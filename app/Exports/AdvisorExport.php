<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Advisor as AdvisorLivewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del reporte /reports/advisor — réplica de venta_reporteee.php.
 * Título "REPORTE DE ASESOR". DOS tablas (diaria + mensual) con cabecera multi-fila
 * (CLIENTES / COBRADOS / NO COBRADOS) y filas Totales + Promedio en cada una.
 */
class AdvisorExport implements FromCollection, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected array $tot = [];
    protected array $avg = [];
    protected array $monthly = [];
    protected int $rowCount = 0;
    protected int $year = 0;
    /** @var array<int,string> sheetRow => color de fin de semana */
    protected array $rowColors = [];

    public function __construct(
        protected array $filters = [],
    ) {}

    /** Título fila1, cabecera filas 2-3, datos diarios desde fila 4. */
    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $c = new AdvisorLivewire();
        $c->selemes   = (string) ($this->filters['selemes']  ?? str_pad((string) now()->month, 2, '0', STR_PAD_LEFT));
        $c->selecano  = (string) ($this->filters['selecano'] ?? now()->year);
        $c->ejecutivo = (string) ($this->filters['ejecutivo'] ?? 'Todos');
        $this->year   = (int) $c->selecano;

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->tot     = $data['tot'] ?? [];
        $this->avg     = $data['avg'] ?? [];
        $this->monthly = $data['monthlyHistory'] ?? [];
        $this->rowCount = $rows->count();

        // Colores fin de semana por fila (datos desde fila 4)
        foreach ($rows->values() as $idx => $r) {
            if (!empty($r['color'])) {
                $this->rowColors[4 + $idx] = $r['color'] === 'red' ? 'FF0000' : '008000';
            }
        }

        return $rows;
    }

    public function map($r): array
    {
        static $i = 0;
        $i++;

        return [
            $i,                                  // A  Nº
            $r['fecha']      ?? '',              // B  FECHA
            (int)   ($r['nuevo']      ?? 0),     // C  NUEVO
            (int)   ($r['renov']      ?? 0),     // D  REN./REF.
            (int)   ($r['canc']       ?? 0),     // E  CANC.
            (int)   ($r['total']      ?? 0),     // F  TOTAL
            (float) ($r['capital']    ?? 0),     // G  CAPITAL
            (float) ($r['imp_cobrar'] ?? 0),     // H  IMP. A COBRAR
            (int)   ($r['cob_cnt']    ?? 0),     // I  COBRADOS CANT.
            (float) ($r['cob_imp']    ?? 0),     // J  COBRADOS IMPORTE
            (int)   ($r['noc_cnt']    ?? 0),     // K  NO COB. CANT.
            (float) ($r['noc_imp']    ?? 0),     // L  NO COB. IMPORTE
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'L';

                // Título
                $sheet->setCellValue('A1', 'REPORTE DE ASESOR');
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);

                // Cabecera tabla diaria (filas 2-3)
                $this->buildHeader($sheet, 2, 'FECHA', 'NUEVO', 'REN./REF.');

                // Colores fin de semana en la columna FECHA
                foreach ($this->rowColors as $row => $rgb) {
                    $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB($rgb);
                }

                // Bordes datos diarios
                $lastData = 3 + $this->rowCount;
                $sheet->getStyle("A2:{$lastCol}{$lastData}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
                ]);

                // Formato moneda + alineación centrada en datos diarios (G,H,J,L son montos)
                if ($this->rowCount > 0) {
                    $sheet->getStyle("A4:{$lastCol}{$lastData}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    foreach (['G', 'H', 'J', 'L'] as $col) {
                        $sheet->getStyle("{$col}4:{$col}{$lastData}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                // Totales + Promedio diarios
                $r = $lastData + 1;
                $this->totalRow($sheet, $r, 'Totales', $this->tot);
                $r++;
                $this->totalRow($sheet, $r, 'Promedio', $this->avg);

                // ── Tabla MENSUAL ──
                $r += 2;
                $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                $sheet->setCellValue("A{$r}", 'REPORTE MENSUAL ' . $this->year);
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::COLOR_TITULO]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $hdr = $r + 1;
                $this->buildHeader($sheet, $hdr, 'MES', 'X.C.N.', 'X.R.C.');
                $dataStart = $hdr + 2;
                $rr = $dataStart - 1;
                foreach (($this->monthly['rows'] ?? []) as $m) {
                    $rr++;
                    $this->writeDataRow($sheet, $rr, $m['n'] ?? '', $m['mes'] ?? '', $m);
                }
                $sheet->getStyle("A{$hdr}:{$lastCol}{$rr}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
                ]);
                if ($rr >= $dataStart) {
                    $sheet->getStyle("A{$dataStart}:{$lastCol}{$rr}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
                $rr++;
                $this->totalRow($sheet, $rr, 'Totales', $this->monthly['tot'] ?? []);
                $rr++;
                $this->totalRow($sheet, $rr, 'Promedio', $this->monthly['avg'] ?? []);
            },
        ];
    }

    /** Cabecera multi-fila (2 filas) a partir de $top. */
    private function buildHeader(Worksheet $sheet, int $top, string $colB, string $lblNuevo, string $lblRenov): void
    {
        $bot = $top + 1;
        $merge = [
            "A{$top}:A{$bot}" => 'Nº', "B{$top}:B{$bot}" => $colB,
            "C{$top}:G{$top}" => 'CLIENTES', "H{$top}:H{$bot}" => 'IMP. A COBRAR',
            "I{$top}:J{$top}" => 'COBRADOS', "K{$top}:L{$top}" => 'NO COBRADOS',
        ];
        foreach ($merge as $range => $val) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $val);
        }
        $sub = [
            "C{$bot}" => $lblNuevo, "D{$bot}" => $lblRenov, "E{$bot}" => 'CANC.', "F{$bot}" => 'TOTAL', "G{$bot}" => 'CAPITAL',
            "I{$bot}" => 'CANT.', "J{$bot}" => 'IMPORTE', "K{$bot}" => 'CANT.', "L{$bot}" => 'IMPORTE',
        ];
        foreach ($sub as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }
        $sheet->getStyle("A{$top}:L{$bot}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);
    }

    private function writeDataRow(Worksheet $sheet, int $r, $col1, $col2, array $d): void
    {
        $sheet->setCellValue("A{$r}", $col1);
        $sheet->setCellValue("B{$r}", $col2);
        $sheet->setCellValue("C{$r}", $d['nuevo'] ?? 0);
        $sheet->setCellValue("D{$r}", $d['renov'] ?? 0);
        $sheet->setCellValue("E{$r}", $d['canc'] ?? 0);
        $sheet->setCellValue("F{$r}", $d['total'] ?? 0);
        $sheet->setCellValue("G{$r}", number_format($d['capital'] ?? 0, 2));
        $sheet->setCellValue("H{$r}", number_format($d['imp_cobrar'] ?? 0, 2));
        $sheet->setCellValue("I{$r}", $d['cob_cnt'] ?? 0);
        $sheet->setCellValue("J{$r}", number_format($d['cob_imp'] ?? 0, 2));
        $sheet->setCellValue("K{$r}", $d['noc_cnt'] ?? 0);
        $sheet->setCellValue("L{$r}", number_format($d['noc_imp'] ?? 0, 2));
    }

    private function totalRow(Worksheet $sheet, int $r, string $label, array $d): void
    {
        $sheet->mergeCells("A{$r}:B{$r}");
        $sheet->setCellValue("A{$r}", $label);
        $sheet->setCellValue("C{$r}", round($d['nuevo'] ?? 0, 2));
        $sheet->setCellValue("D{$r}", round($d['renov'] ?? 0, 2));
        $sheet->setCellValue("E{$r}", round($d['canc'] ?? 0, 2));
        $sheet->setCellValue("F{$r}", round($d['total'] ?? 0, 2));
        $sheet->setCellValue("G{$r}", number_format($d['capital'] ?? 0, 2));
        $sheet->setCellValue("H{$r}", number_format($d['imp_cobrar'] ?? 0, 2));
        $sheet->setCellValue("I{$r}", round($d['cob_cnt'] ?? 0, 2));
        $sheet->setCellValue("J{$r}", number_format($d['cob_imp'] ?? 0, 2));
        $sheet->setCellValue("K{$r}", round($d['noc_cnt'] ?? 0, 2));
        $sheet->setCellValue("L{$r}", number_format($d['noc_imp'] ?? 0, 2));
        $this->markTotalRow($sheet, $r, 'L');
    }
}
