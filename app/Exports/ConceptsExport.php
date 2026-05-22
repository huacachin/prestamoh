<?php

namespace App\Exports;

use App\Models\Concept;
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
 * Excel del catálogo de conceptos — réplica de conceptoex.php.
 */
class ConceptsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected string $tipo = '2',     // 1=Código, 2=Nombre
        protected string $compra = '',
        protected string $estados = 'Activo',
    ) {}

    public function collection()
    {
        $query = Concept::query();

        if (trim($this->compra) !== '') {
            $t = trim($this->compra);
            if ($this->tipo === '1') {
                $query->where('code', 'like', "%{$t}%");
            } else {
                $query->where('name', 'like', "%{$t}%");
            }
        }

        $query->where('status', $this->estados === 'Cesado' ? 'inactive' : 'active');

        return $query->orderBy('code', 'asc')->get();
    }

    public function headings(): array
    {
        return ['Nº', 'Código', 'Nombre', 'Tipo', 'Estado'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $c->code,
            $c->name,
            $c->type,
            $c->status === 'active' ? 'Activo' : 'Cesado',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
