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
 * Excel del simulador de crédito (/reports/simulator) — réplica de simula_excel.php.
 * Título "SIMULACION DE CREDITO" + info (Nombre/Importe/Interés) y 10 bloques
 * horizontales: INTERES MENSUAL (Mes 1-12, 13-24, ..., 49-60) e INTERES SEMANAL.
 * Cada bloque: Monto + 12 meses (Pagar / Mora en rojo). Cabecera celeste #5BC0DE.
 */
class SimulatorExport implements FromCollection, WithEvents
{
    use LegacyExcelStyle;

    private const COLOR_CELESTE = '5BC0DE';
    private const COLOR_MORA = 'FF0000';

    public function __construct(
        protected float $capital = 0,
        protected float $interes = 0,
        protected string $nombre = '',
        protected int $meses = 60,
    ) {}

    /** La hoja se construye íntegramente en AfterSheet. */
    public function collection()
    {
        return collect();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = 'Z'; // 26 columnas: Monto(2) + 12 meses × 2

                // ── Título ──
                $sheet->setCellValue('A1', 'SIMULACION DE CREDITO');
                $sheet->mergeCells("A1:{$last}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);

                // ── Info: Nombre / Importe / Interés ──
                $sheet->setCellValue('A2', 'Nombre :');
                $sheet->setCellValue('B2', $this->nombre);
                $sheet->setCellValue('J2', 'Importe :');
                $sheet->setCellValue('K2', number_format($this->capital, 2));
                $sheet->setCellValue('S2', 'Interés :');
                $sheet->setCellValue('T2', rtrim(rtrim(number_format($this->interes, 2), '0'), '.') . '%');
                $sheet->getStyle('A2:T2')->getFont()->setBold(true);

                $r = 4;
                $this->sectionTitle($sheet, $r, 'INTERES MENSUAL', $last);
                $r++;
                foreach ([1, 13, 25, 37, 49] as $start) {
                    $this->block($sheet, $r, $start, false);
                }

                $this->sectionTitle($sheet, $r, 'INTERES SEMANAL', $last);
                $r++;
                foreach ([1, 13, 25, 37, 49] as $start) {
                    $this->block($sheet, $r, $start, true);
                }

                foreach (range('A', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(10);
                }
            },
        ];
    }

    private function sectionTitle(Worksheet $sheet, int &$r, string $text, string $last): void
    {
        $sheet->mergeCells("A{$r}:{$last}{$r}");
        $sheet->setCellValue("A{$r}", $text);
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $r++;
    }

    /** Un bloque de 12 meses (3 filas: cabecera mes, Pagar/Mora, valores). */
    private function block(Worksheet $sheet, int &$r, int $startMonth, bool $weekly): void
    {
        $top = $r;

        // Fila 1: Monto + Mes N
        $sheet->mergeCells("A{$r}:B{$r}");
        $sheet->setCellValue("A{$r}", 'Monto');
        for ($i = 1; $i <= 12; $i++) {
            $m = $startMonth + $i - 1;
            [$c1, $c2] = $this->monthCols($i);
            $sheet->mergeCells("{$c1}{$r}:{$c2}{$r}");
            $sheet->setCellValue("{$c1}{$r}", "Mes {$m}");
        }
        $sheet->getStyle("A{$r}:Z{$r}")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CELESTE]],
        ]);
        $r++;

        // Fila 2: Pagar / Mora
        for ($i = 1; $i <= 12; $i++) {
            [$c1, $c2] = $this->monthCols($i);
            $sheet->setCellValue("{$c1}{$r}", 'Pagar');
            $sheet->setCellValue("{$c2}{$r}", 'Mora');
            $sheet->getStyle("{$c2}{$r}")->getFont()->getColor()->setRGB(self::COLOR_MORA);
        }
        $sheet->getStyle("A{$r}:Z{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $r++;

        // Fila 3: Monto + valores Pagar/Mora
        $sheet->mergeCells("A{$r}:B{$r}");
        $sheet->setCellValue("A{$r}", number_format($this->capital, 2));
        for ($i = 1; $i <= 12; $i++) {
            $m = $startMonth + $i - 1;
            if ($weekly) {
                $cuo = $m * 4;
                $pag = $cuo > 0 ? $this->capital / $cuo + ($this->capital * $this->interes) / 100 / 4 : 0;
            } else {
                $pag = $m > 0 ? $this->capital / $m + ($this->capital * $this->interes) / 100 : 0;
            }
            $mora = $pag * $this->interes / 100 / 30 * 2;
            [$c1, $c2] = $this->monthCols($i);
            $sheet->setCellValue("{$c1}{$r}", number_format($pag, 2));
            $sheet->setCellValue("{$c2}{$r}", number_format($mora, 2));
            $sheet->getStyle("{$c2}{$r}")->getFont()->getColor()->setRGB(self::COLOR_MORA);
        }
        $sheet->getStyle("A{$r}:Z{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Bordes del bloque
        $sheet->getStyle("A{$top}:Z{$r}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        ]);

        $r += 2; // fila siguiente + una en blanco entre bloques
    }

    /** Columnas (Pagar, Mora) para el mes i (1..12). Monto ocupa A,B. */
    private function monthCols(int $i): array
    {
        return [$this->colLetter(3 + ($i - 1) * 2), $this->colLetter(4 + ($i - 1) * 2)];
    }
}
