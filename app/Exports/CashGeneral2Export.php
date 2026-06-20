<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\CashGeneral2 as CashGeneral2Livewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del /reports/cash-general-2 — réplica de reporte4.php.
 * Título "REPORTE GENERAL CAJA 2". Cols: Nº, Fecha, DATOS DEL CLIENTE, DETALLES, INGRESO, EGRESO.
 * Ingresos en azul, egresos en rojo. Por día: filas "TOTAL" y "SALDO FINAL-INICIAL".
 * Fila final "REPORTE GENERAL CAJA 2 - TOTAL GENERAL" combinada (A:D / E:F).
 */
class CashGeneral2Export implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    private const COLOR_INGRESO = '0000FF'; // azul
    private const COLOR_EGRESO  = 'FF0000'; // rojo

    /** @var int[] filas subtotal/total (celeste) */
    protected array $totalRows = [];
    /** @var int[] filas donde la col E (INGRESO) va en azul */
    protected array $ingresoRows = [];
    /** @var int[] filas donde la col F (EGRESO) va en rojo */
    protected array $egresoRows = [];
    /** fila final TOTAL GENERAL (merges A:D y E:F) */
    protected ?int $finalRow = null;

    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new CashGeneral2Livewire();
        $c->month = (int) ($this->filters['month'] ?? now()->month);
        $c->year  = (int) ($this->filters['year']  ?? now()->year);

        $data = $c->render()->getData();
        $report = $data['report'] ?? ['days' => [], 'balance_general' => 0];

        $flat = collect();
        $rowNum = 2; // fila 1 = título; headers fila 2; datos desde fila 3
        $n = 0;
        foreach ($report['days'] as $day) {
            foreach ($day['items'] as $item) {
                $rowNum++; $n++;
                $this->ingresoRows[] = $rowNum;
                $this->egresoRows[]  = $rowNum;
                $flat->push([
                    $n,
                    $day['date'],
                    $item['cliente'] ?? '',
                    $item['detalle'] ?? '',
                    (float) ($item['ingreso'] ?? 0),
                    (float) ($item['egreso'] ?? 0),
                ]);
            }
            // Fila TOTAL del día
            $rowNum++;
            $this->totalRows[]   = $rowNum;
            $this->ingresoRows[] = $rowNum;
            $this->egresoRows[]  = $rowNum;
            $flat->push(['', '', '', 'TOTAL', (float) $day['total_ingreso'], (float) $day['total_egreso']]);
            // Fila SALDO FINAL-INICIAL
            $rowNum++;
            $this->totalRows[]   = $rowNum;
            $this->ingresoRows[] = $rowNum;
            $flat->push(['', '', '', 'SALDO FINAL-INICIAL', (float) $day['saldo'], '']);
        }
        // Fila TOTAL GENERAL (label en A para que el merge A:D la muestre)
        $rowNum++;
        $this->totalRows[]   = $rowNum;
        $this->ingresoRows[] = $rowNum;
        $this->finalRow      = $rowNum;
        $flat->push(['REPORTE GENERAL CAJA 2 - TOTAL GENERAL', '', '', '', (float) $report['balance_general'], '']);

        return $flat;
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'DATOS DEL CLIENTE', 'DETALLES', 'INGRESO', 'EGRESO'];
    }

    public function map($r): array
    {
        return array_values($r);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'F';

                foreach ($this->totalRows as $row) {
                    $this->markTotalRow($sheet, $row, $lastCol);
                }
                foreach ($this->ingresoRows as $row) {
                    $sheet->getStyle("E{$row}")->getFont()->getColor()->setRGB(self::COLOR_INGRESO);
                }
                foreach ($this->egresoRows as $row) {
                    $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB(self::COLOR_EGRESO);
                }

                if ($this->finalRow) {
                    $sheet->mergeCells("A{$this->finalRow}:D{$this->finalRow}");
                    $sheet->mergeCells("E{$this->finalRow}:F{$this->finalRow}");
                    $sheet->getStyle("A{$this->finalRow}:F{$this->finalRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }

                // Formato moneda (E=INGRESO, F=EGRESO) + alineación centrada en datos
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$highestRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    foreach (['E', 'F'] as $col) {
                        $sheet->getStyle("{$col}3:{$col}{$highestRow}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                $this->applyLegacyStyle($sheet, 'REPORTE GENERAL CAJA 2', $lastCol);
            },
        ];
    }
}
