<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Payments\Daily;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del reporte de pagos DIARIO (/payments/daily) — réplica de pagos_dia.php.
 * Matriz de 48 columnas: 12 fijas + 32 días + TOTAL/MORA/OTROS/SALDOS.
 * Colores por día: domingo rojo, sábado verde, vencido sin pago rojo, hoy amarillo, futuro gris.
 */
class PaymentsDailyExport implements FromCollection, WithEvents
{
    use LegacyExcelStyle;

    private const FIXED = 12;          // columnas fijas antes de los días
    private const DAYS = 32;
    private const LASTCOL_IDX = 48;    // 12 + 32 + 4

    private const DARK = '005F8C';
    private const YELLOW = 'FFFF00';

    protected array $rows = [];
    protected array $tot = [];
    protected array $sub = [];
    protected float $morosidadPct = 0;
    protected float $activosPct = 0;
    protected string $today = '';

    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new Daily();
        $c->ejecutivo = (string) ($this->filters['ejecutivo'] ?? 'Todos');
        $c->eestado   = (string) ($this->filters['eestado'] ?? 'Vigente');
        $c->codio1    = (string) ($this->filters['codio1'] ?? '');

        $data = $c->render()->getData();
        $this->rows = $data['rows'] ?? [];
        $this->tot = $data['tot'] ?? [];
        $this->sub = $data['sub'] ?? ['mora' => [], 'activo' => []];
        $this->morosidadPct = (float) ($data['morosidadPct'] ?? 0);
        $this->activosPct = (float) ($data['activosPct'] ?? 0);
        $this->today = $data['today'] ?? now()->format('Y-m-d');

        return collect();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->colLetter(self::LASTCOL_IDX); // AV
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
                $this->applyBaseFontAndAutosize($sheet);
            },
        ];
    }

    private function buildTitleAndHeader(Worksheet $sheet, string $last): void
    {
        $sheet->setCellValue('A1', 'REPORTE CREDITO DIARIO');
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $fixed = ['N.', 'FECHA', 'EXP.', 'COD.', 'N.C', 'DNI', 'CLIENTE', 'CAPITAL', '%', 'INT.', 'T.P', 'C.'];
        foreach ($fixed as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . '2', $h);
        }
        for ($d = 1; $d <= self::DAYS; $d++) {
            $sheet->setCellValue($this->colLetter(self::FIXED + $d) . '2', $d);
        }
        $tail = ['TOTAL', 'MORA', 'OTROS', 'SALDOS'];
        foreach ($tail as $i => $h) {
            $sheet->setCellValue($this->colLetter(self::FIXED + self::DAYS + $i + 1) . '2', $h);
        }

        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        // Anchos: fijas legibles, días angostos
        $fixedW = [6, 11, 7, 8, 5, 11, 30, 11, 5, 10, 11, 9];
        foreach ($fixedW as $i => $w) {
            $sheet->getColumnDimension($this->colLetter($i + 1))->setWidth($w);
        }
        for ($d = 1; $d <= self::DAYS; $d++) {
            $sheet->getColumnDimension($this->colLetter(self::FIXED + $d))->setWidth(7);
        }
        foreach (['TOTAL', 'MORA', 'OTROS', 'SALDOS'] as $i => $h) {
            $sheet->getColumnDimension($this->colLetter(self::FIXED + self::DAYS + $i + 1))->setWidth(11);
        }
    }

    private function buildRow(Worksheet $sheet, int $r, array $row): void
    {
        $sheet->setCellValue("A{$r}", $row['n']);
        $sheet->setCellValue("B{$r}", $row['fecha']);
        $sheet->setCellValue("C{$r}", $row['expediente']);
        if (empty($row['has_imagen'])) {
            $sheet->getStyle("C{$r}")->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']]]);
        }
        $sheet->setCellValue("D{$r}", $row['codigo']);
        $sheet->setCellValue("E{$r}", $row['cuotas']);
        $sheet->setCellValue("F{$r}", (string) $row['dni']);
        $sheet->setCellValue("G{$r}", $row['cliente']);
        $sheet->setCellValue("H{$r}", (float) $row['capital']);
        $sheet->setCellValue("I{$r}", $row['interes_pct']);
        $sheet->setCellValue("J{$r}", (float) $row['interes']);
        $sheet->setCellValue("K{$r}", (float) $row['apagar']);
        $sheet->setCellValue("L{$r}", (float) $row['cuota']);

        $d = 1;
        foreach ($row['days'] as $day) {
            $col = $this->colLetter(self::FIXED + $d);
            $monto = (float) $day['monto'];
            $sheet->setCellValue("{$col}{$r}", $monto > 0 ? $monto : '');
            $styles = [];
            if (!empty($day['bg'])) {
                $rgb = $day['bg'] === 'yellow' ? 'FFFF00' : ($day['bg'] === 'red' ? 'FF0000' : '999999');
                $styles['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]];
            }
            $font = [];
            if (!empty($day['color'])) {
                $font['color'] = ['rgb' => $day['color'] === 'red' ? 'FF0000' : ($day['color'] === 'green' ? '008000' : 'E7E7E7')];
            }
            if (!empty($day['weight'])) {
                $font['bold'] = true;
            }
            if ($font) {
                $styles['font'] = $font;
            }
            if ($styles) {
                $sheet->getStyle("{$col}{$r}")->applyFromArray($styles);
            }
            $d++;
        }

        $base = self::FIXED + self::DAYS;
        $sheet->setCellValue($this->colLetter($base + 1) . $r, (float) $row['pagado']);
        $sheet->setCellValue($this->colLetter($base + 2) . $r, (float) $row['mora']);
        $sheet->setCellValue($this->colLetter($base + 3) . $r, (float) $row['otros']);
        $sheet->setCellValue($this->colLetter($base + 4) . $r, (float) $row['saldo']);
    }

    private function buildTotal(Worksheet $sheet, int $r, string $last): void
    {
        $t = $this->tot;
        $sheet->mergeCells("A{$r}:G{$r}");
        $sheet->setCellValue("A{$r}", 'Total');
        $sheet->setCellValue("H{$r}", (float) ($t['capital'] ?? 0));
        $sheet->setCellValue("J{$r}", (float) ($t['interes'] ?? 0));
        $sheet->setCellValue("K{$r}", (float) ($t['apagar'] ?? 0));
        $sheet->setCellValue("L{$r}", (float) ($t['cuota'] ?? 0));
        $base = self::FIXED + self::DAYS;
        $sheet->setCellValue($this->colLetter($base + 1) . $r, (float) ($t['pagado'] ?? 0));
        $sheet->setCellValue($this->colLetter($base + 2) . $r, (float) ($t['mora'] ?? 0));
        $sheet->setCellValue($this->colLetter($base + 3) . $r, (float) ($t['otros'] ?? 0));
        $sheet->setCellValue($this->colLetter($base + 4) . $r, (float) ($t['saldo'] ?? 0));
        $this->markTotalRow($sheet, $r, $last);
    }

    private function buildMorosidad(Worksheet $sheet, int &$r): void
    {
        $mora = $this->sub['mora'] ?? [];
        $activo = $this->sub['activo'] ?? [];
        $t = $this->tot;
        $totN = (int) ($mora['n'] ?? 0) + (int) ($activo['n'] ?? 0);
        $dayStart = $this->colLetter(self::FIXED + 1);
        $dayEnd = $this->colLetter(self::FIXED + self::DAYS);
        $lastCol = $this->colLetter(self::LASTCOL_IDX);

        $blocks = [
            ['pct' => $this->morosidadPct, 'pctColor' => 'FF0000', 'lbl' => 'MORA', 'lblColor' => 'FF0000', 'n' => $mora['n'] ?? 0, 'totLbl' => 'TOTAL MORA', 'd' => $mora],
            ['pct' => $this->activosPct, 'pctColor' => '008000', 'lbl' => 'ACTIVOS', 'lblColor' => '008000', 'n' => $activo['n'] ?? 0, 'totLbl' => 'TOTAL ACTIVOS', 'd' => $activo],
            ['pct' => 100, 'pctColor' => '0000FF', 'lbl' => 'TOTAL', 'lblColor' => self::DARK, 'n' => $totN, 'totLbl' => 'TOTAL', 'd' => $t],
        ];

        foreach ($blocks as $b) {
            $d = $b['d'];
            $sheet->mergeCells("A{$r}:C{$r}");
            $sheet->setCellValue("A{$r}", number_format($b['pct'], 2) . '%');
            $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $b['pctColor']]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

            $sheet->setCellValue("D{$r}", $b['lbl']);
            $sheet->getStyle("D{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $b['lblColor']]]]);

            $sheet->setCellValue("E{$r}", $b['n']);
            $sheet->mergeCells("F{$r}:G{$r}");
            $sheet->setCellValue("F{$r}", $b['totLbl']);
            $sheet->getStyle("E{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::DARK]]]);

            $sheet->setCellValue("H{$r}", (float) ($d['capital'] ?? 0));
            $sheet->setCellValue("J{$r}", (float) ($d['interes'] ?? 0));
            $sheet->setCellValue("K{$r}", (float) ($d['apagar'] ?? 0));
            $sheet->setCellValue("L{$r}", (float) ($d['cuota'] ?? 0));
            $base = self::FIXED + self::DAYS;
            $sheet->setCellValue($this->colLetter($base + 1) . $r, (float) ($d['pagado'] ?? 0));
            $sheet->setCellValue($this->colLetter($base + 2) . $r, (float) ($d['mora'] ?? 0));
            $sheet->setCellValue($this->colLetter($base + 3) . $r, (float) ($d['otros'] ?? 0));
            $sheet->setCellValue($this->colLetter($base + 4) . $r, (float) ($d['saldo'] ?? 0));

            $sheet->mergeCells("{$dayStart}{$r}:{$dayEnd}{$r}");
            $sheet->getStyle("H{$r}:{$lastCol}{$r}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::YELLOW]]]);
            foreach (['H', 'K', $lastCol] as $rc) {
                $sheet->getStyle("{$rc}{$r}")->getFont()->getColor()->setRGB('FF0000');
            }
            $r++;
        }
    }

    private function finishStyles(Worksheet $sheet, int $first, int $lastData, string $last): void
    {
        $end = $lastData >= $first ? $lastData : ($first - 1);
        $highest = $sheet->getHighestRow();
        // Formato de moneda: H,J,K,L + días + TOTAL/MORA/OTROS/SALDOS
        $moneyCols = ['H', 'J', 'K', 'L'];
        for ($d = 1; $d <= self::DAYS; $d++) {
            $moneyCols[] = $this->colLetter(self::FIXED + $d);
        }
        for ($i = 1; $i <= 4; $i++) {
            $moneyCols[] = $this->colLetter(self::FIXED + self::DAYS + $i);
        }
        foreach ($moneyCols as $col) {
            $sheet->getStyle("{$col}3:{$col}{$highest}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        // Bordes punteados en cabecera + datos
        $sheet->getStyle("A2:{$last}{$highest}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
        if ($end >= $first) {
            $sheet->getStyle("A{$first}:E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
}
