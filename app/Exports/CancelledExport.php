<?php

namespace App\Exports;

use App\Livewire\Reports\Cancelled;
use Illuminate\Support\Collection;
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
 * Excel del reporte /reports/cancelled — reusa la lógica del componente Livewire
 * Reports\Cancelled instanciándolo, seteando filtros y leyendo $rows del render().
 */
class CancelledExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new Cancelled();
        $c->selemes  = $this->filters['selemes']  ?? date('m');
        $c->selecano = $this->filters['selecano'] ?? date('Y');
        $c->seletipl = (string) ($this->filters['seletipl'] ?? '');
        $c->exp      = (string) ($this->filters['exp']      ?? '');
        $c->codigo   = (string) ($this->filters['codigo']   ?? '');
        $c->cdni     = (string) ($this->filters['cdni']     ?? '');
        $c->cnombre  = (string) ($this->filters['cnombre']  ?? '');
        $c->casesor  = (string) ($this->filters['casesor']  ?? '');

        $data = $c->render()->getData();

        return collect($data['rows'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Nº', 'Exp.', 'Código', 'DNI', 'Cliente', 'Tipo',
            'Capital', 'R./Capital', 'Capital Neto', '%', 'Interés', 'Mora',
            'Total', 'Mora S/Día', 'M.x.D.', 'Días',
            'Fec.Crédito', 'Fec.Venc.', 'Fec.Cancel.', 'Asesor',
        ];
    }

    public function map($r): array
    {
        return [
            $r['n']           ?? '',
            $r['exp']         ?? '',
            $r['codigo']      ?? '',
            $r['dni']         ?? '',
            $r['nombre']      ?? '',
            strip_tags((string) ($r['detalles'] ?? '')),
            (float) ($r['capital']      ?? 0),
            (float) ($r['r_capital']    ?? 0),
            (float) ($r['capital_neto'] ?? 0),
            (float) ($r['interes_pct']  ?? 0),
            (float) ($r['interes_s']    ?? 0),
            (float) ($r['mora']         ?? 0),
            (float) ($r['total']        ?? 0),
            (float) ($r['mora_s']       ?? 0),
            (float) ($r['mxd']          ?? 0),
            (int)   ($r['dias']         ?? 0),
            $r['fec_cred']    ?? '',
            $r['fec_venc']    ?? '',
            $r['fec_cancel']  ?? '',
            $r['asesor']      ?? '',
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
                $sheet->getStyle('A1:T1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:T1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
