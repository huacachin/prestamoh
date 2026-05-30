<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Concept;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del catálogo de conceptos — réplica de conceptoex.php. Título "CONCEPTOS FIJOS".
 */
class ConceptsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    public function __construct(
        protected string $tipo = '2',
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applyLegacyStyle($event->sheet->getDelegate(), 'CONCEPTOS FIJOS', 'E');
            },
        ];
    }
}
