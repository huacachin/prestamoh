<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\CashGeneral2 as CashGeneral2Livewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del /reports/cash-general-2 (balance diario). Título "BALANCE DIARIO".
 * Marca filas SUBTOTAL/BALANCE en celeste.
 */
class CashGeneral2Export implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    /** @var int[] filas (1-based en la hoja) que son subtotal/total */
    protected array $totalRows = [];

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
        foreach ($report['days'] as $day) {
            foreach ($day['items'] as $item) {
                $rowNum++;
                $flat->push([
                    'date'     => $day['date'],
                    'cliente'  => $item['cliente'] ?? '',
                    'detalle'  => $item['detalle'] ?? '',
                    'ingreso'  => (float) ($item['ingreso'] ?? 0),
                    'egreso'   => (float) ($item['egreso'] ?? 0),
                    'saldo'    => '',
                ]);
            }
            $rowNum++;
            $this->totalRows[] = $rowNum;
            $flat->push([
                'date'     => $day['date'],
                'cliente'  => 'SUBTOTAL DEL DÍA',
                'detalle'  => $day['date_label'] ?? '',
                'ingreso'  => (float) $day['total_ingreso'],
                'egreso'   => (float) $day['total_egreso'],
                'saldo'    => (float) $day['saldo'],
            ]);
        }
        $rowNum++;
        $this->totalRows[] = $rowNum;
        $flat->push([
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->applyLegacyStyle($sheet, 'BALANCE DIARIO', 'F');
                foreach ($this->totalRows as $row) {
                    $this->markTotalRow($sheet, $row, 'F');
                }
            },
        ];
    }
}
