<?php

namespace App\Exports;

use App\Livewire\Reports\CashGeneral2 as CashGeneral2Livewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del /reports/cash-general-2 (balance diario por asesor).
 * Aplana la estructura por día/items en filas planas para Excel.
 */
class CashGeneral2Export implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
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

        // Aplanar: una fila Excel por cada item de cada día + 1 fila de subtotal por día
        $flat = collect();
        foreach ($report['days'] as $day) {
            foreach ($day['items'] as $item) {
                $flat->push([
                    'type'     => 'item',
                    'date'     => $day['date'],
                    'cliente'  => $item['cliente'] ?? '',
                    'detalle'  => $item['detalle'] ?? '',
                    'ingreso'  => (float) ($item['ingreso'] ?? 0),
                    'egreso'   => (float) ($item['egreso'] ?? 0),
                    'saldo'    => '',
                ]);
            }
            $flat->push([
                'type'     => 'subtotal',
                'date'     => $day['date'],
                'cliente'  => 'SUBTOTAL DEL DÍA',
                'detalle'  => $day['date_label'] ?? '',
                'ingreso'  => (float) $day['total_ingreso'],
                'egreso'   => (float) $day['total_egreso'],
                'saldo'    => (float) $day['saldo'],
            ]);
        }
        $flat->push([
            'type'    => 'total',
            'date'    => '',
            'cliente' => 'BALANCE GENERAL',
            'detalle' => '',
            'ingreso' => '',
            'egreso'  => '',
            'saldo'   => (float) $report['balance_general'],
        ]);

        return $flat;
    }

    public function headings(): array
    {
        return ['Fecha', 'Cliente/Usuario', 'Detalle', 'Ingreso', 'Egreso', 'Saldo'];
    }

    public function map($r): array
    {
        return [
            $r['date']    ?? '',
            $r['cliente'] ?? '',
            $r['detalle'] ?? '',
            $r['ingreso'],
            $r['egreso'],
            $r['saldo'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
