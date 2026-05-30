<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del cronograma de un crédito (/credits/{id}/schedule).
 * Réplica de cliente_viewexcel2.php — título "REPORTE DE PAGO" + total.
 */
class ScheduleExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $totCap = 0;
    protected float $totInt = 0;
    protected float $totMora = 0;
    protected float $totPag = 0;

    public function __construct(protected int $creditId) {}

    public function collection()
    {
        $credit = Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota')])->find($this->creditId);
        if (!$credit) return collect();

        $rows = $credit->installments->map(function ($i) {
            $cap = (float) $i->importe_cuota;
            $int = (float) $i->importe_interes;
            $mora = (float) $i->importe_mora;
            $pag = (float) $i->importe_aplicado + (float) $i->interes_aplicado;

            $this->totCap += $cap;
            $this->totInt += $int;
            $this->totMora += $mora;
            $this->totPag += $pag;

            return (object) [
                'num'        => $i->num_cuota,
                'periodo'    => $i->fecha_vencimiento?->format('d/m/Y'),
                'capital'    => $cap,
                'interes'    => $int,
                'total'      => $cap + $int,
                'mora'       => $mora,
                'pagado'     => $pag,
                'fecha_pago' => $i->pagado ? $i->fecha_pago?->format('d/m/Y') : '',
            ];
        });

        return $rows;
    }

    public function headings(): array
    {
        return ['N° Cuota', 'Periodo', 'Capital', 'Interés', 'Total', 'Mora', 'Pagado', 'Fecha Pago'];
    }

    public function map($r): array
    {
        return [
            $r->num,
            $r->periodo,
            $r->capital,
            $r->interes,
            $r->total,
            $r->mora,
            $r->pagado,
            $r->fecha_pago,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'H';

                $r = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$r}", 'Total');
                $sheet->mergeCells("A{$r}:B{$r}");
                $sheet->setCellValue("C{$r}", number_format($this->totCap, 2));
                $sheet->setCellValue("D{$r}", number_format($this->totInt, 2));
                $sheet->setCellValue("E{$r}", number_format($this->totCap + $this->totInt, 2));
                $sheet->setCellValue("F{$r}", number_format($this->totMora, 2));
                $sheet->setCellValue("G{$r}", number_format($this->totPag, 2));

                $this->applyLegacyStyle($sheet, 'REPORTE DE PAGO - CRÉDITO #' . $this->creditId, $lastCol);
                $this->markTotalRow($sheet, $r, $lastCol);
            },
        ];
    }
}
