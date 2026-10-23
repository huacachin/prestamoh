<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use Carbon\Carbon;

/**
 * Resumen de cobranza de un crédito (23/10/2026, pedido de Antony): el texto
 * que la oficina le manda al cliente desde la ficha, con el botón Copiar.
 *
 * Crédito MENSUAL (la regla del ejemplo de Antony: 16 800 al 5 % del
 * 28/01/2026 → "Retraso 9 meses", 9 × 840 = 7 560, total 24 360):
 *   - Interés del X % = capital × X % (lo que cuesta cada mes).
 *   - Retraso = meses INICIADOS desde la fecha del crédito hasta hoy (el mes
 *     en curso cuenta entero) menos los meses de interés ya pagados.
 *   - Monto retraso = meses de retraso × interés mensual.
 *   - Total de deuda indicado = capital pendiente + monto retraso.
 * Crédito SEMANAL o DIARIO: se cuenta por cuotas del cronograma (cuotas
 * vencidas sin pagar, su saldo y el saldo total).
 */
final class ResumenDeudaCredito
{
    /**
     * @return array{codigo: int, nombre: string, fecha: string, mensual: bool, capital: float, capital_pendiente: float, interes_pct: string, interes: float, retraso: int, unidad: string, monto_retraso: float, total: float, cancelado: ?string, texto: string}
     */
    public static function de(Credit $credit, Client $client, ?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $cuotas = $credit->relationLoaded('installments') ? $credit->installments : $credit->installments()->orderBy('num_cuota')->get();
        $cancelado = $credit->situacion !== 'Activo';
        $hasta = $cancelado && $credit->fecha_cancelacion ? Carbon::parse($credit->fecha_cancelacion)->startOfDay() : $hoy->copy()->startOfDay();

        $capital = round((float) $credit->importe, 2);
        $pct = (float) $credit->interes;
        $interes = round($capital * $pct / 100, 2);
        $capitalPagado = round((float) $cuotas->sum('importe_aplicado'), 2);
        $capitalPendiente = $cancelado ? 0.0 : round(max(0, $capital - $capitalPagado), 2);

        if ((int) $credit->tipo_planilla === 3) {
            $iniciados = $credit->fecha_prestamo ? self::mesesIniciados(Carbon::parse($credit->fecha_prestamo)->startOfDay(), $hasta) : 0;
            $pagados = $interes > 0 ? (int) floor(((float) $cuotas->sum('interes_aplicado') + 0.005) / $interes) : 0;
            $retraso = $cancelado ? 0 : max(0, $iniciados - $pagados);
            $unidad = $retraso === 1 ? 'mes' : 'meses';
            $montoRetraso = round($retraso * $interes, 2);
            $total = $cancelado ? 0.0 : round($capitalPendiente + $montoRetraso, 2);
        } else {
            $vencidas = $cancelado ? collect() : $cuotas->filter(fn (CreditInstallment $c) => ! $c->pagado && $c->fecha_vencimiento !== null && $c->fecha_vencimiento->lt($hasta));
            $saldoDe = fn (CreditInstallment $c) => (float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente
                - (float) $c->importe_aplicado - (float) $c->interes_aplicado - (float) $c->excedente_aplicado;
            $retraso = $vencidas->count();
            $unidad = $retraso === 1 ? 'cuota' : 'cuotas';
            $montoRetraso = round((float) $vencidas->sum($saldoDe), 2);
            $total = $cancelado ? 0.0 : round(max(0, (float) $cuotas->sum($saldoDe)), 2);
        }

        $datos = [
            'codigo' => (int) $credit->id,
            'nombre' => $client->fullName(),
            'fecha' => $credit->fecha_prestamo ? Carbon::parse($credit->fecha_prestamo)->format('d/m/Y') : '',
            'mensual' => (int) $credit->tipo_planilla === 3,
            'capital' => $capital,
            'capital_pendiente' => $capitalPendiente,
            'interes_pct' => rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
            'interes' => $interes,
            'retraso' => $retraso,
            'unidad' => $unidad,
            'monto_retraso' => $montoRetraso,
            'total' => $total,
            'cancelado' => $cancelado ? ($credit->fecha_cancelacion ? Carbon::parse($credit->fecha_cancelacion)->format('d/m/Y') : 'sí') : null,
        ];
        $datos['texto'] = self::texto($datos);

        return $datos;
    }

    /** El texto tal cual se copia, con la estructura que pidió Antony. */
    public static function texto(array $d): string
    {
        $s = fn (float $n) => 'S/ '.number_format($n, 2);
        $lineas = [
            'DATOS DEL CRÉDITO',
            '',
            $d['nombre'],
            '',
            'Fecha del crédito '.$d['fecha'],
            '',
            'Capital prestado',
            $s($d['capital']),
        ];
        if ($d['cancelado'] === null && $d['capital_pendiente'] < $d['capital']) {
            array_push($lineas, '', 'Capital pendiente', $s($d['capital_pendiente']));
        }
        array_push($lineas,
            '',
            'Interés del '.$d['interes_pct'].'% '.$s($d['interes']),
            '',
            'Retraso '.$d['retraso'].' '.$d['unidad'],
            '',
            'Monto Retraso '.$s($d['monto_retraso']),
            '',
            'Total de deuda indicado',
            $s($d['total']),
        );
        if ($d['cancelado'] !== null) {
            array_push($lineas, '', 'Crédito cancelado'.($d['cancelado'] !== 'sí' ? ' el '.$d['cancelado'] : ''));
        }

        return implode("\n", $lineas);
    }

    /** Meses desde $desde hasta $hasta contando entero el mes en curso (28/01 → 23/10 = 9). */
    public static function mesesIniciados(Carbon $desde, Carbon $hasta): int
    {
        if ($hasta->lte($desde)) {
            return 0;
        }
        $completos = 0;
        $cursor = $desde->copy();
        while ($cursor->copy()->addMonthNoOverflow()->lte($hasta)) {
            $cursor->addMonthNoOverflow();
            $completos++;
        }

        return $completos + ($cursor->lt($hasta) ? 1 : 0);
    }
}
