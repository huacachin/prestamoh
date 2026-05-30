<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Payments as PaymentsLivewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del reporte /reports/payments — título "REPORTE DE PAGO" + total.
 */
class PaymentsReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $total = 0;

    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new PaymentsLivewire();
        $c->tipo   = (string) ($this->filters['tipo']   ?? '1');
        $c->compra = (string) ($this->filters['compra'] ?? '');
        $c->fei    = (string) ($this->filters['fei']    ?? now()->format('Y-m-d'));
        $c->fef    = (string) ($this->filters['fef']    ?? now()->format('Y-m-d'));

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->total = (float) $rows->sum(fn ($r) => (float) ($r['monto'] ?? 0));

        return $rows;
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Hora', 'Usuario', 'Asesor', 'Cliente', 'Detalle', 'Monto', 'Moneda'];
    }

    public function map($r): array
    {
        return [
            $r['n']        ?? '',
            $r['fecha']    ?? '',
            $r['hora']     ?? '',
            $r['usuario']  ?? '',
            $r['asesor']   ?? '',
            $r['cliente']  ?? '',
            $r['detalle']  ?? '',
            (float) ($r['monto'] ?? 0),
            $r['moneda']   ?? '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'I';

                $totalRow = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$totalRow}", 'Total');
                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                $sheet->setCellValue("H{$totalRow}", number_format($this->total, 2));

                $this->applyLegacyStyle($sheet, 'REPORTE DE PAGO', $lastCol);
                $this->markTotalRow($sheet, $totalRow, $lastCol);
            },
        ];
    }
}
