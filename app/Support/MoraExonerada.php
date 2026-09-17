<?php

namespace App\Support;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mora exonerada teórica por cuota: días CALENDARIO de atraso entre el
 * vencimiento y la fecha de pago real (todos los tipos, regla única desde
 * 02/09) × mora diaria (5% de la cuota ÷7 semanal / ÷30 mensual;
 * diarios con su mora1 histórico — ver Credit::moraDiaria()), menos la mora
 * que ESA cuota pagó. La fecha de pago real se reconstruye con FIFO porque
 * installments.fecha_pago guarda el vencimiento, no el día del pago.
 *
 * 17/09: lo que se resta es la PARTE de la cuota en el reparto del cobro
 * (MoraPagada::mostradaPorCuota), no la mora del día colgada por FIFO en la
 * primera cuota tocada. Con lo de antes, en el 28957 la cuota 19 salía
 * "pagó 163,17" en la celda y "exonerada 163,17" en rojo debajo: la misma
 * cuota pagada y perdonada por los mismos días. Ahora, si la cuota pagó su
 * mora teórica completa, la exonerada es 0.
 *
 * Misma lógica que el cronograma público (App\Livewire\Credits\Schedule);
 * si se ajusta una, ajustar la otra.
 */
class MoraExonerada
{
    /**
     * @param  array|null  $moraCelda  MoraPagada::mostradaPorCuota() ya calculado, para no repetir consultas.
     * @return array<int, array{monto: float, dias: int}> keyed por num_cuota (solo cuotas con exoneración)
     */
    public static function porCuota(Credit $credit, ?array $moraCelda = null): array
    {
        $installments = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get(['id', 'num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes'])
            ->values();

        if ($installments->isEmpty()) {
            return [];
        }

        $moraCelda ??= MoraPagada::mostradaPorCuota($credit);

        // Solo los pagos de capital/interés arman los eventos del FIFO; la
        // mora ya no se cuelga por fecha, se toma del reparto por cuota.
        $pays = DB::table('payments')
            ->where('credit_id', $credit->id)
            ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
            ->select('fecha', 'hora', 'monto', 'documento')
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get();

        $eventos = [];
        foreach ($pays as $p) {
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA') {
                continue;
            }
            $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : '';
            $last = count($eventos) - 1;
            if ($last >= 0 && $eventos[$last]['fecha'] === $f && $eventos[$last]['hora'] === $p->hora) {
                $eventos[$last]['monto'] += (float) $p->monto;
            } else {
                $eventos[] = ['fecha' => $f, 'hora' => $p->hora, 'monto' => (float) $p->monto];
            }
        }

        // FIFO → fecha de pago real por cuota
        $alloc = [];
        $n = $installments->count();
        $idx = 0;
        $capacidad = (float) $installments[0]->importe_cuota + (float) $installments[0]->importe_interes;

        foreach ($eventos as $p) {
            $rem = $p['monto'];
            while ($rem > 0.005 && $idx < $n) {
                $take = min($rem, $capacidad);
                if ($take > 0) {
                    $alloc[$idx]['monto'] = ($alloc[$idx]['monto'] ?? 0) + $take;
                    $alloc[$idx]['fecha'] = $p['fecha'];
                    $rem -= $take;
                    $capacidad -= $take;
                }
                if ($capacidad <= 0.005) {
                    $idx++;
                    $capacidad = $idx < $n
                        ? (float) $installments[$idx]->importe_cuota + (float) $installments[$idx]->importe_interes
                        : 0.0;
                }
            }
            if ($idx >= $n) {
                break; // sobrante = pago "OTROS", sin cuota que exonerar
            }
        }

        $out = [];
        foreach ($installments as $k => $ins) {
            $a = $alloc[$k] ?? null;
            $fechaPago = ($a && round($a['monto'], 2) >= 0.01) ? ($a['fecha'] ?? '') : '';

            if ($fechaPago === '' || ! $ins->fecha_vencimiento) {
                continue;
            }

            $venc = Carbon::parse($ins->fecha_vencimiento);
            $dias = (int) floor($venc->diffInDays(Carbon::parse($fechaPago), false));
            if ($dias <= 0) {
                continue;
            }

            $rate = $credit->moraDiaria((float) $ins->importe_cuota + (float) $ins->importe_interes);
            $pagada = (float) ($moraCelda[(int) $ins->id]['monto'] ?? 0);
            $monto = round(max(0, $dias * $rate - $pagada), 2);
            if ($monto > 0) {
                $out[(int) $ins->num_cuota] = ['monto' => $monto, 'dias' => $dias];
            }
        }

        return $out;
    }
}
