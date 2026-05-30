<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del simulador de crédito (/reports/simulator).
 * Réplica de simula_excel.php — título "SIMULACION DE CREDITO".
 * Versión vertical (más usable en Excel): una fila por mes con Pagar/Mora
 * mensual y semanal, hasta 60 meses.
 */
class SimulatorExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    public function __construct(
        protected float $capital = 0,
        protected float $interes = 0,
        protected string $nombre = '',
        protected int $meses = 60,
    ) {}

    public function collection()
    {
        $rows = collect();
        $cap = $this->capital;
        $int = $this->interes;
        $intMensual = ($cap * $int) / 100;
        $intSemanal = ($cap * $int) / 100 / 4;

        for ($m = 1; $m <= $this->meses; $m++) {
            // Mensual: capital/meses + interés mensual
            $pagMen = $cap / $m + $intMensual;
            $moraMen = $pagMen * $int / 100 / 30 * 2;
            // Semanal: capital/(meses*4) + interés semanal
            $cuoSem = $m * 4;
            $pagSem = $cap / $cuoSem + $intSemanal;
            $moraSem = $pagSem * $int / 100 / 30 * 2;

            $rows->push((object) [
                'mes'      => $m,
                'pag_men'  => round($pagMen, 2),
                'mora_men' => round($moraMen, 2),
                'pag_sem'  => round($pagSem, 2),
                'mora_sem' => round($moraSem, 2),
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Mes', 'Pagar (Mensual)', 'Mora (Mensual)', 'Pagar (Semanal)', 'Mora (Semanal)'];
    }

    public function map($r): array
    {
        return [$r->mes, $r->pag_men, $r->mora_men, $r->pag_sem, $r->mora_sem];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'E';

                // Sub-cabecera con los datos del simulacro (fila 2 ya tendrá headers,
                // así que insertamos info en una fila extra arriba del todo moviendo todo
                // no es trivial; en su lugar lo ponemos en el título).
                $titulo = sprintf(
                    'SIMULACION DE CREDITO%s · Importe: %s · Interés: %s%%',
                    $this->nombre !== '' ? ' - ' . $this->nombre : '',
                    number_format($this->capital, 2),
                    rtrim(rtrim(number_format($this->interes, 2), '0'), '.')
                );

                $this->applyLegacyStyle($sheet, $titulo, $lastCol);

                // Columnas de mora en rojo (legacy)
                $highest = $sheet->getHighestRow();
                $sheet->getStyle("C3:C{$highest}")->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle("E3:E{$highest}")->getFont()->getColor()->setRGB('FF0000');
            },
        ];
    }
}
