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
 * El monto se reparte entre esas cuotas por los días en que cada una fue
 * la más antigua impaga (de su vencimiento al vencimiento de la siguiente,
 * o a la fecha del pago) — el mismo reloj del cálculo de mora, que cobra
 * días × tasa desde la vencida más antigua. Como la tasa diaria es
 * uniforme, el prorrateo por días reproduce la mora que generó cada cuota
 * sin depender de la tasa histórica.
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

        // Mensual cuenta días corridos; semanal/diario salta sáb/dom
        $esMensual = (int) $credit->tipo_planilla === 3;

        $map = [];
        foreach ($ops as $o) {
            $fpago = Carbon::parse($o->fecha);
            $vencidas = ($cuotasPorOp[$o->mass_deletion_id] ?? collect())
                ->unique('num_cuota')
                ->map(fn ($c) => ['num' => (int) $c->num_cuota, 'venc' => Carbon::parse($c->fecha_vencimiento)])
                ->filter(fn ($c) => $c['venc']->lt($fpago))
                ->sortBy('num')->values();

            $items = [];
            foreach ($vencidas as $i => $c) {
                $hasta = $vencidas[$i + 1]['venc'] ?? $fpago;
                $items[] = ['num' => $c['num'], 'dias' => self::diasMoraEntre($c['venc'], $hasta, $esMensual)];
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

    /** Días de mora entre dos fechas con el mismo reloj del cálculo de mora. */
    private static function diasMoraEntre(Carbon $desde, Carbon $hasta, bool $diasCorridos): int
    {
        $diff = (int) floor($desde->diffInDays($hasta, false));
        if ($diff <= 0) {
            return 0;
        }
        if ($diasCorridos) {
            return $diff;
        }
        $d = 0;
        $cur = $desde->copy();
        for ($i = 1; $i <= $diff; $i++) {
            $cur->addDay();
            if (! in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $d++;
            }
        }

        return $d;
    }
}
