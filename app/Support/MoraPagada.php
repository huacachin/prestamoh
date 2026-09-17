<?php

namespace App\Support;

use App\Models\Credit;
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
 * mapa — mostradaPorCuota() la suma como "propia" de la cuota.
 *
 * Compartido por /payments/create y /credits/{id}/schedule.
 */
class MoraPagada
{
    /**
     * Reparto por cuota ANCLA: la cuota donde el cobro anotó la mora entera.
     *
     * @return array<int, list<array{id:int, num:int, monto:float, dias:?int}>> por installment_id de la ancla
     */
    public static function porCuota(Credit $credit): array
    {
        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->get(['id', 'num_cuota', 'fecha_vencimiento'])
            ->keyBy('id');
        if ($cuotas->isEmpty()) {
            return [];
        }

        // 17/09: la unión exige que la OPERACIÓN (mass_deletions) sea de este
        // crédito y que la cuota ancla le pertenezca. Las filas M migradas del
        // legacy conservan payment_id = id del ingreso legacy, que colisiona
        // con ids de pagos nuevos (los pagos se migraron sin id): sin este
        // filtro, una fila M de OTRO crédito se colaba por el pago MORA de
        // éste y metía mora fantasma en el reparto. El filtro tipo=M ya era
        // obligatorio por las colisiones con filas C/I.
        $ops = DB::table('mass_deletion_details as m')
            ->join('payments as p', 'p.id', '=', 'm.payment_id')
            ->join('mass_deletions as md', 'md.id', '=', 'm.mass_deletion_id')
            ->where('p.credit_id', $credit->id)
            ->where('md.credit_id', $credit->id)
            ->where('p.tipo', 'MORA')
            ->where('m.tipo', 'M')
            ->whereIn('m.installment_id', $cuotas->keys()->all())
            // El motor nuevo escribe la fila M con amount = monto del pago MORA
            // (Create.php, secciones 4 y 5). Una fila M legacy cuyo payment_id
            // colisione con un pago MORA de ESTE crédito no cumple esto.
            ->whereColumn('m.amount', 'p.monto')
            ->get(['m.mass_deletion_id', 'm.installment_id', 'p.monto', 'p.fecha']);
        if ($ops->isEmpty()) {
            return [];
        }

        $cuotasPorOp = DB::table('mass_deletion_details as d')
            ->join('credit_installments as ci', 'ci.id', '=', 'd.installment_id')
            ->where('ci.credit_id', $credit->id)
            ->whereIn('d.mass_deletion_id', $ops->pluck('mass_deletion_id')->unique()->all())
            ->whereIn('d.tipo', ['C', 'I', 'E'])
            ->distinct()
            ->get(['d.mass_deletion_id', 'ci.id', 'ci.num_cuota', 'ci.fecha_vencimiento'])
            ->groupBy('mass_deletion_id');

        $map = [];
        foreach ($ops as $o) {
            $fpago = Carbon::parse($o->fecha);
            $vencidas = ($cuotasPorOp[$o->mass_deletion_id] ?? collect())
                ->unique('id')
                ->map(fn ($c) => ['id' => (int) $c->id, 'num' => (int) $c->num_cuota, 'venc' => Carbon::parse($c->fecha_vencimiento)])
                ->filter(fn ($c) => $c['venc']->lt($fpago))
                ->sortBy('num')->values();

            $items = [];
            foreach ($vencidas as $c) {
                $items[] = ['id' => $c['id'], 'num' => $c['num'], 'dias' => self::diasMoraEntre($c['venc'], $fpago)];
            }
            $totDias = array_sum(array_column($items, 'dias'));

            if ($totDias <= 0) {
                // Sin vencidas al pagar (mora manual sobre cuotas al día):
                // todo a la cuota ancla de la fila M.
                $ancla = $cuotas[$o->installment_id];
                $items = [['id' => (int) $ancla->id, 'num' => (int) $ancla->num_cuota, 'monto' => (float) $o->monto, 'dias' => null]];
            } else {
                $partes = self::repartirCentavos((float) $o->monto, array_column($items, 'dias'));
                foreach ($items as $i => $it) {
                    $items[$i]['monto'] = $partes[$i];
                }
            }

            // Varias operaciones ancladas a la misma cuota: fusionar por cuota.
            $map[(int) $o->installment_id] = collect($map[(int) $o->installment_id] ?? [])
                ->concat($items)
                ->groupBy('id')
                ->map(fn ($g, $id) => [
                    'id' => (int) $id,
                    'num' => (int) $g->first()['num'],
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
     * Defensa: si en una cuota ancla hay anotado MENOS de lo que dicen sus
     * filas M (una reversa que puso importe_mora en 0 y dejó viva la fila M
     * de otro cobro), las partes de esa ancla se recortan en proporción. Así
     * las celdas nunca suman más que lo anotado, y el total sigue cuadrando.
     *
     * @param  array|null  $reparto  porCuota() ya calculado, para no repetir consultas.
     * @return array<int, array{monto: float, dias: ?int, detalle: string}> por installment_id
     */
    public static function mostradaPorCuota(Credit $credit, ?array $reparto = null): array
    {
        $reparto ??= self::porCuota($credit);

        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get(['id', 'num_cuota', 'importe_mora', 'mora_interes'])
            ->keyBy('id');

        $raw = [];
        foreach ($cuotas as $id => $c) {
            $raw[$id] = round((float) $c->importe_mora + (float) $c->mora_interes, 2);
        }

        $parte = [];    // installment_id => ['monto' => float, 'dias' => int, 'origen' => list<string>]
        $anclado = [];  // installment_id => lo que porCuota ya repartió desde esa ancla
        foreach ($reparto as $anclaId => $items) {
            if (! isset($cuotas[$anclaId]) || empty($items)) {
                continue;
            }
            $totalAnclado = round(array_sum(array_column($items, 'monto')), 2);
            if ($totalAnclado <= 0) {
                continue;
            }

            $disponible = round(max(0, $raw[$anclaId] - ($anclado[$anclaId] ?? 0)), 2);
            $aRepartir = min($totalAnclado, $disponible);
            if ($aRepartir <= 0) {
                continue;
            }
            // Mismo peso que traían las partes; en centavos enteros para que
            // la suma sea exacta también cuando hubo recorte.
            $partes = $aRepartir < $totalAnclado
                ? self::repartirCentavos($aRepartir, array_map(fn ($it) => (int) round($it['monto'] * 100), $items))
                : array_map(fn ($it) => (float) $it['monto'], $items);
            $anclado[$anclaId] = round(($anclado[$anclaId] ?? 0) + $aRepartir, 2);

            $nums = array_map('intval', array_column($items, 'num'));
            $rango = count($nums) > 1 ? min($nums).' a '.max($nums) : (string) ($nums[0] ?? '');
            $origen = count($nums) > 1
                ? 'parte de un cobro de '.number_format($aRepartir, 2).' por las cuotas '.$rango
                    .' (anotado en la cuota '.$cuotas[$anclaId]->num_cuota.')'
                : 'cobro de '.number_format($aRepartir, 2);

            foreach ($items as $i => $it) {
                $id = (int) $it['id'];
                $monto = $partes[$i];
                if ($monto <= 0 || ! isset($cuotas[$id])) {
                    continue;
                }
                $parte[$id]['monto'] = round(($parte[$id]['monto'] ?? 0) + $monto, 2);
                $parte[$id]['dias'] = ($parte[$id]['dias'] ?? 0) + (int) ($it['dias'] ?? 0);
                $parte[$id]['origen'][] = $origen;
            }
        }

        $salida = [];
        foreach ($cuotas as $id => $c) {
            // Lo anotado en la cuota que NO viene de una operación: legacy.
            $propia = round(max(0, $raw[$id] - ($anclado[$id] ?? 0)), 2);
            $p = $parte[$id] ?? null;
            $monto = round(($p['monto'] ?? 0) + $propia, 2);
            if ($monto <= 0) {
                continue;
            }

            $lineas = $p['origen'] ?? [];
            if ($propia > 0) {
                $lineas[] = 'anotada en la propia cuota: '.number_format($propia, 2);
            }
            $dias = (int) ($p['dias'] ?? 0);

            $salida[(int) $id] = [
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
     * Reparte $monto en proporción a $pesos, en CENTAVOS ENTEROS y por resto
     * mayor: ninguna parte sale negativa y la suma es exacta por construcción.
     * Antes (17/09) se redondeaba parte por parte y la última se llevaba el
     * resto, que podía ser negativo: 0,50 entre 60 cuotas iguales daba 59 ×
     * 0,01 = 0,59 y la última −0,09, que la celda descartaba.
     *
     * @param  list<int|float>  $pesos
     * @return list<float>
     */
    private static function repartirCentavos(float $monto, array $pesos): array
    {
        $n = count($pesos);
        $total = (int) round($monto * 100);
        $suma = (int) array_sum($pesos);
        if ($n === 0 || $suma <= 0 || $total <= 0) {
            return array_fill(0, $n, 0.0);
        }

        $base = [];
        $resto = [];
        foreach (array_values($pesos) as $i => $p) {
            $base[$i] = intdiv($total * (int) $p, $suma);
            $resto[$i] = ($total * (int) $p) % $suma;
        }
        // Los centavos que faltan (siempre menos que $n) van a las de mayor
        // fracción; en empate, a la primera de la lista (la más antigua).
        $faltan = $total - array_sum($base);
        $orden = array_keys($resto);
        usort($orden, fn ($a, $b) => ($resto[$b] <=> $resto[$a]) ?: ($a <=> $b));
        for ($k = 0; $k < $faltan; $k++) {
            $base[$orden[$k]]++;
        }

        return array_map(fn ($c) => $c / 100, $base);
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
