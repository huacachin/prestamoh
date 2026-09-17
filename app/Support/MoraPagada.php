<?php

namespace App\Support;

use App\Models\Credit;
use App\Models\CreditInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Desglose de la mora pagada que carga cada cuota, vía la operación de
 * cobro: los pagos MORA del sistema nuevo llevan fila tipo M en
 * mass_deletion_details (apunta a la cuota donde se anotó la mora) y las
 * cuotas C/I/E de esa misma operación son las que generaron el atraso.
 *
 * El monto se reparte entre esas cuotas en proporción a los DÍAS DE ATRASO
 * de cada una al momento del pago (de su vencimiento a la fecha del cobro).
 * Es la misma regla con la que el modal "Pagar por cuotas" calcula y cobra
 * la mora cuota por cuota (días × tarifa), así que con tarifa diaria
 * uniforme el prorrateo reproduce exactamente lo que se cobró por cada una,
 * sin depender de la tarifa histórica. Hasta el 17/09 se repartía por los
 * siete días en que cada cuota fue la más antigua impaga: salía parejo
 * (104,90 en las ocho del 28957) cuando lo cobrado iba de 186,48 a 23,31.
 *
 * La mora migrada del legacy no tiene vínculo de operación: pertenece a su
 * propia cuota (allá venía anotada cuota por cuota) y no aparece en este
 * mapa — las vistas usan la propia cuota como fallback.
 *
 * Compartido por /payments/create y /credits/{id}/schedule.
 */
class MoraPagada
{
    /** @return array<int, list<array{num:int, monto:float, dias:?int}>> por installment_id */
    public static function porCuota(Credit $credit): array
    {
        // El filtro tipo=M es obligatorio: payment_id solo, colisiona con
        // filas C/I de la migración que referencian otros pagos (ids
        // reutilizados por el autoincrement tras migrate:fresh).
        $ops = DB::table('mass_deletion_details as m')
            ->join('payments as p', 'p.id', '=', 'm.payment_id')
            ->where('p.credit_id', $credit->id)
            ->where('p.tipo', 'MORA')
            ->where('m.tipo', 'M')
            ->get(['m.mass_deletion_id', 'm.installment_id', 'p.monto', 'p.fecha']);
        if ($ops->isEmpty()) {
            return [];
        }

        $cuotasPorOp = DB::table('mass_deletion_details as d')
            ->join('credit_installments as ci', 'ci.id', '=', 'd.installment_id')
            ->whereIn('d.mass_deletion_id', $ops->pluck('mass_deletion_id')->unique()->all())
            ->whereIn('d.tipo', ['C', 'I', 'E'])
            ->distinct()
            ->get(['d.mass_deletion_id', 'ci.num_cuota', 'ci.fecha_vencimiento'])
            ->groupBy('mass_deletion_id');

        $map = [];
        foreach ($ops as $o) {
            $fpago = Carbon::parse($o->fecha);
            $vencidas = ($cuotasPorOp[$o->mass_deletion_id] ?? collect())
                ->unique('num_cuota')
                ->map(fn ($c) => ['num' => (int) $c->num_cuota, 'venc' => Carbon::parse($c->fecha_vencimiento)])
                ->filter(fn ($c) => $c['venc']->lt($fpago))
                ->sortBy('num')->values();

            // 17/09: cada cuota pesa por SUS días de atraso al momento del pago
            // (de su vencimiento a la fecha del cobro), no por los siete días
            // en que fue la más antigua impaga. Con tarifa diaria uniforme eso
            // reproduce exactamente lo que cobra el modal "Pagar por cuotas"
            // (días × tarifa por cuota): en el 28957, 186,48 en la 18 bajando
            // hasta 23,31 en la 25. El reparto parejo anterior mostraba 104,90
            // en todas y Antony lo señaló: "cada cuota tiene diferente mora".
            $items = [];
            foreach ($vencidas as $c) {
                $items[] = ['num' => $c['num'], 'dias' => self::diasMoraEntre($c['venc'], $fpago)];
            }
            $totDias = array_sum(array_column($items, 'dias'));

            if ($totDias <= 0) {
                // Sin vencidas al pagar (mora manual sobre cuotas al día):
                // todo a la cuota ancla de la fila M.
                $numAncla = (int) (CreditInstallment::find($o->installment_id)?->num_cuota ?? 0);
                $items = [['num' => $numAncla, 'monto' => (float) $o->monto, 'dias' => null]];
            } else {
                $resto = (float) $o->monto;
                foreach ($items as $i => $it) {
                    $items[$i]['monto'] = $i === count($items) - 1
                        ? round($resto, 2)
                        : round((float) $o->monto * $it['dias'] / $totDias, 2);
                    $resto -= $items[$i]['monto'];
                }
            }

            // Varias operaciones ancladas a la misma cuota: fusionar por num
            $map[$o->installment_id] = collect($map[$o->installment_id] ?? [])
                ->concat($items)
                ->groupBy('num')
                ->map(fn ($g, $num) => [
                    'num' => (int) $num,
                    'monto' => round($g->sum('monto'), 2),
                    'dias' => $g->sum('dias') ?: null,
                ])
                ->sortBy('num')->values()->all();
        }

        return $map;
    }

    /**
     * Mora que MUESTRA cada cuota en las pantallas (17/09, pedido de Antony:
     * "muestra el reparto en las celdas, no en el tooltip").
     *
     * porCuota() devuelve el reparto agrupado por la cuota ANCLA de cada
     * operación: la primera cuota tocada por el cobro, donde el motor anota
     * el monto ENTERO en importe_mora. Pintar esa columna tal cual ponía toda
     * la mora en la primera cuota y dejaba en blanco a las demás que la
     * generaron (crédito 28957: 839,16 en la 18 y nada en la 19 a 25, con el
     * reparto escondido en un tooltip).
     *
     * Aquí se invierte el mapa: cada cuota recibe SU parte de cada operación
     * en que participó, más lo que quedó anotado en ella sin operación (la
     * mora migrada del legacy, que allá venía cuota por cuota). La suma por
     * crédito es la misma que la de importe_mora + mora_interes: solo cambia
     * dónde se ve.
     *
     * @return array<int, array{monto: float, dias: ?int, detalle: string}> por installment_id
     */
    public static function mostradaPorCuota(Credit $credit): array
    {
        $reparto = self::porCuota($credit);

        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get(['id', 'num_cuota', 'importe_mora', 'mora_interes']);
        $numPorId = $cuotas->pluck('num_cuota', 'id');

        // Parte de cada cuota en cada operación, y cuánto quedó ANCLADO en
        // cada cuota: eso ya está repartido y no debe contarse dos veces.
        $parte = [];    // num_cuota => ['monto' => float, 'dias' => int, 'origen' => list<string>]
        $anclado = [];  // installment_id => float
        foreach ($reparto as $anclaId => $items) {
            $totalAnclado = round(array_sum(array_column($items, 'monto')), 2);
            $anclado[$anclaId] = round(($anclado[$anclaId] ?? 0) + $totalAnclado, 2);

            $nums = array_map('intval', array_column($items, 'num'));
            $rango = count($nums) > 1 ? min($nums).' a '.max($nums) : (string) ($nums[0] ?? '');
            $origen = count($nums) > 1
                ? 'parte de un cobro de '.number_format($totalAnclado, 2).' por las cuotas '.$rango
                    .' (anotado en la cuota '.($numPorId[$anclaId] ?? '?').')'
                : 'cobro de '.number_format($totalAnclado, 2);

            foreach ($items as $it) {
                $n = (int) $it['num'];
                $parte[$n]['monto'] = round(($parte[$n]['monto'] ?? 0) + (float) $it['monto'], 2);
                $parte[$n]['dias'] = ($parte[$n]['dias'] ?? 0) + (int) ($it['dias'] ?? 0);
                $parte[$n]['origen'][] = $origen;
            }
        }

        $salida = [];
        foreach ($cuotas as $c) {
            $raw = (float) $c->importe_mora + (float) $c->mora_interes;
            // Lo anotado en la cuota que NO viene de una operación: legacy.
            $propia = round(max(0, $raw - ($anclado[$c->id] ?? 0)), 2);
            $p = $parte[(int) $c->num_cuota] ?? null;
            $monto = round(($p['monto'] ?? 0) + $propia, 2);
            if ($monto <= 0) {
                continue;
            }

            $lineas = $p['origen'] ?? [];
            if ($propia > 0) {
                $lineas[] = 'anotada en la propia cuota: '.number_format($propia, 2);
            }
            $dias = (int) ($p['dias'] ?? 0);

            $salida[(int) $c->id] = [
                'monto' => $monto,
                'dias' => $dias ?: null,
                'detalle' => 'Mora de la cuota '.$c->num_cuota.': '.number_format($monto, 2)
                    .($dias ? ' - D. '.$dias : '')
                    .'<br>'.implode('<br>', $lineas),
            ];
        }

        return $salida;
    }

    /**
     * Días de mora entre dos fechas con el mismo reloj del cálculo de mora:
     * calendario corrido para todos los tipos (regla única desde 02/09).
     */
    private static function diasMoraEntre(Carbon $desde, Carbon $hasta): int
    {
        return max(0, (int) floor($desde->diffInDays($hasta, false)));
    }
}
