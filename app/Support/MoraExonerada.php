<?php

namespace App\Support;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mora exonerada teórica por cuota: días de atraso entre el vencimiento y la
 * fecha de pago real × mora diaria (5% de la cuota ÷7 semanal / ÷30 mensual;
 * diarios con su mora1 histórico — ver Credit::moraDiaria()), menos la mora
 * cobrada en la cuota. La fecha de pago real se reconstruye con FIFO porque
 * installments.fecha_pago guarda el vencimiento, no el día del pago.
 *
 * Misma lógica que el cronograma público (App\Livewire\Credits\Schedule);
 * si se ajusta una, ajustar la otra.
 */
class MoraExonerada
{
    /** @return array<int, array{monto: float, dias: int}> keyed por num_cuota (solo cuotas con exoneración) */
    public static function porCuota(Credit $credit): array
    {
        $tipoPlanilla = (int) $credit->tipo_planilla;

        $installments = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes'])
            ->values();

        if ($installments->isEmpty()) {
            return [];
        }

        $pays = DB::table('payments')
            ->where('credit_id', $credit->id)
            ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
            ->select('fecha', 'hora', 'monto', 'documento')
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get();

        // Eventos de pago (fecha+hora) y mora cobrada por fecha
        $eventos = [];
        $payMora = [];
        foreach ($pays as $p) {
            $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : '';
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA') {
                if ($f) {
                    $payMora[$f] = ($payMora[$f] ?? 0) + (float) $p->monto;
                }
            } else {
                $last = count($eventos) - 1;
                if ($last >= 0 && $eventos[$last]['fecha'] === $f && $eventos[$last]['hora'] === $p->hora) {
                    $eventos[$last]['monto'] += (float) $p->monto;
                } else {
                    $eventos[] = ['fecha' => $f, 'hora' => $p->hora, 'monto' => (float) $p->monto];
                }
            }
        }

        // FIFO → fecha de pago real y fechas de pago asociadas por cuota
        $alloc = [];
        $moraCuota = [];
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
                    if ($p['fecha']) {
                        $moraCuota[$idx][$p['fecha']] = true;
                    }
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
        $moraUsada = [];
        foreach ($installments as $k => $ins) {
            $a = $alloc[$k] ?? null;
            $fechaPago = ($a && round($a['monto'], 2) >= 0.01) ? ($a['fecha'] ?? '') : '';

            // Mora cobrada en los días en que esta cuota recibió pagos (una sola vez)
            $mora = 0.0;
            foreach (array_keys($moraCuota[$k] ?? []) as $f) {
                if (! isset($moraUsada[$f]) && isset($payMora[$f])) {
                    $mora += (float) $payMora[$f];
                    $moraUsada[$f] = true;
                }
            }

            if ($fechaPago === '' || ! $ins->fecha_vencimiento) {
                continue;
            }

            $venc = Carbon::parse($ins->fecha_vencimiento);
            $diff = (int) floor($venc->diffInDays(Carbon::parse($fechaPago), false));
            if ($diff <= 0) {
                continue;
            }

            $dias = 0;
            if ($tipoPlanilla === 3) {
                $dias = $diff;
            } else {
                $cur = $venc->copy();
                for ($i = 1; $i <= $diff; $i++) {
                    $cur->addDay();
                    if (! in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                        $dias++;
                    }
                }
            }

            $rate = $credit->moraDiaria((float) $ins->importe_cuota + (float) $ins->importe_interes);
            $monto = round(max(0, $dias * $rate - $mora), 2);
            if ($monto > 0) {
                $out[(int) $ins->num_cuota] = ['monto' => $monto, 'dias' => $dias];
            }
        }

        return $out;
    }
}
