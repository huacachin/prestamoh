<?php

namespace App\Exports;

use App\Livewire\Reports\Advisor as AdvisorLivewire;
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
 * Excel del reporte /reports/advisor (colocación por asesor — día a día del mes).
 */
class AdvisorExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new AdvisorLivewire();
        $c->selemes  = (string) ($this->filters['selemes']  ?? str_pad((string) now()->month, 2, '0', STR_PAD_LEFT));
        $c->selecano = (string) ($this->filters['selecano'] ?? now()->year);
        $c->ejecutivo = (string) ($this->filters['ejecutivo'] ?? 'Todos');

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);

        // Append fila Total
        $tot = $data['tot'] ?? [];
        if (!empty($tot)) {
            $rows->push([
                'fecha' => 'TOTAL', 'nuevo' => $tot['nuevo'] ?? 0, 'renov' => $tot['renov'] ?? 0,
                'canc' => $tot['canc'] ?? 0, 'total' => $tot['total'] ?? 0,
                'capital' => $tot['capital'] ?? 0, 'imp_cobrar' => $tot['imp_cobrar'] ?? 0,
                'cob_cnt' => $tot['cob_cnt'] ?? 0, 'cob_imp' => $tot['cob_imp'] ?? 0,
                'noc_cnt' => $tot['noc_cnt'] ?? 0, 'noc_imp' => $tot['noc_imp'] ?? 0,
            ]);
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            'Fecha', 'Nuevo', 'Renov.', 'Cancel.', 'Total',
            'Capital', 'Imp. Cobrar', 'Cob. N°', 'Cob. Imp.', 'No Cob. N°', 'No Cob. Imp.',
        ];
    }

    public function map($r): array
    {
        return [
            $r['fecha']     ?? '',
            (int)   ($r['nuevo']      ?? 0),
            (int)   ($r['renov']      ?? 0),
            (int)   ($r['canc']       ?? 0),
            (int)   ($r['total']      ?? 0),
            (float) ($r['capital']    ?? 0),
            (float) ($r['imp_cobrar'] ?? 0),
            (int)   ($r['cob_cnt']    ?? 0),
            (float) ($r['cob_imp']    ?? 0),
            (int)   ($r['noc_cnt']    ?? 0),
            (float) ($r['noc_imp']    ?? 0),
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
                $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
