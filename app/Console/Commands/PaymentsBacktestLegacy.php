<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\DistribucionPago;
use App\Services\Payments\LegacyEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backtest del motor de distribución contra el histórico completo del legacy.
 *
 * Para cada operación de cobro (cab_masivo, en orden) reconstruye el
 * estado del cronograma EN ESE MOMENTO y compara la predicción del motor,
 * fila por fila (cuota/tipo/monto), contra lo que el legacy escribió de
 * verdad (det_masivo tipo C/I ↔ huaca_ingreso CAPITAL/INTERES).
 *
 * Reconstrucción del estado (espejo del legacy):
 *  - Ramas A/B (semanal/diario/mensual multicuota): normalización greedy
 *    desde el total de caja acumulado (pagossmasivo L716+), recalculada tras
 *    cada operación — igual que el legacy la corre en cada carga de página.
 *  - Rama C (mensual 1 cuota): estado incremental (C→importeapli, I→aplicado),
 *    porque esa rama nunca se normaliza.
 * El estado avanza con los montos REALES del histórico, así que un error del
 * motor en una operación no contamina la evaluación de las siguientes.
 *
 * Se evalúan dos motores por operación:
 *  - port  = LegacyEngine (port fiel de pagossmasivo)
 *  - motor = DistribucionPago (el motor en producción del sistema nuevo)
 */
class PaymentsBacktestLegacy extends Command
{
    protected $signature = 'payments:backtest-legacy
                            {--desde= : Solo evaluar ops con fecha >= (YYYY-MM-DD); el estado igual se reconstruye completo}
                            {--credit= : Solo un crédito (para depurar; imprime cada op)}
                            {--activos : Solo créditos vigentes (situacion=Vigente en legacy) — los que seguirán recibiendo pagos}
                            {--limit= : Máximo de créditos a procesar}
                            {--muestras=10 : Cuántos mismatches de ejemplo mostrar}';

    protected $description = 'Replay de las operaciones históricas del legacy contra el motor de distribución, fila por fila';

    /** @var array<int, array{ops:int, port:int, actual:int}> */
    private array $porAnio = [];

    /** @var array<string, int> */
    private array $porMotivo = [];

    private array $ejemplos = [];

    private bool $debug = false;

    public function handle(LegacyEngine $port, DistribucionPago $actual): int
    {
        $desde = $this->option('desde');
        $soloCredito = $this->option('credit');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $maxMuestras = (int) $this->option('muestras');
        $this->debug = (bool) $soloCredito;

        $leg = DB::connection('legacy');

        $creditosQ = $leg->table('cab_masivo')->select('codpres')->distinct()->orderBy('codpres');
        if ($soloCredito) {
            $creditosQ->where('codpres', (int) $soloCredito);
        }
        if ($this->option('activos')) {
            $creditosQ->whereIn('codpres', $leg->table('cab_cuentacorriente')
                ->where('situacion', 'Vigente')->pluck('id'));
        }
        $creditos = $creditosQ->pluck('codpres');
        if ($limit) {
            $creditos = $creditos->take($limit);
        }

        $this->info('Créditos a reproducir: '.$creditos->count());
        $bar = $this->debug ? null : $this->output->createProgressBar($creditos->count());

        $totOps = $okPort = $okActual = $saltadas = 0;

        foreach ($creditos as $creditId) {
            $r = $this->replayCredito($leg, $port, $actual, (int) $creditId, $desde, $maxMuestras);
            $totOps += $r['ops'];
            $okPort += $r['port'];
            $okActual += $r['actual'];
            $saltadas += $r['saltadas'];
            $bar?->advance();
        }
        $bar?->finish();
        $this->newLine(2);

        $pct = fn (int $ok) => $totOps > 0 ? round($ok * 100 / $totOps, 2).'%' : '-';
        $this->info("Operaciones evaluadas: {$totOps} (saltadas sin C/I: {$saltadas})");
        $this->info('  PORT LegacyEngine:      '.$okPort.'  ('.$pct($okPort).')');
        $this->info('  MOTOR actual (nuevo):   '.$okActual.'  ('.$pct($okActual).')');

        if ($this->porAnio) {
            ksort($this->porAnio);
            $this->table(['Año', 'Ops', 'Port OK', 'Port %', 'Actual OK', 'Actual %'],
                collect($this->porAnio)->map(fn ($v, $k) => [
                    $k, $v['ops'],
                    $v['port'], $v['ops'] ? round($v['port'] * 100 / $v['ops'], 2).'%' : '-',
                    $v['actual'], $v['ops'] ? round($v['actual'] * 100 / $v['ops'], 2).'%' : '-',
                ])->all());
        }

        if ($this->porMotivo) {
            arsort($this->porMotivo);
            $this->warn('Mismatches del PORT por tipo:');
            foreach ($this->porMotivo as $motivo => $n) {
                $this->line("  {$motivo}: {$n}");
            }
        }

        foreach ($this->ejemplos as $e) {
            $this->newLine();
            $this->warn("MISMATCH PORT credito {$e['credit']} op {$e['op']} fecha {$e['fecha']} monto {$e['monto']}");
            $this->line('  real: '.$e['real']);
            $this->line('  port: '.$e['pred']);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{ops:int, port:int, actual:int, saltadas:int}
     */
    private function replayCredito($leg, LegacyEngine $port, DistribucionPago $actualEngine, int $creditId, ?string $desde, int $maxMuestras): array
    {
        $cab = $leg->table('cab_cuentacorriente')->where('id', $creditId)->first(['tipoplani', 'cuotas']);
        if (! $cab) {
            return ['ops' => 0, 'port' => 0, 'actual' => 0, 'saltadas' => 0];
        }
        $esMensual1 = ((int) $cab->tipoplani === 3 && (int) $cab->cuotas === 1);

        // Cronograma prístino (importes actuales, en orden de id como el cursor legacy)
        $det = $leg->table('det_cuentacorriente')
            ->where('idcab', $creditId)->orderBy('id')
            ->get(['id', 'num_cuot', 'importecuota', 'importeinteres']);
        if ($det->isEmpty()) {
            return ['ops' => 0, 'port' => 0, 'actual' => 0, 'saltadas' => 0];
        }

        $estado = [];       // por det_id, en orden de id
        foreach ($det as $c) {
            $estado[] = (object) [
                'det_id' => (int) $c->id,
                'num_cuota' => (int) $c->num_cuot,
                'importe_cuota' => (float) $c->importecuota,
                'importe_interes' => (float) $c->importeinteres,
                'importe_excedente' => 0.0,
                'importe_aplicado' => 0.0,
                'interes_aplicado' => 0.0,
                'excedente_aplicado' => 0.0,
                'flpago' => 0,
            ];
        }
        $ops = $leg->table('cab_masivo')->where('codpres', $creditId)
            ->orderBy('id')->get(['id', 'fecha', 'monto']);
        $deltas = $leg->table('det_masivo')
            ->whereIn('det_masivo.idcab', $ops->pluck('id'))
            ->whereIn('det_masivo.tipo', ['C', 'I'])
            ->orderBy('det_masivo.id')
            ->join('det_cuentacorriente as d', 'd.id', '=', 'det_masivo.codigocuota')
            ->get(['det_masivo.idcab', 'det_masivo.codigocuota', 'det_masivo.montocuota', 'det_masivo.tipo', 'd.num_cuot', 'det_masivo.codigoing'])
            ->groupBy('idcab');

        // TODA la caja histórica del crédito (los créditos viejos tienen
        // pagos por módulos previos a pagossmasivo, sin cab/det_masivo).
        // Mismo filtro que la normalización real (L734): sin MORA*, sin
        // filas 'Gat', empresa=1. El estado del cronograma se alimenta de
        // ESTE total en orden de identrada.
        $ingresos = $leg->table('ingreso')->where('nroentrada', $creditId)
            ->where('empresa', 1)
            ->whereRaw("LEFT(documento,4) <> 'MORA'")
            ->whereRaw("RIGHT(aa,3) <> 'Gat'")
            ->orderBy('identrada')
            ->get(['identrada', 'documento', 'totalgeneral']);

        $nOps = $okPort = $okActual = $salt = 0;

        foreach ($ops as $op) {
            $rows = $deltas->get($op->id, collect());
            $montoDist = round((float) $rows->sum('montocuota'), 2);

            if ($montoDist < 0.01 || $rows->isEmpty()) {
                $salt++;

                continue;
            }

            // Estado del cronograma ANTES de esta operación: caja acumulada
            // hasta la fila de ingreso previa a la primera de esta op.
            $minIng = (int) $rows->min('codigoing');
            $previos = $ingresos->filter(fn ($i) => (int) $i->identrada < $minIng);
            if ($esMensual1) {
                // Rama C: incremental por fila (nunca se normaliza)
                foreach ($estado as $e) {
                    $e->importe_aplicado = 0.0;
                    $e->interes_aplicado = 0.0;
                    $e->flpago = 0;
                }
                $this->aplicarIncrementalC($estado, $previos);
            } else {
                $port->normalizar($estado, round((float) $previos->sum('totalgeneral'), 2));
            }

            $evaluar = ! $desde || substr((string) $op->fecha, 0, 10) >= $desde;

            if ($evaluar) {
                $unpaid = array_values(array_filter($estado, fn ($e) => $e->flpago === 0));

                // ── PORT fiel ──
                $predPort = [];
                foreach ($port->distribuir($montoDist, $unpaid, $esMensual1) as $pr) {
                    $predPort[] = $pr['num'].'/'.$pr['tipo'].'/'.number_format($pr['monto'], 2, '.', '');
                }

                // ── Motor actual (adaptado a filas cuota/tipo/monto) ──
                $predActual = [];
                foreach ($actualEngine->distribuir($montoDist, $unpaid, $esMensual1)['rows'] as $pr) {
                    if ($pr['cap'] > 0.001) {
                        $predActual[] = $pr['ins']->num_cuota.'/C/'.number_format($pr['cap'], 2, '.', '');
                    }
                    if ($pr['int'] > 0.001) {
                        $predActual[] = $pr['ins']->num_cuota.'/I/'.number_format($pr['int'], 2, '.', '');
                    }
                }

                $real = $rows->map(fn ($r) => ((int) $r->num_cuot).'/'.$r->tipo.'/'.number_format((float) $r->montocuota, 2, '.', ''))->all();

                $sp = $predPort;
                $sa = $predActual;
                $sr = $real;
                sort($sp);
                sort($sa);
                sort($sr);

                $anio = (int) substr((string) $op->fecha, 0, 4);
                $this->porAnio[$anio] ??= ['ops' => 0, 'port' => 0, 'actual' => 0];
                $this->porAnio[$anio]['ops']++;
                $nOps++;

                if ($sp === $sr) {
                    $okPort++;
                    $this->porAnio[$anio]['port']++;
                } else {
                    $motivo = $this->clasificar($sp, $sr);
                    $this->porMotivo[$motivo] = ($this->porMotivo[$motivo] ?? 0) + 1;
                    if (count($this->ejemplos) < $maxMuestras) {
                        $this->ejemplos[] = [
                            'credit' => $creditId, 'op' => $op->id,
                            'fecha' => substr((string) $op->fecha, 0, 10), 'monto' => $montoDist,
                            'real' => implode(' ', $real), 'pred' => implode(' ', $predPort),
                        ];
                    }
                }
                if ($sa === $sr) {
                    $okActual++;
                    $this->porAnio[$anio]['actual']++;
                }

                if ($this->debug) {
                    $flag = $sp === $sr ? 'OK  ' : 'DIFF';
                    $this->line("[{$flag}] op {$op->id} ".substr((string) $op->fecha, 0, 10)." monto {$montoDist}");
                    $this->line('   real: '.implode(' ', $real));
                    if ($sp !== $sr) {
                        $this->line('   port: '.implode(' ', $predPort));
                    }
                }
            }

        }

        return ['ops' => $nOps, 'port' => $okPort, 'actual' => $okActual, 'saltadas' => $salt];
    }

    /**
     * Rama C (mensual 1 cuota, sin normalización): reconstruye el estado
     * aplicando la caja histórica fila a fila, interés/capital sobre la
     * primera cuota abierta en orden.
     */
    private function aplicarIncrementalC(array $estado, $ingresos): void
    {
        foreach ($ingresos as $ing) {
            $monto = (float) $ing->totalgeneral;
            $esInt = $ing->documento === 'INTERES';

            foreach ($estado as $e) {
                if ($e->flpago === 1) {
                    continue;
                }
                if ($esInt) {
                    $e->interes_aplicado = round($e->interes_aplicado + $monto, 2);
                } else {
                    $e->importe_aplicado = round($e->importe_aplicado + $monto, 2);
                }
                $e->flpago = ($e->importe_aplicado >= $e->importe_cuota - 0.005
                    && $e->interes_aplicado >= $e->importe_interes - 0.005) ? 1 : 0;
                break;
            }
        }
    }

    private function clasificar(array $pred, array $real): string
    {
        $suma = function (array $rows, string $tipo): float {
            $s = 0.0;
            foreach ($rows as $r) {
                [, $t, $m] = explode('/', $r);
                if ($t === $tipo) {
                    $s += (float) $m;
                }
            }

            return round($s, 2);
        };

        $capIgual = abs($suma($pred, 'C') - $suma($real, 'C')) < 0.011;
        $intIgual = abs($suma($pred, 'I') - $suma($real, 'I')) < 0.011;

        if ($capIgual && $intIgual) {
            return 'mismos totales C/I, distinta atribucion por cuota';
        }
        if (abs(($suma($pred, 'C') + $suma($pred, 'I')) - ($suma($real, 'C') + $suma($real, 'I'))) < 0.011) {
            return 'clasificacion C/I distinta (total igual)';
        }

        return 'montos distintos';
    }
}
