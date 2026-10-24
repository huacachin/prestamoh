<?php

namespace App\Support;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Calcular cancelación" de la pantalla de cobro (23/10/2026, Antony, crédito 28603).
 * El cajero elige hasta qué cuota se cobra el interés y el monto para cancelar es
 *
 *     interés por cuota × N + capital del crédito − monto pagado
 *
 * En el 28603 de producción: 82.50 × 50 + 11,000 − 10,118.40 = 5,006.60. El monto
 * pagado es lo aplicado en el cronograma (capital + interés + excedente, sin la
 * mora). Al cobrar, el interés que sobra se condona con el mecanismo de siempre de
 * la cancelación anticipada (Payments\Create::condonarInteresFuturo).
 *
 * N va de `minimo` a `maximo`: nunca más que las cuotas del préstamo ni menos que
 * las pagadas. El mínimo además sube lo justo para que el monto cubra el capital
 * pendiente y el excedente vencido, porque al cancelar solo se condona interés. Así
 * el interés cobrado nunca baja del ya pagado (en el 28603 la cuota 44 ya tiene 1.36
 * de interés: el mínimo es 44, no 43) y el cobro deja el cronograma en cero.
 *
 * `capital_de_mas`: 36 semanales migrados del legacy llevan en la última cuota unos
 * céntimos de capital de más que el préstamo (el 28603, 0.32: el redondeo se sumó en
 * vez de restarse). Como la fórmula usa el capital del préstamo, al cancelar se
 * corrigen en esa cuota (Payments\Create::corregirCapitalDeMas) y el interés
 * exonerado es justo el de las cuotas siguientes.
 *
 * No aplica a los mensuales de una cuota: ahí el interés corre por mes, no por cuota.
 */
final class CancelacionHastaCuota
{
    public static function aplica(Credit $credit): bool
    {
        return ! ((int) $credit->tipo_planilla === 3 && (int) $credit->cuotas === 1);
    }

    /**
     * Datos de la fórmula para el crédito tal como está hoy, o null si no aplica.
     *
     * @return array{interes_cuota: float, capital: float, pagado: float, total_cuotas: int, ultima_cuota: int, pagadas: int, minimo: int, maximo: int, vencidas: int, saldo: float, saldo_al_cancelar: float, capital_de_mas: float, vencimientos: array<int, string>}|null
     */
    public static function plan(Credit $credit, ?Carbon $fecha = null): ?array
    {
        if (! self::aplica($credit)) {
            return null;
        }

        $fecha = ($fecha ?? now())->copy()->startOfDay();
        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'pagado', 'importe_cuota', 'importe_interes', 'importe_excedente',
                'importe_aplicado', 'interes_aplicado', 'excedente_aplicado']);

        $interesCuota = self::interesPorCuota($cuotas);
        if ($cuotas->isEmpty() || $interesCuota <= 0) {
            return null;
        }

        $capital = round((float) $credit->importe, 2);
        $pagado = round((float) $cuotas->sum(fn (object $c) => (float) $c->importe_aplicado + (float) $c->interes_aplicado + (float) $c->excedente_aplicado), 2);
        $saldo = round((float) $cuotas->sum(fn (object $c) => (float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente
            - (float) $c->importe_aplicado - (float) $c->interes_aplicado - (float) $c->excedente_aplicado), 2);

        // Excedente pendiente de las cuotas impagas: el de las que aún no vencen se condona al cancelar
        // (condonarExcedenteFuturo); el de las vencidas se cobra.
        $vencida = fn (object $c) => $c->fecha_vencimiento !== null && Carbon::parse($c->fecha_vencimiento)->startOfDay()->lte($fecha);
        $excedentePendiente = fn (object $c) => (int) $c->pagado === 0 ? max(0.0, (float) $c->importe_excedente - (float) $c->excedente_aplicado) : 0.0;
        $excedenteFuturo = round((float) $cuotas->reject($vencida)->sum($excedentePendiente), 2);
        $excedenteVencido = round((float) $cuotas->filter($vencida)->sum($excedentePendiente), 2);

        // Céntimos de capital de más en la última cuota (hasta 1.00 y si esa cuota aún los debe): se corrigen al cancelar.
        $ultima = $cuotas->sortByDesc('num_cuota')->first();
        $deMas = round((float) $cuotas->sum('importe_cuota') - $capital, 2);
        $capitalDeMas = $deMas > 0.001 && $deMas <= 1.0 && (int) $ultima->pagado === 0
            && (float) $ultima->importe_cuota - (float) $ultima->importe_aplicado >= $deMas ? $deMas : 0.0;

        // Lo que el cobro debe cubrir sí o sí: el capital pendiente (ya sin esos céntimos) y el excedente vencido.
        $piso = round((float) $cuotas->sum(fn (object $c) => (float) $c->importe_cuota - (float) $c->importe_aplicado) - $capitalDeMas + $excedenteVencido, 2);

        $totalCuotas = $cuotas->count();
        $pagadas = $cuotas->filter(fn (object $c) => (int) $c->pagado === 1)->count();
        $minimo = max(1, $pagadas, (int) ceil(round(($piso - $capital + $pagado) / $interesCuota, 6)));
        if ($minimo > $totalCuotas) {
            return null;
        }

        return [
            'interes_cuota' => $interesCuota,
            'capital' => $capital,
            'pagado' => $pagado,
            'total_cuotas' => $totalCuotas,
            'ultima_cuota' => (int) $cuotas->max('num_cuota'),
            'pagadas' => $pagadas,
            'minimo' => $minimo,
            'maximo' => $totalCuotas,
            'vencidas' => (int) ($cuotas->filter($vencida)->max('num_cuota') ?? 0),
            'saldo' => $saldo,
            'saldo_al_cancelar' => round($saldo - $excedenteFuturo - $capitalDeMas, 2),
            'capital_de_mas' => $capitalDeMas,
            'vencimientos' => $cuotas->mapWithKeys(fn (object $c) => [(int) $c->num_cuota => $c->fecha_vencimiento ? Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') : ''])->all(),
        ];
    }

    /**
     * Monto para cancelar cobrando el interés hasta la cuota $hasta (se acota al rango del plan).
     * Nunca pasa del saldo que queda al cancelar.
     *
     * @param  array{interes_cuota: float, capital: float, pagado: float, minimo: int, maximo: int, saldo_al_cancelar: float}  $plan
     */
    public static function monto(array $plan, int $hasta): float
    {
        $hasta = max($plan['minimo'], min($plan['maximo'], $hasta));

        return round(min($plan['interes_cuota'] * $hasta + $plan['capital'] - $plan['pagado'], $plan['saldo_al_cancelar']), 2);
    }

    /**
     * El detalle por cuota del recibo de ese cobro (23/10, Antony: "debe aparecer solo las cuotas
     * que estoy colocando, no deben de aparecer todas"): las cuotas hasta la elegida van una por una
     * y lo que el cobro dejó en las siguientes, en una sola línea. Al cancelar, a las siguientes
     * solo va su capital (su interés se exonera); si el crédito queda vigente, el monto les llegó
     * en orden, como a cualquier cobro.
     *
     * @param  list<array{num: int, monto: float, parcial: bool, saldo: float}>  $detalle
     * @return array{detalle: list<array{num: int, monto: float, parcial: bool, saldo: float}>, cuotas: list<int>, resto: array{rotulo: string, monto: float}|null}
     */
    public static function agrupar(array $detalle, int $hasta, bool $cancela): array
    {
        $elegidas = array_values(array_filter($detalle, fn (array $c) => $c['num'] <= $hasta));
        $siguientes = array_values(array_filter($detalle, fn (array $c) => $c['num'] > $hasta));
        $resto = null;
        if ($siguientes !== []) {
            $rotulo = self::sinCortes(RotuloCuotas::de(array_column($siguientes, 'num')));
            $resto = [
                'rotulo' => ($cancela ? 'Capital '.$rotulo : ucfirst($rotulo)).':',
                'monto' => round(array_sum(array_column($siguientes, 'monto')), 2),
            ];
        }

        return ['detalle' => $elegidas, 'cuotas' => array_column($elegidas, 'num'), 'resto' => $resto];
    }

    /**
     * Las líneas del recibo de ese cobro (23/10, Antony: "en el voucher hay que indicar que se
     * está cancelando hasta la cuota 50 y que las demás cuotas se están exonerando"). Las usan la
     * vista previa del cobro, el recibo web, el PDF y la ticketera.
     *
     * @return array{titulo: string, exonera: ?string, exonerado: float}
     */
    public static function lineasRecibo(int $hasta, int $ultima, float $exonerado): array
    {
        // Las cuotas siguientes pagan su capital: lo que se les exonera es el interés. Si se cobró el
        // interés de todas, no hay cuotas exoneradas (los céntimos de redondeo no se anuncian).
        $exonera = $hasta < $ultima
            ? 'Se exonera el interés de '.($hasta + 1 === $ultima ? 'la ' : 'las ').self::sinCortes(RotuloCuotas::de(range($hasta + 1, $ultima)))
            : null;

        return [
            'titulo' => "Cancelación hasta la cuota {$hasta}",
            'exonera' => $exonera,
            'exonerado' => round($exonerado, 2),
        ];
    }

    /**
     * "cuotas 51 a 72" con espacios que no se cortan: en un recibo angosto el rango baja entero a la
     * línea siguiente en vez de partirse en "cuotas 51" y "a 72". La ticketera los vuelve espacios.
     */
    private static function sinCortes(string $texto): string
    {
        return str_replace(' ', "\u{00A0}", $texto);
    }

    /**
     * El interés de una cuota del cronograma (82.50 en el 28603): el valor que más se repite,
     * para que el redondeo de la última cuota no lo cambie.
     *
     * @param  Collection<int, object>  $cuotas
     */
    private static function interesPorCuota(Collection $cuotas): float
    {
        $frecuencias = $cuotas->countBy(fn (object $c) => number_format((float) $c->importe_interes, 2, '.', ''));
        $maximo = $frecuencias->max();

        return (float) ($frecuencias->filter(fn (int $veces) => $veces === $maximo)->keys()->first() ?? 0);
    }
}
