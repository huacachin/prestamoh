<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Port FIEL del motor de distribución del legacy (pagossmasivo.php).
 *
 * Dado el monto de UNA operación y el estado de las cuotas impagas
 * (flpago=0) en ese momento, genera las mismas filas de caja que el legacy
 * escribe en huaca_ingreso / huaca_det_masivo: lista de (num_cuota, C|I,
 * monto), en el mismo orden de inserción.
 *
 * Cada rama replica el original línea por línea, INCLUIDOS sus defectos
 * deliberadamente (son el comportamiento de producción desde hace años y la
 * homologación exige reproducirlos):
 *  - montoante para cuota con capital aplicado NO resta el interés aplicado
 *    (L53-56).
 *  - En cuota completa, la fila INTERES sale por el importe COMPLETO aunque
 *    hubiera interés ya aplicado; el desfase lo compensa el carry
 *    $grbdomonto restando de la fila CAPITAL (L73-75, L96).
 *  - La cuota parcial-final con capital previo usa el montototap COMPLETO
 *    como disponible (L113).
 *  - La rama mensual-1-cuota puede emitir una fila CAPITAL negativa
 *    (L452→L474).
 *
 * Referencias de línea = pagossmasivo.php del legacy.
 * Validación: `php artisan payments:backtest-legacy` (replay del histórico).
 */
final class LegacyEngine
{
    private const EPS = 1e-9;

    /**
     * @param  float  $montototap  monto digitado de la operación (lo que se distribuye)
     * @param  iterable<object>  $cuotas  cuotas con flpago=0, en orden de id, con
     *                                    num_cuota, importe_cuota, importe_interes, importe_aplicado (importeapli),
     *                                    interes_aplicado (aplicado)
     * @param  bool  $esMensualUnaCuota  tipoplani=3 && cuotas=1 (Rama C)
     * @return array<int, array{num: int, tipo: 'C'|'I', monto: float}>
     */
    public function distribuir(float $montototap, iterable $cuotas, bool $esMensualUnaCuota): array
    {
        return $esMensualUnaCuota
            ? $this->ramaC($montototap, $cuotas)
            : $this->ramaAB($montototap, $cuotas);
    }

    /**
     * Ramas A y B — semanal(1)/diario(4) [L38-231] y mensual multicuota [L232-418].
     * Idénticas en la generación de filas de caja; capital primero.
     */
    private function ramaAB(float $montototap, iterable $cuotas): array
    {
        $rows = [];
        $salxocuota = $montototap;   // saldo corredor (L26)
        $monPagado = 0.0;
        $grbdomonto = 0.0;           // carry de capital sobre-aplicado (L75)

        foreach ($cuotas as $c) {
            $cap = (float) $c->importe_cuota;
            $int = (float) $c->importe_interes;
            $apli = (float) $c->importe_aplicado;

            // ── PASO 1: descontar la cuota del saldo corredor (L53-60) ──
            if ($apli > 0) {
                $montoante = ($cap + $int) - $apli;   // ¡NO resta interés aplicado!
                $salxocuota -= $montoante;
            } else {
                $montoante = $cap;
                $salxocuota -= ($cap + $int);
            }

            // ── PASO 2: alcanza para la cuota completa (L61) ──
            if ($salxocuota >= 0.01) {
                if (abs($cap) > self::EPS) {  // cuota solo-interés: se salta sin registrar (L63)
                    $montocuotaM2 = $apli > 0 ? ($montoante - $int) : $montoante; // L65-71

                    if ($montocuotaM2 < 0) {
                        $grbdomonto = abs($montocuotaM2);  // carry; sin fila CAPITAL (L73-75)
                    } else {
                        $montocuotaM2 -= $grbdomonto;      // descuenta carry previo (L76)
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($montocuotaM2, 2)]; // L78 (inserta aun 0.00)
                        $grbdomonto = 0.0;
                        $monPagado += $montocuotaM2;       // L91
                    }
                    // INTERES completo, SIEMPRE en este paso (L96)
                    $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($int, 2)];
                    $monPagado += $int;                    // L107
                }

                continue;
            }

            // ── PASO 3: cuota parcial-final ──
            if (abs($salxocuota) > self::EPS) {           // L110: salxocuota <> 0
                if ($apli > 0) {
                    $salxocuota2 = $montototap;           // L113: usa el monto COMPLETO (defecto replicado)
                } else {
                    $salxocuota2 = ($cap + $int) - abs($salxocuota);  // L122: disponible para esta cuota
                }

                if ($grbdomonto > 0) {                    // L129: carry pendiente
                    $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($salxocuota2 - $grbdomonto, 2)]; // L132
                    $grbdomonto = 0.0;
                } else {                                  // L144: capital primero, luego interés
                    $apagarC = $cap - $apli;              // L147
                    $saldoDdPg = $apagarC - $salxocuota2; // L149
                    if ($saldoDdPg >= 0) {
                        $impoCuta = $salxocuota2;
                        $impoIntr = 0.0;                  // L151
                    } else {
                        $impoCuta = $apagarC;
                        $impoIntr = abs($saldoDdPg);      // L155
                    }
                    if ($impoCuta > 0) {
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($impoCuta, 2)]; // L161
                    }
                    if ($impoIntr > 0) {
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($impoIntr, 2)]; // L174
                    }
                }
                break;                                    // L187
            }

            // L188: el pago terminó EXACTO al llegar a esta cuota
            $disponible = $montototap - $monPagado;       // L192
            $apagarC = $cap - $apli;                      // L193
            $saldoDdPg = $apagarC - $disponible;          // L194
            if ($saldoDdPg >= 0) {
                $impoCuta = $disponible;
                $impoIntr = 0.0;                          // L195
            } else {
                $impoCuta = $apagarC;
                $impoIntr = abs($saldoDdPg);              // L199
            }
            if ($impoCuta > 0) {
                $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($impoCuta, 2)]; // L205
            }
            if ($impoIntr > 0) {
                $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($impoIntr, 2)]; // L217
            }
            break;                                        // L227
        }

        return $rows;
    }

    /**
     * Rama C — mensual con cuotas=1 [L419-604]. El tracker de avance es el
     * interés aplicado ('aplicado') y el orden es INTERÉS PRIMERO. Esta rama
     * NO pasa por la normalización posterior: su estado es incremental.
     */
    private function ramaC(float $montototap, iterable $cuotas): array
    {
        $rows = [];
        $salxocuota = $montototap;
        $monPagado = 0.0;
        $grbdomonto = 0.0;

        foreach ($cuotas as $c) {
            $cap = (float) $c->importe_cuota;
            $int = (float) $c->importe_interes;
            $aplicado = (float) $c->interes_aplicado;

            // ── PASO 1 (L432-439): usa 'aplicado', no importeapli ──
            if ($aplicado > 0) {
                $montoante = ($cap + $int) - $aplicado;
                $salxocuota -= $montoante;
            } else {
                $montoante = $cap;
                $salxocuota -= ($cap + $int);
            }

            // ── PASO 2 (L440): cuota completa — INTERÉS PRIMERO ──
            if ($salxocuota >= 0.01) {
                if (abs($cap) > self::EPS) {
                    $montocuotaM2 = $aplicado > 0 ? ($montoante - $int) : $montoante; // L444-451

                    if ($montocuotaM2 < 0) {
                        $grbdomonto = abs($montocuotaM2);  // L452: sin fila INTERES en este caso
                    } else {
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($int, 2)]; // L460
                        $monPagado += $int;
                    }
                    // CAPITAL incondicional (L474-475): con carry recién seteado
                    // puede salir NEGATIVO — defecto replicado tal cual.
                    $montocuotaM2 -= $grbdomonto;
                    $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($montocuotaM2, 2)];
                    $grbdomonto = 0.0;
                    $monPagado += $montocuotaM2;           // L489
                }

                continue;
            }

            // ── PASO 3: parcial-final — interés primero (L493-560) ──
            if (abs($salxocuota) > self::EPS) {
                if ($aplicado > 0) {
                    $salxocuota2 = $montototap;            // L495 (defecto replicado)
                } else {
                    $salxocuota2 = ($cap + $int) - abs($salxocuota); // L500
                }

                if ($grbdomonto > 0) {                     // L505
                    $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($salxocuota2 - $grbdomonto, 2)]; // L508
                    $grbdomonto = 0.0;
                } else {                                   // L518: interés primero
                    $apagarC = $int - $aplicado;           // L520: pendiente de INTERÉS
                    $saldoDdPg = $apagarC - $salxocuota2;  // L521
                    if ($saldoDdPg >= 0) {
                        $impoIntr = $salxocuota2;
                        $impoCuta = 0.0;                   // L522
                    } else {
                        $impoIntr = $apagarC;
                        $impoCuta = abs($saldoDdPg);       // L526
                    }
                    if ($impoCuta > 0) {
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($impoCuta, 2)]; // L532
                    }
                    if ($impoIntr > 0) {
                        $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($impoIntr, 2)]; // L546
                    }
                }
                break;                                     // L560
            }

            // L561: terminó exacto
            $disponible = $montototap - $monPagado;        // L562
            $apagarC = $int - $aplicado;                   // L563
            $saldoDdPg = $apagarC - $disponible;
            if ($saldoDdPg >= 0) {
                $impoIntr = $disponible;
                $impoCuta = 0.0;
            } else {
                $impoIntr = $apagarC;
                $impoCuta = abs($saldoDdPg);
            }
            if ($impoCuta > 0) {
                $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'C', 'monto' => round($impoCuta, 2)]; // L575
            }
            if ($impoIntr > 0) {
                $rows[] = ['num' => (int) $c->num_cuota, 'tipo' => 'I', 'monto' => round($impoIntr, 2)]; // L588
            }
            break;                                         // L599
        }

        return $rows;
    }

    /**
     * NORMALIZACIÓN del cronograma (L716-847): el legacy la corre en CADA
     * carga de página y es la fuente de verdad de importeapli/aplicado/
     * flpago para las ramas A/B (la Rama C mantiene estado incremental y
     * NUNCA se normaliza; créditos cancelados —estado=0— tampoco).
     *
     * Greedy sobre el total de caja: cuota a cuota, si el dinero cubre
     * capital+interés → llena y marca pagada; en la cuota donde se corta:
     * capital primero (hasta importecuota) y el resto a interés; las
     * posteriores quedan en cero.
     *
     * @param  array<int, object>  $cuotas  en orden de id; muta importe_aplicado,
     *                                      interes_aplicado y flpago de cada objeto
     * @param  float  $totalCaja  SUM de ingresos CAPITAL+INTERES históricos
     */
    public function normalizar(array $cuotas, float $totalCaja): void
    {
        $totalRP = $totalCaja;

        foreach ($cuotas as $c) {
            $cap = (float) $c->importe_cuota;
            $int = (float) $c->importe_interes;

            // Reset de la fila (L758)
            $c->importe_aplicado = 0.0;
            $c->interes_aplicado = 0.0;
            $c->flpago = 0;

            $montoCuot = $cap + $int;                      // L759
            if ($totalRP - $montoCuot >= 0) {              // L762: cubre la cuota entera
                $c->importe_aplicado = $cap;
                $c->interes_aplicado = $int;
                $c->flpago = 1;                            // L764
                $totalRP = round($totalRP - $montoCuot, 2);
            } else {                                       // L767: cuota donde se corta
                if ($totalRP > $cap) {
                    $c->importe_aplicado = $cap;
                    $c->interes_aplicado = round($totalRP - $cap, 2); // L791
                } else {
                    $c->importe_aplicado = round($totalRP, 2);        // L795
                }
                $totalRP = 0.0;
                // Cierre flpago (L806-813): pagada si quedó exacta
                if (abs($c->importe_aplicado - $cap) < 0.005 && abs($c->interes_aplicado - $int) < 0.005) {
                    $c->flpago = 1;
                }
            }
        }
    }
}
