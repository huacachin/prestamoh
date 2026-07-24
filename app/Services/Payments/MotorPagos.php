<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;

/**
 * Fachada del motor de distribución de pagos: elige el motor correcto según
 * el esquema del crédito y devuelve un resultado UNIFICADO con las dos
 * verdades que el legacy mantiene por separado:
 *
 *  - 'caja': las filas que van a `payments` (lo que leen TODOS los reportes
 *    de caja) — crudas, tal como las emitiría el legacy en huaca_ingreso.
 *  - 'rows': la APLICACIÓN al cronograma por cuota (importe_aplicado /
 *    interes_aplicado / excedente_aplicado + mass_deletion_details) —
 *    normalizada como el det del legacy (topada en los importes de cada
 *    cuota, exceso empujado a la siguiente como capital).
 *
 * En el flujo común ambas coinciden; divergen exactamente donde el legacy
 * también diverge (p. ej. operación sobre cuota con capital cubierto: caja
 * registra INTERES por el importe completo, el cronograma topa y empuja).
 *
 * Selección de motor:
 *  - Crédito de CUOTA UNIFORME (tiene excedente; esquema solo del sistema
 *    nuevo) → DistribucionPago. El legacy no conoce este esquema, no hay
 *    homologación posible fila a fila.
 *  - Resto (créditos espejo del legacy) → LegacyEngine, port validado con
 *    `payments:backtest-legacy` (99.95% en créditos vigentes; 100% en 2026).
 */
final class MotorPagos
{
    public function __construct(
        private readonly LegacyEngine $legacy,
        private readonly DistribucionPago $uniforme,
    ) {}

    /**
     * @param  iterable<object>  $unpaid  cuotas impagas en orden (num_cuota,
     *                                    importe_cuota, importe_interes, importe_excedente, importe_aplicado,
     *                                    interes_aplicado, excedente_aplicado)
     * @return array{
     *   caja: array<int, array{num:int, tipo:'C'|'I'|'E', monto:float}>,
     *   rows: array<int, array{ins:mixed, cap:float, int:float, exc:float}>,
     *   capital: float, interes: float, excedente: float, cuotas: array<int,int>
     * }
     */
    public function distribuir(float $monto, iterable $unpaid, bool $esMensualUnaCuota, bool $esCuotaUniforme): array
    {
        $unpaid = is_array($unpaid) ? $unpaid : iterator_to_array($unpaid, false);

        return $esCuotaUniforme
            ? $this->viaUniforme($monto, $unpaid, $esMensualUnaCuota)
            : $this->viaLegacy($monto, $unpaid, $esMensualUnaCuota);
    }

    /** Cuota uniforme: caja y aplicación son idénticas (comportamiento actual). */
    private function viaUniforme(float $monto, array $unpaid, bool $esMensualUnaCuota): array
    {
        $d = $this->uniforme->distribuir($monto, $unpaid, $esMensualUnaCuota);

        $caja = [];
        foreach ($d['rows'] as $r) {
            $num = (int) $r['ins']->num_cuota;
            if ($r['cap'] > 0.001) {
                $caja[] = ['num' => $num, 'tipo' => 'C', 'monto' => $r['cap']];
            }
            if ($r['int'] > 0.001) {
                $caja[] = ['num' => $num, 'tipo' => 'I', 'monto' => $r['int']];
            }
            if ($r['exc'] > 0.001) {
                $caja[] = ['num' => $num, 'tipo' => 'E', 'monto' => $r['exc']];
            }
        }

        return $d + ['caja' => $caja];
    }

    /** Créditos espejo del legacy: caja cruda del port + aplicación normalizada. */
    private function viaLegacy(float $monto, array $unpaid, bool $esMensualUnaCuota): array
    {
        $raw = $this->legacy->distribuir($monto, $unpaid, $esMensualUnaCuota);

        // ── Filas de caja: crudas, sin ceros ni negativos ──────────────
        // El legacy llega a emitir filas de 0.00 (rama L78) y, en un edge de
        // la rama mensual-1, negativas. Ni unas ni otras aportan a caja; se
        // omiten y se deja rastro en el log si aparece una negativa.
        $caja = [];
        $rawPorNum = [];   // num → ['C' => monto, 'I' => monto] acumulado
        foreach ($raw as $r) {
            if ($r['monto'] < -0.001) {
                Log::warning('MotorPagos: LegacyEngine emitió fila negativa (edge rama mensual-1)', $r);

                continue;
            }
            if ($r['monto'] < 0.005) {
                continue;
            }
            $caja[] = ['num' => $r['num'], 'tipo' => $r['tipo'], 'monto' => $r['monto']];
            $rawPorNum[$r['num']][$r['tipo']] = round(($rawPorNum[$r['num']][$r['tipo']] ?? 0) + $r['monto'], 2);
        }

        // ── Aplicación al cronograma ───────────────────────────────────
        $rows = [];
        $cuotas = [];
        $capital = $interes = 0.0;

        if ($esMensualUnaCuota) {
            // Rama C del legacy: estado incremental, sin normalización.
            // C → capital, I → interés, tal cual (espejo de sus UPDATE).
            foreach ($unpaid as $ins) {
                $num = (int) $ins->num_cuota;
                $cap = $rawPorNum[$num]['C'] ?? 0.0;
                $int = $rawPorNum[$num]['I'] ?? 0.0;
                if ($cap < 0.001 && $int < 0.001) {
                    continue;
                }
                $rows[] = ['ins' => $ins, 'cap' => $cap, 'int' => $int, 'exc' => 0.0];
            }
        } else {
            // Normalización del legacy (det L758+): por cuota en orden,
            // capital primero hasta su importe, interés después hasta el
            // suyo, exceso empujado a la siguiente cuota.
            $carry = 0.0;
            foreach ($unpaid as $ins) {
                $num = (int) $ins->num_cuota;
                $entrante = round($carry
                    + ($rawPorNum[$num]['C'] ?? 0.0)
                    + ($rawPorNum[$num]['I'] ?? 0.0), 2);
                if ($entrante < 0.001) {
                    continue;
                }

                $capPend = max(0.0, (float) $ins->importe_cuota - (float) $ins->importe_aplicado);
                $intPend = max(0.0, (float) $ins->importe_interes - (float) $ins->interes_aplicado);

                $aplCap = round(min($entrante, $capPend), 2);
                $resto = round($entrante - $aplCap, 2);
                $aplInt = round(min($resto, $intPend), 2);
                $carry = round($resto - $aplInt, 2);

                if ($aplCap > 0.001 || $aplInt > 0.001) {
                    $rows[] = ['ins' => $ins, 'cap' => $aplCap, 'int' => $aplInt, 'exc' => 0.0];
                }
            }
            if ($carry > 0.01) {
                // Sobrepago más allá del cronograma: el front lo bloquea
                // (monto ≤ saldo) — si llega aquí, dejar rastro.
                Log::warning("MotorPagos: excedente sin cuota destino ({$carry})");
            }
        }

        // ── Totales para la vista previa (clasificación de CAJA) ───────
        foreach ($caja as $r) {
            if ($r['tipo'] === 'C') {
                $capital += $r['monto'];
                if (! in_array($r['num'], $cuotas, true)) {
                    $cuotas[] = $r['num'];
                }
            } else {
                $interes += $r['monto'];
            }
        }

        return [
            'caja' => $caja,
            'rows' => $rows,
            'capital' => round($capital, 2),
            'interes' => round($interes, 2),
            'excedente' => 0.0,
            'cuotas' => $cuotas,
        ];
    }
}
