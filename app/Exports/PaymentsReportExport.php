<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Payments as PaymentsLivewire;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Excel del reporte /reports/payments — título "REPORTE DE PAGO" + total.
 */
class PaymentsReportExport implements FromCollection, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use LegacyExcelStyle;

    protected array $totals = ['total' => 0, 'fijos' => 0, 'otros' => 0, 'capital' => 0, 'interes' => 0, 'mora' => 0];

    public function __construct(
        protected array $filters = [],
    ) {}

    public function collection()
    {
        $c = new PaymentsLivewire;
        $c->tipo = (string) ($this->filters['tipo'] ?? '1');
        $c->compra = (string) ($this->filters['compra'] ?? '');
        $c->fei = (string) ($this->filters['fei'] ?? now()->format('Y-m-d'));
        $c->fef = (string) ($this->filters['fef'] ?? now()->format('Y-m-d'));

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->totals = array_merge($this->totals, $data['totals'] ?? []);

        return $rows;
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Hora', 'Usuario', 'Asesor', 'Cliente', 'Detalle', 'Monto', 'Moneda'];
    }

    public function map($r): array
    {
        return [
            $r['n'] ?? '',
            $r['fecha'] ?? '',
            $r['hora'] ?? '',
            $r['usuario'] ?? '',
            $r['asesor'] ?? '',
            $r['cliente'] ?? '',
            $r['detalle'] ?? '',
            (float) ($r['monto'] ?? 0),
            $r['moneda'] ?? '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'I';

                // Centrado + moneda en datos (Monto col H), antes del bloque de totales.
                $this->styleDataRange($sheet, $lastCol, ['H']);

                // ── Bloque de totales (6 filas, réplica de ingreso_excel2.php) ──
                // "Total" combinado A:E (rowspan 6) + Total General en col H.
                // Desglose Fijos/Otros/Capital/Interés/Mora en F (label) y G (valor).
                $r1 = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$r1}", 'Total');
                $sheet->mergeCells("A{$r1}:E".($r1 + 5));
                $sheet->getStyle("A{$r1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->setCellValue("H{$r1}", number_format($this->totals['total'], 2));

                $desglose = [
                    ['Fijos',   $this->totals['fijos'],   false],
                    ['Otros',   $this->totals['otros'],   true],  // label rojo
                    ['Capital', $this->totals['capital'], false],
                    ['Interes', $this->totals['interes'], false],
                    ['Mora',    $this->totals['mora'],    false],
                ];
                $r = $r1;
                foreach ($desglose as $d) {
                    $r++;
                    $sheet->setCellValue("F{$r}", $d[0]);
                    $sheet->setCellValue("G{$r}", number_format($d[1], 2));
                    if ($d[2]) {
                        $sheet->getStyle("F{$r}")->getFont()->getColor()->setRGB('FF0000');
                    }
                }

                $this->applyLegacyStyle($sheet, 'REPORTE DE PAGO', $lastCol);
                for ($x = $r1; $x <= $r1 + 5; $x++) {
                    $this->markTotalRow($sheet, $x, $lastCol);
                }
            },
        ];
    }
}
