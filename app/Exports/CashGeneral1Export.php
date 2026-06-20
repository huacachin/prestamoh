<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\CashGeneral1;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del reporte /reports/cash-general-1 — réplica de reporteee.php (Caja 1).
 * Cabecera de 3 filas (INGRESOS colspan8 / EGRESOS colspan5). Por día: encabezado,
 * filas ingreso/egreso intercaladas, SUB TOTAL y TOTAL. Cierra con Sub Total General
 * y TOTAL GENERAL. Datos del componente CashGeneral1.
 */
class CashGeneral1Export implements FromCollection, WithEvents
{
    use LegacyExcelStyle;

    private const BLUE = '0D6EFD';
    private const RED = 'FF0000';
    private const GRAY = 'B0B0B0';
    private const SUBT = 'F0F0F0';
    private const TC_LABELS = [1 => 'S', 3 => 'M', 4 => 'D'];

    protected array $days = [];
    protected array $tot = [];

    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new CashGeneral1();
        $c->selemes  = (string) ($this->filters['selemes']  ?? str_pad((string) now()->month, 2, '0', STR_PAD_LEFT));
        $c->selecano = (string) ($this->filters['selecano'] ?? now()->year);
        $c->seletipl = (string) ($this->filters['seletipl'] ?? '0000');

        $data = $c->render()->getData();
        $this->days = $data['days'] ?? [];
        $this->tot = [
            'Tcpi' => $data['Tcpi'] ?? 0, 'Tcpi2' => $data['Tcpi2'] ?? 0, 'Tint' => $data['Tint'] ?? 0,
            'Tmor4' => $data['Tmor4'] ?? 0, 'toff' => $data['toff'] ?? 0, 'toff2' => $data['toff2'] ?? 0,
            'toff1' => $data['toff1'] ?? 0,
        ];

        return collect();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildTitleAndHeader($sheet);
                $r = 5;
                $first = $r;
                foreach ($this->days as $day) {
                    $this->buildDay($sheet, $r, $day);
                }
                $this->buildGrandTotals($sheet, $r);
                $this->finishStyles($sheet, $first, $r - 1);
                $this->applyBaseFontAndAutosize($sheet);
            },
        ];
    }

    private function buildTitleAndHeader(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'REPORTE GENERAL CAJA 1');
        $sheet->mergeCells('A1:S1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Fila 2
        $row2 = [
            'A2:A4' => 'N°', 'B2:I2' => 'INGRESOS', 'J2:J4' => 'ASES.', 'K2:K4' => 'T.C.',
            'L2:P2' => 'EGRESOS', 'Q2:Q4' => 'ADM.', 'R2:R4' => 'ASES.', 'S2:S4' => 'T.C.',
        ];
        // Fila 3
        $row3 = [
            'B3:B4' => 'CDG', 'C3:C4' => 'CLIENTE', 'D3:D4' => 'DETALLE', 'E3:E4' => 'N° CUOTAS', 'F3:I3' => 'CUOTAS',
            'L3:L4' => 'CDG', 'M3:M4' => 'CLIENTE', 'N3:N4' => 'MONTO', 'O3:O4' => '%', 'P3:P4' => 'S/',
        ];
        foreach ([$row2, $row3] as $set) {
            foreach ($set as $range => $val) {
                $sheet->mergeCells($range);
                $sheet->setCellValue(explode(':', $range)[0], $val);
            }
        }
        // Fila 4 (sub-cabecera CUOTAS)
        foreach (['F4' => 'TOTAL', 'G4' => 'CAPITAL', 'H4' => 'INTERES', 'I4' => 'MORA'] as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle('A2:S4')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $widths = ['A' => 5, 'B' => 7, 'C' => 22, 'D' => 16, 'E' => 8, 'F' => 11, 'G' => 11, 'H' => 11, 'I' => 10,
            'J' => 10, 'K' => 5, 'L' => 7, 'M' => 22, 'N' => 12, 'O' => 6, 'P' => 11, 'Q' => 10, 'R' => 10, 'S' => 5];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }

    private function buildDay(Worksheet $sheet, int &$r, array $day): void
    {
        // Encabezado del día
        $sheet->mergeCells("A{$r}:S{$r}");
        $sheet->setCellValue("A{$r}", $day['date_label'] ?? '');
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::GRAY]],
        ]);
        $r++;

        $ing = $day['ingresos'] ?? [];
        $egr = $day['egresos'] ?? [];
        $maxRows = max(count($ing), count($egr));

        if ($maxRows === 0) {
            $sheet->mergeCells("A{$r}:S{$r}");
            $sheet->setCellValue("A{$r}", 'SIN MOVIMIENTOS');
            $sheet->getStyle("A{$r}")->getFont()->getColor()->setRGB(self::RED);
            $r++;
        } else {
            for ($i = 0; $i < $maxRows; $i++) {
                $a = $ing[$i] ?? null;
                $e = $egr[$i] ?? null;
                $sheet->setCellValue("A{$r}", $i + 1);

                if ($a) {
                    $sheet->setCellValue("B{$r}", $a['credit_id']);
                    $sheet->setCellValue("C{$r}", $a['cliente']);
                    $sheet->setCellValue("D{$r}", $a['detalle'] ?? '');
                    $sheet->setCellValue("E{$r}", $a['nro_cuotas'] ?? '');
                    $sheet->setCellValue("F{$r}", (float) $a['total']);
                    $sheet->setCellValue("G{$r}", (float) $a['capital']);
                    $sheet->setCellValue("H{$r}", (float) $a['interes']);
                    $sheet->setCellValue("I{$r}", (float) $a['mora']);
                    $sheet->setCellValue("J{$r}", $a['asesor'] ?? '');
                    $sheet->setCellValue("K{$r}", self::TC_LABELS[$a['tipo_planilla']] ?? '?');
                    if (in_array((int) $a['tipo_planilla'], [1, 3], true)) {
                        $sheet->getStyle("A{$r}:K{$r}")->getFont()->getColor()->setRGB(self::RED);
                    }
                    foreach (['F', 'G', 'H', 'I'] as $mc) {
                        $sheet->getStyle("{$mc}{$r}")->getFont()->getColor()->setRGB(self::BLUE);
                    }
                }

                if ($e) {
                    $cli = $e['cliente'];
                    if (!empty($e['cod_rem'])) {
                        $cli .= ' (' . $e['cod_rem'] . ')';
                    }
                    $sheet->setCellValue("L{$r}", $e['credit_id']);
                    $sheet->setCellValue("M{$r}", $cli);
                    $sheet->setCellValue("N{$r}", (float) $e['monto']);
                    $sheet->setCellValue("O{$r}", (float) $e['interes_pct']);
                    $sheet->setCellValue("P{$r}", (float) $e['interes_monto']);
                    $sheet->setCellValue("Q{$r}", $e['usuario'] ?? '');
                    $sheet->setCellValue("R{$r}", $e['asesor'] ?? '');
                    $sheet->setCellValue("S{$r}", self::TC_LABELS[$e['tipo_planilla']] ?? '?');
                    if (in_array((int) $e['tipo_planilla'], [1, 3], true)) {
                        $sheet->getStyle("L{$r}:S{$r}")->getFont()->getColor()->setRGB(self::RED);
                    }
                    $sheet->getStyle("N{$r}")->getFont()->getColor()->setRGB(self::BLUE);
                    $sheet->getStyle("O{$r}")->getFont()->getColor()->setRGB(self::RED);
                    $sheet->getStyle("P{$r}")->getFont()->getColor()->setRGB(self::BLUE);
                }
                $r++;
            }
        }

        // SUB TOTAL del día
        $sheet->mergeCells("B{$r}:E{$r}");
        $sheet->setCellValue("B{$r}", 'SUB TOTAL');
        $sheet->setCellValue("F{$r}", (float) $day['sub_ingresos']);
        $sheet->setCellValue("G{$r}", (float) $day['sub_capital']);
        $sheet->setCellValue("H{$r}", (float) $day['sub_interes']);
        $sheet->setCellValue("I{$r}", (float) $day['sub_mora']);
        $sheet->setCellValue("N{$r}", (float) $day['sub_egresos']);
        $sheet->setCellValue("P{$r}", (float) $day['sub_egresos_interes']);
        $sheet->getStyle("A{$r}:S{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SUBT]],
        ]);
        $r++;

        // TOTAL del día (ingresos + mora) en col H
        $sheet->mergeCells("B{$r}:F{$r}");
        $sheet->setCellValue("B{$r}", 'TOTAL');
        $sheet->setCellValue("H{$r}", (float) $day['sub_ingresos'] + (float) $day['sub_mora']);
        $sheet->getStyle("A{$r}:S{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL]],
        ]);
        $sheet->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
    }

    private function buildGrandTotals(Worksheet $sheet, int &$r): void
    {
        $t = $this->tot;
        // Sub Total General
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->setCellValue("A{$r}", 'Sub Total General');
        $sheet->setCellValue("F{$r}", (float) $t['Tcpi']);
        $sheet->setCellValue("G{$r}", (float) $t['Tcpi2']);
        $sheet->setCellValue("H{$r}", (float) $t['Tint']);
        $sheet->setCellValue("I{$r}", (float) $t['Tmor4']);
        $sheet->setCellValue("N{$r}", (float) $t['toff']);
        $sheet->setCellValue("P{$r}", (float) $t['toff2']);
        $sheet->getStyle("A{$r}:S{$r}")->getFont()->setBold(true);
        foreach (['F', 'G', 'H', 'I'] as $mc) {
            $sheet->getStyle("{$mc}{$r}")->getFont()->getColor()->setRGB(self::BLUE);
        }
        $r++;

        // TOTAL GENERAL
        $sheet->mergeCells("A{$r}:E{$r}");
        $sheet->setCellValue("A{$r}", 'REPORTE GENERAL CAJA 1 - TOTAL GENERAL');
        $sheet->setCellValue("F{$r}", (float) $t['toff1']);
        $sheet->setCellValue("N{$r}", (float) $t['toff']);
        $sheet->setCellValue("P{$r}", (float) $t['toff2']);
        $sheet->getStyle("A{$r}:S{$r}")->getFont()->setBold(true);
        foreach (['F', 'N', 'P'] as $mc) {
            $sheet->getStyle("{$mc}{$r}")->getFont()->getColor()->setRGB(self::RED);
        }
        $r++;
    }

    private function finishStyles(Worksheet $sheet, int $first, int $last): void
    {
        if ($last < $first) {
            return;
        }
        // Formato de moneda en columnas numéricas
        foreach (['F', 'G', 'H', 'I', 'N', 'P'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        // Bordes punteados en todo el cuerpo
        $sheet->getStyle("A2:S{$last}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
        $sheet->getStyle("F5:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("N5:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("P5:P{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
}
