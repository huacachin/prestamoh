<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Cancelled;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del reporte /reports/cancelled. Título "RESUMEN DE CANCELADOS".
 * Totales de Capital / R.Capital / Capital Neto / Interés / Mora / Total.
 */
class CancelledExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected array $t = ['cap' => 0, 'rcap' => 0, 'neto' => 0, 'int' => 0, 'mora' => 0, 'total' => 0];

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
        $rows = collect($data['rows'] ?? []);

        foreach ($rows as $r) {
            $this->t['cap']   += (float) ($r['capital']      ?? 0);
            $this->t['rcap']  += (float) ($r['r_capital']    ?? 0);
            $this->t['neto']  += (float) ($r['capital_neto'] ?? 0);
            $this->t['int']   += (float) ($r['interes_s']    ?? 0);
            $this->t['mora']  += (float) ($r['mora']         ?? 0);
            $this->t['total'] += (float) ($r['total']        ?? 0);
        }

        return $rows;
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'T';

                $row = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$row}", 'Totales');
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("G{$row}", number_format($this->t['cap'], 2));
                $sheet->setCellValue("H{$row}", number_format($this->t['rcap'], 2));
                $sheet->setCellValue("I{$row}", number_format($this->t['neto'], 2));
                $sheet->setCellValue("K{$row}", number_format($this->t['int'], 2));
                $sheet->setCellValue("L{$row}", number_format($this->t['mora'], 2));
                $sheet->setCellValue("M{$row}", number_format($this->t['total'], 2));

                $this->applyLegacyStyle($sheet, 'RESUMEN DE CANCELADOS', $lastCol);
                $this->markTotalRow($sheet, $row, $lastCol);
            },
        ];
    }
}
