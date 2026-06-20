<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del cronograma de un crédito (/credits/{id}/schedule) — réplica de cliente_viewexcel2.php.
 * Título "REPORTE DE PAGO". Domingos en rojo / sábados en verde. Total (2 filas).
 * Créditos diarios (tipoplani=4): filas extra de pagos antes/después del cronograma + Totales.
 */
class ScheduleExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $totCap = 0;
    protected float $totInt = 0;
    protected float $totMora = 0;
    protected float $totPag = 0;
    protected int $rowCount = 0;
    protected ?Credit $credit = null;
    /** @var array<int,string> sheetRow => color fin de semana */
    protected array $rowColors = [];

    public function __construct(protected int $creditId) {}

    public function collection()
    {
        $this->credit = Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota')])->find($this->creditId);
        if (!$this->credit) return collect();

        $installments = $this->credit->installments;
        $this->rowCount = $installments->count();

        $rows = $installments->values()->map(function ($i, $idx) {
            $cap  = (float) $i->importe_cuota;
            $int  = (float) $i->importe_interes;
            $mora = (float) $i->importe_mora;
            $pag  = (float) $i->importe_aplicado + (float) $i->interes_aplicado;

            $this->totCap  += $cap;
            $this->totInt  += $int;
            $this->totMora += $mora;
            $this->totPag  += $pag;

            // Color fin de semana según periodo (fecha_vencimiento)
            if ($i->fecha_vencimiento) {
                $dow = $i->fecha_vencimiento->dayOfWeek;
                if ($dow === Carbon::SUNDAY)        $this->rowColors[3 + $idx] = 'FF0000';
                elseif ($dow === Carbon::SATURDAY)  $this->rowColors[3 + $idx] = '008000';
            }

            return (object) [
                'num'        => $i->num_cuota,
                'periodo'    => $i->fecha_vencimiento?->format('d/m/Y'),
                'capital'    => $cap,
                'interes'    => $int,
                'total'      => $cap + $int,
                'mora'       => $mora,
                'pagado'     => $pag,
                'fecha_pago' => $i->pagado ? $i->fecha_pago?->format('d/m/Y') : '',
            ];
        });

        return $rows;
    }

    public function headings(): array
    {
        return ['N° Cuota', 'Periodo', 'Capital', 'Interés', 'Total', 'Mora', 'Pagado', 'Fecha Pago'];
    }

    public function map($r): array
    {
        return [$r->num, $r->periodo, $r->capital, $r->interes, $r->total, $r->mora, $r->pagado, $r->fecha_pago];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'H';

                // Formato moneda + alineación centrada en el cronograma (datos desde fila 3)
                if ($this->rowCount > 0) {
                    $first = 3;
                    $last  = 2 + $this->rowCount;
                    $sheet->getStyle("A{$first}:{$lastCol}{$last}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                // Colores fin de semana (toda la fila)
                foreach ($this->rowColors as $row => $rgb) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->getColor()->setRGB($rgb);
                }

                // ── Total (2 filas: legacy "Total" rowspan2 + fila combinada) ──
                $r = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$r}", 'Total');
                $sheet->mergeCells("A{$r}:B{$r}");
                $sheet->setCellValue("C{$r}", number_format($this->totCap, 2));
                $sheet->setCellValue("D{$r}", number_format($this->totInt, 2));
                $sheet->setCellValue("E{$r}", number_format($this->totCap + $this->totInt, 2));
                $sheet->setCellValue("F{$r}", number_format($this->totMora, 2));
                $sheet->setCellValue("G{$r}", number_format($this->totPag, 2));
                $this->markTotalRow($sheet, $r, $lastCol);

                $r2 = $r + 1;
                $sheet->mergeCells("A{$r2}:B{$r2}");
                $sheet->setCellValue("A{$r2}", number_format($this->totMora + $this->totPag, 2));
                $this->markTotalRow($sheet, $r2, $lastCol);

                // ── Créditos diarios: pagos antes/después del cronograma ──
                if ($this->credit && (int) $this->credit->tipo_planilla === 4) {
                    $this->buildDailyExtras($sheet, $r2 + 1, $lastCol);
                }

                $this->applyBaseFontAndAutosize($sheet);
            },
        ];
    }

    private function buildDailyExtras($sheet, int $startRow, string $lastCol): void
    {
        $inst = $this->credit->installments;
        $minFec = $inst->min(fn ($i) => $i->fecha_vencimiento?->format('Y-m-d'));
        $maxFec = $inst->max(fn ($i) => $i->fecha_vencimiento?->format('Y-m-d'));
        if (!$minFec || !$maxFec) return;

        $base = fn () => DB::table('payments')
            ->where('credit_id', $this->creditId)
            ->where('documento', 'NOT LIKE', 'MORA%')
            ->where(function ($q) { $q->whereNull('detalle')->orWhere('detalle', 'NOT LIKE', '%Gat'); });

        $before = (clone $base())->where('fecha', '<', $minFec)
            ->selectRaw('fecha, SUM(monto) tgm')->groupBy('fecha')->orderBy('fecha')->get();
        $after = (clone $base())->where('fecha', '>', $maxFec)
            ->selectRaw('fecha, SUM(monto) tgm')->groupBy('fecha')->orderBy('fecha')->get();

        $moraEnFecha = function ($fecha) {
            return (float) DB::table('payments')
                ->where('credit_id', $this->creditId)
                ->where('documento', 'LIKE', 'MORA%')
                ->whereDate('fecha', $fecha)
                ->sum('monto');
        };

        $r = $startRow;
        $tt = $this->rowCount;
        $sumImpo = 0; $sumMora = 0;
        foreach ($before->concat($after) as $p) {
            $tt++;
            $mora = $moraEnFecha($p->fecha);
            $sheet->setCellValue("A{$r}", $tt);
            $sheet->setCellValue("C{$r}", '0.00');
            $sheet->setCellValue("D{$r}", '0.00');
            $sheet->setCellValue("E{$r}", '0.00');
            $sheet->setCellValue("F{$r}", number_format($mora, 2));
            $sheet->setCellValue("G{$r}", number_format((float) $p->tgm, 2));
            $sheet->setCellValue("H{$r}", Carbon::parse($p->fecha)->format('d/m/Y'));
            $sumImpo += (float) $p->tgm;
            $sumMora += $mora;
            $r++;
        }

        if ($r > $startRow) {
            // Bordes para las filas extra
            $sheet->getStyle("A{$startRow}:{$lastCol}" . ($r - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
            ]);
            // Totales finales
            $sheet->mergeCells("A{$r}:E{$r}");
            $sheet->setCellValue("A{$r}", 'Totales');
            $sheet->setCellValue("F{$r}", number_format($sumMora, 2));
            $sheet->setCellValue("G{$r}", number_format($sumImpo, 2));
            $this->markTotalRow($sheet, $r, $lastCol);
        }
    }
}
