<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Cancelled;
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
 * Excel del reporte /reports/cancelled — réplica de canceladoe.php.
 * Título "CREDITO CANCELADO". Cabecera de 2 filas (Capital / Interes Ganado / Mora x Cob.),
 * 22 columnas (Nº ocupa 2: global + por día), total general de 2 filas y tabla de distribución.
 */
class CancelledExport implements FromCollection, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected array $totals = [];
    protected array $distribution = [];
    protected int $rowCount = 0;
    /** @var array<int,array{fill:?string,font:string}> estilo por fila de datos (sheet row => estilo) */
    protected array $rowFills = [];

    public function __construct(
        protected array $filters = [],
    ) {}

    /** Datos desde fila 4: fila1=título, filas 2-3=cabecera multi-fila. */
    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $c = new Cancelled();
        $c->selemes  = $this->filters['selemes']  ?? date('m');
        $c->selecano = $this->filters['selecano'] ?? date('Y');
        $c->seletipl = (string) ($this->filters['seletipl'] ?? '');
        $c->exp      = (string) ($this->filters['exp']      ?? '');
        $c->codigo   = (string) ($this->filters['codigo']   ?? '');
        $c->cdni     = (string) ($this->filters['cdni']     ?? '');
        $c->cnombre  = (string) ($this->filters['cnombre']  ?? '');
        $c->casesor  = (string) ($this->filters['casesor']  ?? '');

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->totals       = $data['totals'] ?? [];
        $this->distribution = $data['distribution'] ?? [];
        $this->rowCount     = $rows->count();

        // Estilo de resaltado por fila (réplica de los bg del legacy). Datos desde fila 4.
        foreach ($rows->values() as $idx => $r) {
            $sheetRow = 4 + $idx;
            $bg = (string) ($r['bg'] ?? '');
            $fill = null;
            if (preg_match('/#([0-9a-fA-F]{6})/', $bg, $m)) {
                $fill = strtoupper($m[1]);
            } elseif (str_contains($bg, 'yellow')) {
                $fill = 'FFFF00';
            }
            if ($fill) {
                $this->rowFills[$sheetRow] = [
                    'fill' => $fill,
                    'font' => ($r['color_texto'] ?? 'black') === 'white' ? 'FFFFFF' : '000000',
                ];
            }
        }

        return $rows;
    }

    public function map($r): array
    {
        return [
            $r['n']            ?? '',  // A  Nº global
            $r['tot2']         ?? '',  // B  Nº por día
            $r['exp']          ?? '',  // C  Exp
            $r['codigo']       ?? '',  // D  Codigo
            (string) ($r['dni'] ?? ''),// E  Dni
            $r['nombre']       ?? '',  // F  Nombre
            (float) ($r['capital']      ?? 0), // G  Capital
            (float) ($r['r_capital']    ?? 0), // H  R./ Capital
            (float) ($r['capital_neto'] ?? 0), // I  Capital Neto
            strip_tags((string) ($r['detalles'] ?? '')), // J  Detalles
            (float) ($r['interes_pct']  ?? 0), // K  %
            (float) ($r['interes_s']    ?? 0), // L  S/ (interés)
            (float) ($r['mora']         ?? 0), // M  Mora
            (float) ($r['total']        ?? 0), // N  Total
            (float) ($r['mxd']          ?? 0), // O  S/ (mora x cob)
            (float) ($r['mora_s']       ?? 0), // P  MxD
            (int)   ($r['dias']         ?? 0), // Q  Dias
            $r['fec_cred']     ?? '',  // R  Fec/Cred
            $r['fec_venc']     ?? '',  // S  Fec/Venc
            $r['fec_cancel']   ?? '',  // T  Fecha Cancelado
            $r['estado']       ?? '',  // U  Estado
            $r['asesor']       ?? '',  // V  Ases.
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'V';

                $this->buildTitleAndHeader($sheet, $lastCol);
                $this->buildDataStyles($sheet);
                $this->buildRowHighlights($sheet, $lastCol);
                $this->buildTotals($sheet, $lastCol);
                $this->applyBaseFontAndAutosize($sheet);
            },
        ];
    }

    /** Fila 1 (título) + filas 2-3 (cabecera multi-fila con merges). */
    private function buildTitleAndHeader(Worksheet $sheet, string $lastCol): void
    {
        // Título
        $sheet->setCellValue('A1', 'CREDITO CANCELADO');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Cabecera fila 1 (rowspan/colspan) + fila 2 (sub-headers)
        $merge2 = [
            'A2:B3' => 'Nº', 'C2:C3' => 'Exp', 'D2:D3' => 'Codigo', 'E2:E3' => 'Dni', 'F2:F3' => 'Nombre',
            'G2:I2' => 'Capital', 'J2:M2' => 'Interes Ganado', 'N2:N3' => 'Total', 'O2:Q2' => 'Mora x Cob.',
            'R2:R3' => 'Fec/Cred', 'S2:S3' => 'Fec/Venc', 'T2:T3' => 'Fecha Cancelado', 'U2:U3' => 'Estado', 'V2:V3' => 'Ases.',
        ];
        foreach ($merge2 as $range => $val) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $val);
        }
        $sub = [
            'G3' => 'Capital', 'H3' => 'R./ Capital', 'I3' => 'Capital Neto',
            'J3' => 'Detalles', 'K3' => '%', 'L3' => 'S/', 'M3' => 'Mora',
            'O3' => 'S/', 'P3' => 'MxD', 'Q3' => 'Dias',
        ];
        foreach ($sub as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        // Estilo header (azul, blanco, bold, centrado) en filas 2-3
        $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        // Bordes punteados en cabecera + datos
        $lastData = 3 + $this->rowCount;
        if ($lastData >= 2) {
            $sheet->getStyle("A2:{$lastCol}{$lastData}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
            ]);
        }
    }

    /** Formato de moneda + alineación centrada en el rango de datos (ShouldAutoSize maneja anchos). */
    private function buildDataStyles(Worksheet $sheet): void
    {
        if ($this->rowCount < 1) {
            return;
        }
        $first = 4;
        $last  = 3 + $this->rowCount;

        $sheet->getStyle("A{$first}:V{$last}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Columnas de montos (no formatear K=% ni Q=Dias)
        foreach (['G', 'H', 'I', 'L', 'M', 'N', 'O', 'P'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    /** Resaltado de filas (rojo/verde/amarillo) como en el legacy. */
    private function buildRowHighlights(Worksheet $sheet, string $lastCol): void
    {
        foreach ($this->rowFills as $row => $st) {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['color' => ['rgb' => $st['font']]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $st['fill']]],
            ]);
        }
    }

    /** Total General (2 filas) + tabla de distribución. */
    private function buildTotals(Worksheet $sheet, string $lastCol): void
    {
        $t = $this->totals;
        $tg  = 4 + $this->rowCount;       // fila Total General (1)
        $tg2 = $tg + 1;                   // fila Total General (2)

        $sheet->setCellValue("A{$tg}", 'Total General');
        $sheet->mergeCells("A{$tg}:F{$tg2}");
        $sheet->setCellValue("G{$tg}", number_format($t['cancecapi']    ?? 0, 2));
        $sheet->setCellValue("H{$tg}", number_format($t['canceinteg']   ?? 0, 2));
        $sheet->setCellValue("I{$tg}", number_format($t['todf1']        ?? 0, 2));
        $sheet->setCellValue("L{$tg}", number_format($t['canceinte']    ?? 0, 2));
        $sheet->setCellValue("M{$tg}", number_format($t['cancemora']    ?? 0, 2));
        $sheet->setCellValue("N{$tg}", number_format($t['totGP']        ?? 0, 2));
        $sheet->setCellValue("O{$tg}", number_format($t['montomorxdia'] ?? 0, 2));

        // Fila 2: total combinado (interés + mora + gat) bajo L:N
        $combinado = ($t['canceinte'] ?? 0) + ($t['cancemora'] ?? 0) + ($t['total_gat'] ?? 0);
        $sheet->mergeCells("L{$tg2}:N{$tg2}");
        $sheet->setCellValue("L{$tg2}", number_format($combinado, 2));

        foreach ([$tg, $tg2] as $r) {
            $this->markTotalRow($sheet, $r, $lastCol);
        }
        $sheet->getStyle("A{$tg}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // ── Tabla de distribución (Detalle | % | S/ | % | S/ | % | S/) ──
        $dh = $tg2 + 2; // fila de cabecera de distribución (deja una fila en blanco)
        $sheet->mergeCells("A{$dh}:E{$dh}");
        $sheet->setCellValue("A{$dh}", 'Detalle');
        $sheet->setCellValue("F{$dh}", '%');
        $sheet->setCellValue("G{$dh}", 'S/');
        $sheet->setCellValue("H{$dh}", '%');
        $sheet->setCellValue("I{$dh}", 'S/');
        $sheet->setCellValue("J{$dh}", '%');
        $sheet->mergeCells("K{$dh}:L{$dh}");
        $sheet->setCellValue("K{$dh}", 'S/');
        $sheet->getStyle("A{$dh}:L{$dh}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $r = $dh;
        foreach ($this->distribution as $d) {
            $r++;
            $sheet->mergeCells("A{$r}:E{$r}");
            $sheet->setCellValue("A{$r}", $d['label'] ?? '');
            $sheet->setCellValue("F{$r}", $d['pct1'] ?? '');
            $sheet->setCellValue("G{$r}", number_format($d['val1'] ?? 0, 2));
            $sheet->setCellValue("H{$r}", $d['pct2'] ?? '');
            $sheet->setCellValue("I{$r}", number_format($d['val2'] ?? 0, 2));
            $sheet->setCellValue("J{$r}", $d['pct3'] ?? '');
            $sheet->mergeCells("K{$r}:L{$r}");
            $sheet->setCellValue("K{$r}", number_format($d['val3'] ?? 0, 2));
            if (($d['label'] ?? '') === 'Total') {
                $this->markTotalRow($sheet, $r, 'L');
            }
        }
        $sheet->getStyle("A{$dh}:L{$r}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
    }
}
