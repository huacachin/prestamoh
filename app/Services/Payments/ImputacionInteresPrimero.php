<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Motor de distribución con INTERÉS PRIMERO — regla de negocio propia del
 * sistema nuevo (decisión del 12/08/2026), que reemplaza en producción al
 * espejo del legacy.
 *
 * Cascada por cuota, en orden de cronograma:
 *   interés → capital → excedente, y el remanente pasa a la cuota siguiente
 *   empezando otra vez por su interés.
 *
 * Propiedades que la diferencian del motor legacy:
 *  - SIN ramas por tipo de crédito ni "fase de interés": una sola regla para
 *    semanal, diario y mensual (el mensual-1-cuota ya era interés-primero,
 *    así que para él nada cambia).
 *  - INDEPENDIENTE DEL CAMINO: los casilleros a llenar (int/cap/exc de cada
 *    cuota) tienen orden fijo y cada operación continúa donde quedó la
 *    anterior. Partir un cobro en N operaciones — en cualquier orden de
 *    montos — produce exactamente el mismo resultado que una sola armada.
 *    Se acabó la "regla del sobrante" que dependía de cómo se digitaba.
 *  - Caja y cronograma son UNA sola verdad: las filas de payments se derivan
 *    de esta misma aplicación (no hay port crudo aparte).
 *
 * Consecuencia asumida por el negocio: el desglose capital/interés de los
 * pagos parciales deja de cuadrar contra el legacy (él mantiene sus ramas).
 * El total cobrado y la deuda del cliente cuadran igual que siempre.
 *
 * Función PURA: no toca BD ni estado.
 */
final class ImputacionInteresPrimero
{
    /**
     * Mismo contrato que DistribucionPago::distribuir().
     *
     * @param  iterable  $unpaid  cuotas impagas en orden (objetos con
     *                            num_cuota, importe_cuota, importe_interes, importe_excedente,
     *                            importe_aplicado, interes_aplicado, excedente_aplicado)
     * @return array{
     *   rows: array<int, array{ins: mixed, cap: float, int: float, exc: float}>,
     *   capital: float, interes: float, excedente: float, cuotas: array<int, int>
     * }
     */
    public function distribuir(float $monto, iterable $unpaid): array
    {
        $rows = [];
        $cuotas = [];
        $capital = $interes = $excedente = 0.0;

        $remaining = $monto;

        foreach ($unpaid as $ins) {
            if ($remaining < 0.01) {
                break;
            }

            $pendInt = max(0.0, (float) $ins->importe_interes - (float) $ins->interes_aplicado);
            $pendCap = max(0.0, (float) $ins->importe_cuota - (float) $ins->importe_aplicado);
            $pendExc = max(0.0, (float) $ins->importe_excedente - (float) $ins->excedente_aplicado);

            $payInt = round(min($remaining, $pendInt), 2);
            $remaining = round($remaining - $payInt, 2);

            $payCap = round(min($remaining, $pendCap), 2);
            $remaining = round($remaining - $payCap, 2);

            $payExc = round(min($remaining, $pendExc), 2);
            $remaining = round($remaining - $payExc, 2);

            if ($payInt < 0.001 && $payCap < 0.001 && $payExc < 0.001) {
                continue;
            }

            $rows[] = ['ins' => $ins, 'cap' => $payCap, 'int' => $payInt, 'exc' => $payExc];

            if ($payCap > 0.001) {
                $cuotas[] = (int) $ins->num_cuota;
            }
            $capital += $payCap;
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
