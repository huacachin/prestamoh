<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Motor de distribución de un pago sobre las cuotas de un crédito.
 *
 * Es el ESPEJO del motor legacy (pagossmasivo.php): dado el monto de UNA
 * operación y el estado del cronograma en ese momento, decide cuánto va a
 * capital, interés y excedente de cada cuota — misma clasificación que el
 * legacy escribiría en huaca_ingreso / huaca_det_masivo.
 *
 * REGLA DEL SOBRANTE (POR OPERACIÓN, no fija):
 *  - Residuo que desborda a una cuota con capital pendiente → CAPITAL de esa
 *    cuota (flujo estándar: pago en 1 operación).
 *  - Operación que cae en cuota con capital ya cubierto ("fase de interés")
 *    → INTERÉS (flujo en 2 operaciones).
 * Si cobranza digita las MISMAS operaciones en ambos sistemas, la
 * clasificación coincide al céntimo. NO agregar reglas de reetiquetado fijo.
 *
 * Función PURA: no toca BD ni estado; validable contra el histórico con
 * `php artisan payments:backtest-legacy`.
 */
final class DistribucionPago
{
    /**
     * @param  iterable  $unpaid  cuotas impagas en orden (objetos con
     *                            num_cuota, importe_cuota, importe_interes, importe_excedente,
     *                            importe_aplicado, interes_aplicado, excedente_aplicado)
     * @return array{
     *   rows: array<int, array{ins: mixed, cap: float, int: float, exc: float}>,
     *   capital: float, interes: float, excedente: float, cuotas: array<int, int>
     * }
     */
    public function distribuir(float $monto, iterable $unpaid, bool $isMensualUnaCuota): array
    {
        $rows = [];
        $cuotas = [];
        $capital = $interes = $excedente = 0.0;

        $remaining = $monto;
        $tocadas = 0;
        // Capital pagado en cuotas anteriores de ESTE pago (decide el
        // destino del sobrante que desborda a la cuota siguiente).
        $opPagoCapital = false;

        foreach ($unpaid as $ins) {
            if ($remaining < 0.01) {
                break;
            }

            // Fase de interés: el capital de la cuota en curso ya estaba
            // cubierto y este pago no puso capital → lo que desborda a la
            // cuota siguiente se aplica a su INTERÉS (espejo del legacy
            // cuando la operación cae sobre cuota con capital cubierto).
            $sobranteAInteres = $tocadas > 0 && ! $opPagoCapital;

            if ($isMensualUnaCuota || $sobranteAInteres) {
                // INTERES PRIMERO (tipoplani=3 + cuotas=1, o fase de interés)
                $apagarInt = (float) $ins->importe_interes - (float) $ins->interes_aplicado;
                $apagarCap = (float) $ins->importe_cuota - (float) $ins->importe_aplicado;

                $payInt = round(min($remaining, max(0, $apagarInt)), 2);
                $remaining -= $payInt;
                $payCap = round(min($remaining, max(0, $apagarCap)), 2);
                $remaining -= $payCap;
            } else {
                // BRANCH NORMAL: capital primero, interés segundo
                $apagarCap = (float) $ins->importe_cuota - (float) $ins->importe_aplicado;
                $apagarInt = (float) $ins->importe_interes - (float) $ins->interes_aplicado;

                $payCap = round(min($remaining, max(0, $apagarCap)), 2);
                $remaining -= $payCap;
                $payInt = round(min($remaining, max(0, $apagarInt)), 2);
                $remaining -= $payInt;
            }

            if ($payCap > 0.001) {
                $opPagoCapital = true;
            }

            // Excedente de redondeo (cuota uniforme): siempre al final
            $apagarExc = (float) $ins->importe_excedente - (float) $ins->excedente_aplicado;
            $payExc = round(min($remaining, max(0, $apagarExc)), 2);
            $remaining -= $payExc;

            $rows[] = ['ins' => $ins, 'cap' => $payCap, 'int' => $payInt, 'exc' => $payExc];

            if ($payCap > 0.001 || $payInt > 0.001 || $payExc > 0.001) {
                $tocadas++;
            }
            if ($payCap > 0.001) {
                $cuotas[] = (int) $ins->num_cuota;
                $capital += $payCap;
            }
            $interes += $payInt;
            $excedente += $payExc;
        }

        return [
            'rows' => $rows,
            'capital' => round($capital, 2),
            'interes' => round($interes, 2),
            'excedente' => round($excedente, 2),
            'cuotas' => $cuotas,
        ];
    }
}
