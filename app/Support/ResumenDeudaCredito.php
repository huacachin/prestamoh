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
 *   - Total sin mora = capital pendiente + monto retraso.
 * Crédito SEMANAL o DIARIO: se cuenta por cuotas del cronograma (cuotas
 * vencidas sin pagar, su saldo y el saldo total).
 * Mora (todos los tipos, 23/10): la de CADA cuota vencida, como el modal
 * "elegir cuotas" del cobro, y su suma; Total con mora = total sin mora + esa
 * suma. La mora acumulada de cuotas ya pagadas se muestra aparte (informativa).
 */
final class ResumenDeudaCredito
{
    /**
     * @return array{codigo: int, nombre: string, fecha: string, fecha_corte: string, mensual: bool, capital: float, capital_pendiente: float, capital_amortizado: float, interes_pendiente: float, interes_pct: string, interes: float, interes_diario: float, retraso_meses: int, retraso_dias: int, retraso_texto: string, monto_retraso: float, monto_meses: float, monto_dias: float, mora_cuotas: list<array{cuota: int, dias: int, por_dia: float, monto: float}>, mora_dias: int, mora: float, mora_acumulada: array{dias: int, monto: float}, total: float, total_con_mora: float, cancelado: ?string, texto: string, texto_copia: string}
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
            // Interés pendiente (23/10, Antony): en el mensual es el interés del tiempo transcurrido, el retraso.
            $interesPendiente = $cancelado ? 0.0 : $montoRetraso;
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
            // Interés pendiente (23/10, Antony): lo que falta del interés (y del redondeo) en el cronograma,
            // también el de las cuotas que aún no vencen; capital pendiente + esto = total sin mora.
            $interesPendiente = $cancelado ? 0.0 : round(max(0, (float) $cuotas->sum(fn (CreditInstallment $c) => (float) $c->importe_interes + (float) $c->importe_excedente
                - (float) $c->interes_aplicado - (float) $c->excedente_aplicado)), 2);
        }

        // Mora POR CUOTA (23/10, Antony: "son 24 días de mora pero de la primera cuota vencida; debería ver el
        // detalle de la siguiente y al final sumarlos"): la del modal "elegir cuotas" del cobro
        // (Payments\Create::abrirMoraCuotas): por cada cuota impaga ya vencida, días desde SU vencimiento ×
        // su mora diaria (5 % de la cuota al mes prorrateado; diario: mora1). La mora es la suma.
        $moraCuotas = [];
        if (! $cancelado) {
            $impagas = $cuotas->filter(fn (CreditInstallment $c) => ! $c->pagado && (float) $c->importe_cuota > 0 && $c->fecha_vencimiento !== null)
                ->sortBy('fecha_vencimiento');
            foreach ($impagas as $c) {
                $diasCuota = max(0, (int) floor($c->fecha_vencimiento->copy()->startOfDay()->diffInDays($hasta, false)));
                if ($diasCuota === 0) {
                    continue;
                }
                $porDia = $credit->moraDiaria((float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente);
                $moraCuotas[] = ['cuota' => (int) $c->num_cuota, 'dias' => $diasCuota, 'por_dia' => $porDia, 'monto' => round($diasCuota * $porDia, 2)];
            }
        }
        $mora = round(array_sum(array_column($moraCuotas, 'monto')), 2);
        $moraDias = (int) array_sum(array_column($moraCuotas, 'dias'));

        // Mora acumulada (23/10): la de las cuotas que ya se pagaron con atraso, la misma de las tarjetas
        // "Días / Total Mora Acumulada" del cobro (MoraExonerada). Es informativa: no entra en los totales.
        $acumulada = ['dias' => 0, 'monto' => 0.0];
        if (! $cancelado) {
            $exonerada = collect(MoraExonerada::porCuota($credit, MoraPagada::mostradaPorCuota($credit)));
            $acumulada = ['dias' => (int) $exonerada->sum('dias'), 'monto' => round((float) $exonerada->sum('monto'), 2)];
        }

        $datos = [
            'codigo' => (int) $credit->id,
            'nombre' => $client->fullName(),
            'fecha' => $credit->fecha_prestamo ? Carbon::parse($credit->fecha_prestamo)->format('d/m/Y') : '',
            'fecha_corte' => $hoy->format('d/m/Y'),
            'mensual' => $mensual,
            'capital' => $capital,
            'capital_pendiente' => $capitalPendiente,
            // Lo que ya se pagó de capital (23/10, Antony): prestado − pendiente, así siempre cuadra la resta.
            'capital_amortizado' => $cancelado ? 0.0 : round($capital - $capitalPendiente, 2),
            'interes_pendiente' => $interesPendiente,
            'interes_pct' => rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
            'interes' => $interes,
            'interes_diario' => $interesDiario,
            'retraso_meses' => $meses,
            'retraso_dias' => $dias,
            'retraso_texto' => $retrasoTexto,
            'monto_retraso' => $montoRetraso,
            'monto_meses' => $montoMeses,
            'monto_dias' => $montoDias,
            'mora_cuotas' => $moraCuotas,
            'mora_dias' => $moraDias,
            'mora' => $mora,
            'mora_acumulada' => $acumulada,
            'total' => $total,
            'total_con_mora' => $cancelado ? 0.0 : round($total + $mora, 2),
            'cancelado' => $cancelado ? ($credit->fecha_cancelacion ? Carbon::parse($credit->fecha_cancelacion)->format('d/m/Y') : 'sí') : null,
        ];
        $datos['texto'] = self::texto($datos);
        // Lo que va al portapapeles: desde el 23/10 el texto ya va compacto (sin líneas en blanco), es el mismo.
        $datos['texto_copia'] = $datos['texto'];

        return $datos;
    }

    /**
     * El texto que se ve y se copia. Compacto (23/10, Antony: "tratar de comprimir todo porque ya se hace
     * muy largo"): una línea por dato con "Etiqueta: valor" y sin líneas en blanco.
     */
    public static function texto(array $d): string
    {
        $s = fn (float $n) => 'S/ '.number_format($n, 2);
        $enDias = fn (int $n) => $n.' '.($n === 1 ? 'día' : 'días');
        $lineas = [
            'DATOS DEL CRÉDITO #'.$d['codigo'],
            $d['nombre'],
            'Fecha del crédito: '.$d['fecha'],
            'Fecha de corte: '.$d['fecha_corte'],
            'Capital prestado: '.$s($d['capital']),
        ];
        if ($d['cancelado'] === null && $d['capital_pendiente'] < $d['capital']) {
            $lineas[] = 'Capital amortizado: '.$s($d['capital_amortizado']);
            $lineas[] = 'Capital pendiente: '.$s($d['capital_pendiente']);
        }
        $lineas[] = 'Interés del '.$d['interes_pct'].'%: '.$s($d['interes']);
        if ($d['cancelado'] === null) {
            $lineas[] = 'Interés pendiente: '.$s($d['interes_pendiente']);
        }
        $lineas[] = 'Retraso: '.$d['retraso_texto'].' · '.$s($d['monto_retraso']);
        // Desglose del retraso mensual en una línea: lo que corresponde a los meses y lo prorrateado por días.
        if ($d['mensual'] && ($d['retraso_meses'] > 0 || $d['retraso_dias'] > 0)) {
            $partes = [];
            if ($d['retraso_meses'] > 0) {
                $partes[] = 'Por '.$d['retraso_meses'].' '.($d['retraso_meses'] === 1 ? 'mes' : 'meses').': '.$s($d['monto_meses']);
            }
            if ($d['retraso_dias'] > 0) {
                $partes[] = 'Por '.$enDias($d['retraso_dias']).' ('.$s($d['interes_diario']).' x día): '.$s($d['monto_dias']);
            }
            $lineas[] = implode(' · ', $partes);
        }

        // Mora por cuota vencida y su suma; la tasa diaria va una sola vez si es la misma en todas.
        $moraCuotas = $d['mora_cuotas'];
        if ($moraCuotas === []) {
            $lineas[] = 'Mora: '.$s(0);
        } elseif (count($moraCuotas) === 1) {
            $m = $moraCuotas[0];
            $lineas[] = 'Mora cuota '.$m['cuota'].': '.$enDias($m['dias']).' ('.$s($m['por_dia']).' x día) · '.$s($m['monto']);
        } else {
            $tasas = array_unique(array_map(fn ($m) => number_format($m['por_dia'], 2), $moraCuotas));
            $mismaTasa = count($tasas) === 1;
            $lineas[] = 'Mora por cuota'.($mismaTasa ? ' ('.$s($moraCuotas[0]['por_dia']).' x día)' : '').':';
            foreach ($moraCuotas as $m) {
                $lineas[] = 'Cuota '.$m['cuota'].': '.$enDias($m['dias']).($mismaTasa ? '' : ' ('.$s($m['por_dia']).' x día)').' · '.$s($m['monto']);
            }
            $lineas[] = 'Mora total: '.$enDias($d['mora_dias']).' · '.$s($d['mora']);
        }
        if ($d['mora_acumulada']['dias'] > 0 || $d['mora_acumulada']['monto'] > 0) {
            $lineas[] = 'Mora acumulada (cuotas ya pagadas): '.$enDias($d['mora_acumulada']['dias']).' · '.$s($d['mora_acumulada']['monto']);
        }

        $lineas[] = 'Total de deuda sin mora: '.$s($d['total']);
        $lineas[] = 'Total de deuda con mora: '.$s($d['total_con_mora']);
        if ($d['cancelado'] !== null) {
            $lineas[] = 'Crédito cancelado'.($d['cancelado'] !== 'sí' ? ' el '.$d['cancelado'] : '');
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
