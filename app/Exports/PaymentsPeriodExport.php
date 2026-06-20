<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base de los reportes de pagos SEMANAL/MENSUAL (matriz de 12 periodos) — réplica de
 * pagossemanas.php / pagosmes.php. 29 columnas: 13 fijas + 12 periodos + TOTAL/MORA/OTROS/SALDOS,
 * con filas de subtotales MORA / ACTIVOS / TOTAL. Subclases definen componente y título.
 */
abstract class PaymentsPeriodExport implements FromCollection, WithEvents
{
    use LegacyExcelStyle;

    private const FIXED = 13;
    private const PERIODS = 12;
    private const LAST_IDX = 29; // 13 + 12 + 4
    private const DARK = '005F8C';
    private const YELLOW = 'FFFF00';

    protected array $rows = [];
    protected array $tot = [];
    protected array $sub = [];
    protected float $morosidadPct = 0;
    protected float $activosPct = 0;

    public function __construct(
        protected array $filters = [],
    ) {}

    abstract protected function componentClass(): string;

    abstract protected function reportTitle(): string;

    public function collection()
    {
        $cls = $this->componentClass();
        $c = new $cls();
        $c->ejecutivo = (string) ($this->filters['ejecutivo'] ?? 'Todos');
        $c->eestado = (string) ($this->filters['eestado'] ?? 'Vigente');
        $c->codio1 = (string) ($this->filters['codio1'] ?? '');

        $data = $c->render()->getData();
        $this->rows = $data['rows'] ?? [];
        $this->tot = $data['tot'] ?? [];
        $this->sub = $data['sub'] ?? ['mora' => [], 'activo' => []];
        $this->morosidadPct = (float) ($data['morosidadPct'] ?? 0);
        $this->activosPct = (float) ($data['activosPct'] ?? 0);

        return collect();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->colLetter(self::LAST_IDX); // AC
                $this->buildTitleAndHeader($sheet, $last);
                $r = 3;
                $first = $r;
                foreach ($this->rows as $row) {
                    $this->buildRow($sheet, $r, $row);
                    $r++;
                }
                $lastData = $r - 1;
                if (! empty($this->rows)) {
                    $this->buildTotal($sheet, $r, $last);
                    $r++;
                    $this->buildMorosidad($sheet, $r);
                }
                $this->finishStyles($sheet, $first, $lastData, $last);
            },
        ];
    }

    private function periodCol(int $d): string
    {
        return $this->colLetter(self::FIXED + $d);
    }

    private function tailCol(int $i): string
    {
        return $this->colLetter(self::FIXED + self::PERIODS + $i);
    }

    private function buildTitleAndHeader(Worksheet $sheet, string $last): void
    {
        $sheet->setCellValue('A1', $this->reportTitle());
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $fixed = ['N.', 'F.Cr.', 'F.Ve.', 'EXP.', 'COD.', 'N.C', 'DNI', 'CLIENTE', 'CAPITAL', '%', 'INT.', 'T.P', 'C.'];
        foreach ($fixed as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . '2', $h);
        }
        for ($d = 1; $d <= self::PERIODS; $d++) {
            $sheet->setCellValue($this->periodCol($d) . '2', $d);
        }
        foreach (['TOTAL', 'MORA', 'OTROS', 'SALDOS'] as $i => $h) {
            $sheet->setCellValue($this->tailCol($i + 1) . '2', $h);
        }

        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $fixedW = [6, 11, 11, 7, 8, 5, 11, 30, 11, 5, 10, 11, 9];
        foreach ($fixedW as $i => $w) {
            $sheet->getColumnDimension($this->colLetter($i + 1))->setWidth($w);
        }
        for ($d = 1; $d <= self::PERIODS; $d++) {
            $sheet->getColumnDimension($this->periodCol($d))->setWidth(11);
        }
        for ($i = 1; $i <= 4; $i++) {
            $sheet->getColumnDimension($this->tailCol($i))->setWidth(11);
        }
    }

    private function buildRow(Worksheet $sheet, int $r, array $row): void
    {
        $sheet->setCellValue("A{$r}", $row['n']);
        $sheet->setCellValue("B{$r}", $row['fecha_pres']);
        $sheet->setCellValue("C{$r}", $row['fecha_venc']);
        $sheet->setCellValue("D{$r}", $row['expediente']);
        if (empty($row['has_imagen'])) {
            $sheet->getStyle("D{$r}")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::YELLOW]]]);
        }
        $sheet->setCellValue("E{$r}", $row['codigo']);
        $sheet->setCellValue("F{$r}", $row['cuotas']);
        $sheet->setCellValue("G{$r}", (string) $row['dni']);
        $sheet->setCellValue("H{$r}", $row['cliente']);
        $sheet->setCellValue("I{$r}", (float) $row['capital']);
        $sheet->setCellValue("J{$r}", $row['interes_pct']);
        $sheet->setCellValue("K{$r}", (float) $row['interes']);
        $sheet->setCellValue("L{$r}", (float) $row['apagar']);
        $sheet->setCellValue("M{$r}", (float) $row['cuota']);

        $d = 1;
        foreach ($row['cuotas_cols'] as $col) {
            $cell = $this->periodCol($d) . $r;
            $monto = $col['monto'];
            $sheet->setCellValue($cell, ($monto !== null && (float) $monto != 0) ? (float) $monto : '');
            $this->applyCellColor($sheet, $cell, $col['bg'] ?? '', $col['color'] ?? '');
            $d++;
        }

        $sheet->setCellValue($this->tailCol(1) . $r, (float) $row['pagado']);
        $sheet->setCellValue($this->tailCol(2) . $r, (float) $row['mora']);
        $sheet->setCellValue($this->tailCol(3) . $r, (float) $row['otros']);
        $sheet->setCellValue($this->tailCol(4) . $r, (float) $row['saldo']);
    }

    private function applyCellColor(Worksheet $sheet, string $cell, string $bg, string $color): void
    {
        $styles = [];
        if ($bg !== '') {
            $rgb = $bg === 'yellow' ? self::YELLOW : ($bg === 'red' ? 'FF0000' : ($bg === 'green' ? '008000' : ltrim($bg, '#')));
            if (strlen($rgb) === 3) {
                $rgb = $rgb[0] . $rgb[0] . $rgb[1] . $rgb[1] . $rgb[2] . $rgb[2];
            }
            $styles['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => strtoupper($rgb)]];
        }
        if ($color !== '') {
            $rgb = $color === 'red' ? 'FF0000' : ($color === 'green' ? '008000' : ltrim($color, '#'));
            $styles['font'] = ['color' => ['rgb' => strtoupper($rgb)]];
        }
        if ($styles) {
            $sheet->getStyle($cell)->applyFromArray($styles);
        }
    }

    private function buildTotal(Worksheet $sheet, int $r, string $last): void
    {
        $t = $this->tot;
        $sheet->mergeCells("A{$r}:H{$r}");
        $sheet->setCellValue("A{$r}", 'Total');
        $sheet->setCellValue("I{$r}", (float) ($t['capital'] ?? 0));
        $sheet->setCellValue("K{$r}", (float) ($t['interes'] ?? 0));
        $sheet->setCellValue("L{$r}", (float) ($t['apagar'] ?? 0));
        $sheet->setCellValue("M{$r}", (float) ($t['cuota'] ?? 0));
        $sheet->setCellValue($this->tailCol(1) . $r, (float) ($t['pagado'] ?? 0));
        $sheet->setCellValue($this->tailCol(2) . $r, (float) ($t['mora'] ?? 0));
        $sheet->setCellValue($this->tailCol(3) . $r, (float) ($t['otros'] ?? 0));
        $sheet->setCellValue($this->tailCol(4) . $r, (float) ($t['saldo'] ?? 0));
        $this->markTotalRow($sheet, $r, $last);
    }

    private function buildMorosidad(Worksheet $sheet, int &$r): void
    {
        $mora = $this->sub['mora'] ?? [];
        $activo = $this->sub['activo'] ?? [];
        $t = $this->tot;
        $totN = (int) ($mora['n'] ?? 0) + (int) ($activo['n'] ?? 0);

        $blocks = [
            ['pct' => $this->morosidadPct, 'pctColor' => 'FF0000', 'lbl' => 'MORA', 'lblColor' => 'FF0000', 'n' => $mora['n'] ?? 0, 'totLbl' => 'TOTAL MORA', 'data' => $mora],
            ['pct' => $this->activosPct, 'pctColor' => '008000', 'lbl' => 'ACTIVOS', 'lblColor' => '008000', 'n' => $activo['n'] ?? 0, 'totLbl' => 'TOTAL ACTIVOS', 'data' => $activo],
            ['pct' => 100, 'pctColor' => '0000FF', 'lbl' => 'TOTAL', 'lblColor' => self::DARK, 'n' => $totN, 'totLbl' => 'TOTAL', 'data' => $t],
        ];

        foreach ($blocks as $b) {
            $d = $b['data'];
            $sheet->mergeCells("A{$r}:C{$r}");
            $sheet->setCellValue("A{$r}", number_format($b['pct'], 2) . '%');
            $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $b['pctColor']]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

            $sheet->setCellValue("D{$r}", $b['lbl']);
            $sheet->getStyle("D{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $b['lblColor']]]]);

            $sheet->setCellValue("E{$r}", $b['n']);
            $sheet->mergeCells("F{$r}:H{$r}");
            $sheet->setCellValue("F{$r}", $b['totLbl']);
            $sheet->getStyle("E{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::DARK]]]);

            $sheet->setCellValue("I{$r}", (float) ($d['capital'] ?? 0));
            $sheet->setCellValue("K{$r}", (float) ($d['interes'] ?? 0));
            $sheet->setCellValue("L{$r}", (float) ($d['apagar'] ?? 0));
            $sheet->setCellValue("M{$r}", (float) ($d['cuota'] ?? 0));
            $sheet->setCellValue($this->tailCol(1) . $r, (float) ($d['pagado'] ?? 0));
            $sheet->setCellValue($this->tailCol(2) . $r, (float) ($d['mora'] ?? 0));
            $sheet->setCellValue($this->tailCol(3) . $r, (float) ($d['otros'] ?? 0));
            $sheet->setCellValue($this->tailCol(4) . $r, (float) ($d['saldo'] ?? 0));

            // Celdas amarillas (I..M y periodos y cola)
            $sheet->getStyle("I{$r}:" . $this->colLetter(self::LAST_IDX) . $r)
                ->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::YELLOW]]]);
            // Capital(I), T.P(L), Saldo(AC) en rojo
            foreach (['I', 'L', $this->tailCol(4)] as $rc) {
                $sheet->getStyle("{$rc}{$r}")->getFont()->getColor()->setRGB('FF0000');
            }
            $r++;
        }
    }

    private function finishStyles(Worksheet $sheet, int $first, int $lastData, string $last): void
    {
        $moneyCols = ['I', 'K', 'L', 'M'];
        for ($d = 1; $d <= self::PERIODS; $d++) {
            $moneyCols[] = $this->periodCol($d);
        }
        for ($i = 1; $i <= 4; $i++) {
            $moneyCols[] = $this->tailCol($i);
        }
        // Formato sobre datos + Total + filas de morosidad
        $highest = $sheet->getHighestRow();
        if ($lastData >= $first) {
            foreach ($moneyCols as $col) {
                $sheet->getStyle("{$col}3:{$col}{$highest}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        $sheet->getStyle("A2:{$last}{$highest}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
        if ($lastData >= $first) {
            $sheet->getStyle("A{$first}:F{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
}
