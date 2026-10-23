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
 * Crédito MENSUAL (ejemplo de Antony: 16 800 al 5 % del 28/01/2026):
 *   - Interés del X % = capital × X % (lo que cuesta cada mes); por día,
 *     interés mensual ÷ 30 (el "MxD" de la ficha).
 *   - Retraso = tiempo EXACTO en meses y días desde la fecha del crédito
 *     hasta hoy ("8 meses y 25 días"), sin redondear; en créditos en cuotas
 *     se descuentan primero los meses de interés ya pagados.
 *   - Monto retraso = meses × interés mensual + días × interés diario.
 *   - Total de deuda indicado = capital pendiente + monto retraso.
 * Crédito SEMANAL o DIARIO: se cuenta por cuotas del cronograma (cuotas
 * vencidas sin pagar, su saldo y el saldo total).
 */
final class ResumenDeudaCredito
{
    /**
     * @return array{codigo: int, nombre: string, fecha: string, mensual: bool, capital: float, capital_pendiente: float, interes_pct: string, interes: float, interes_diario: float, retraso_meses: int, retraso_dias: int, retraso_texto: string, monto_retraso: float, monto_meses: float, monto_dias: float, total: float, cancelado: ?string, texto: string}
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
        $interesDiario = round($interes / 30, 2);
        $capitalPagado = round((float) $cuotas->sum('importe_aplicado'), 2);
        $capitalPendiente = $cancelado ? 0.0 : round(max(0, $capital - $capitalPagado), 2);
        $mensual = (int) $credit->tipo_planilla === 3;
        $meses = 0;
        $dias = 0;

        if ($mensual) {
            // El retraso corre desde el primer mes de interés no pagado.
            $pagados = $interes > 0 ? (int) floor(((float) $cuotas->sum('interes_aplicado') + 0.005) / $interes) : 0;
            $inicio = $credit->fecha_prestamo ? Carbon::parse($credit->fecha_prestamo)->startOfDay()->addMonthsNoOverflow($pagados) : null;
            if (! $cancelado && $inicio !== null) {
                [$meses, $dias] = self::mesesYDias($inicio, $hasta);
            }
            $montoMeses = round($meses * $interes, 2);
            $montoDias = round($dias * $interesDiario, 2);
            $montoRetraso = round($montoMeses + $montoDias, 2);
            $retrasoTexto = self::textoRetraso($meses, $dias);
            $total = $cancelado ? 0.0 : round($capitalPendiente + $montoRetraso, 2);
        } else {
            $vencidas = $cancelado ? collect() : $cuotas->filter(fn (CreditInstallment $c) => ! $c->pagado && $c->fecha_vencimiento !== null && $c->fecha_vencimiento->lt($hasta));
            $saldoDe = fn (CreditInstallment $c) => (float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente
                - (float) $c->importe_aplicado - (float) $c->interes_aplicado - (float) $c->excedente_aplicado;
            $n = $vencidas->count();
            $retrasoTexto = $n.' '.($n === 1 ? 'cuota' : 'cuotas');
            $montoRetraso = round((float) $vencidas->sum($saldoDe), 2);
            $montoMeses = 0.0;
            $montoDias = 0.0;
            $total = $cancelado ? 0.0 : round(max(0, (float) $cuotas->sum($saldoDe)), 2);
        }

        $datos = [
            'codigo' => (int) $credit->id,
            'nombre' => $client->fullName(),
            'fecha' => $credit->fecha_prestamo ? Carbon::parse($credit->fecha_prestamo)->format('d/m/Y') : '',
            'mensual' => $mensual,
            'capital' => $capital,
            'capital_pendiente' => $capitalPendiente,
            'interes_pct' => rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
            'interes' => $interes,
            'interes_diario' => $interesDiario,
            'retraso_meses' => $meses,
            'retraso_dias' => $dias,
            'retraso_texto' => $retrasoTexto,
            'monto_retraso' => $montoRetraso,
            'monto_meses' => $montoMeses,
            'monto_dias' => $montoDias,
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
            'Retraso '.$d['retraso_texto'],
            '',
            'Monto Retraso '.$s($d['monto_retraso']),
        );
        // Desglose del retraso mensual (Antony, 23/10): lo que corresponde a los meses y lo prorrateado por días.
        if ($d['mensual'] && $d['retraso_meses'] > 0) {
            $lineas[] = 'Por '.$d['retraso_meses'].' '.($d['retraso_meses'] === 1 ? 'mes' : 'meses').' '.$s($d['monto_meses']);
        }
        if ($d['mensual'] && $d['retraso_dias'] > 0) {
            $lineas[] = 'Por '.$d['retraso_dias'].' '.($d['retraso_dias'] === 1 ? 'día' : 'días').' ('.$s($d['interes_diario']).' x día) '.$s($d['monto_dias']);
        }
        array_push($lineas,
            '',
            'Total de deuda indicado',
            $s($d['total']),
        );
        if ($d['cancelado'] !== null) {
            array_push($lineas, '', 'Crédito cancelado'.($d['cancelado'] !== 'sí' ? ' el '.$d['cancelado'] : ''));
        }

        return implode("\n", $lineas);
    }

    /**
     * Meses completos y días sueltos entre dos fechas, como se cuentan a mano
     * (28/01 → 23/10 = 8 meses y 25 días).
     *
     * @return array{0: int, 1: int}
     */
    public static function mesesYDias(Carbon $desde, Carbon $hasta): array
    {
        if ($hasta->lte($desde)) {
            return [0, 0];
        }
        $dif = $desde->copy()->startOfDay()->diff($hasta->copy()->startOfDay());

        return [$dif->y * 12 + $dif->m, $dif->d];
    }

    public static function textoRetraso(int $meses, int $dias): string
    {
        $partes = [];
        if ($meses > 0) {
            $partes[] = $meses.' '.($meses === 1 ? 'mes' : 'meses');
        }
        if ($dias > 0 || $meses === 0) {
            $partes[] = $dias.' '.($dias === 1 ? 'día' : 'días');
        }

        return implode(' y ', $partes);
    }
}
