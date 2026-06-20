<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Concept;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del catálogo de conceptos — réplica de conceptoex.php. Título "CONCEPTOS FIJOS".
 */
class ConceptsExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
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
        // Réplica exacta de conceptoex.php: Nº, Codigo, Nombre, Tipo, ING. S/, EGR. S/
        return ['Nº', 'Código', 'Nombre', 'Tipo', 'ING. S/', 'EGR. S/'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $c->code,
            $c->name,
            strtoupper($c->type) === 'INGRESO' || $c->type === 'ingreso' ? 'INGRESO' : 'EGRESO',
            $c->factor_ingreso,
            $c->factor_egreso,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                // Centrado + moneda en datos (ING. S/ col E, EGR. S/ col F).
                $this->styleDataRange($sheet, 'F', ['E', 'F']);
                $this->applyLegacyStyle($sheet, 'CONCEPTOS FIJOS', 'F');
            },
        ];
    }
}
