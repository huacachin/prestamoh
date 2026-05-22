<?php

namespace App\Exports;

use App\Livewire\Reports\Payments as PaymentsLivewire;
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
 * Excel del reporte /reports/payments — reusa la lógica del componente Livewire.
 */
class PaymentsReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
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
        return collect($data['rows'] ?? []);
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

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
