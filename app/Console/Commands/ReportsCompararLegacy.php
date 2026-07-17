<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación diaria legacy ↔ sistema nuevo (doble digitación).
 *
 * Compara, para una fecha o rango:
 *   1. PAGOS por crédito: capital / interés / mora (huaca_ingreso modo CREDITO
 *      vs payments). Detecta pagos que faltan en un sistema y desgloses distintos.
 *   2. GASTOS caja 1 (huaca_entrada Fijos/Otros vs expenses caja=1).
 *   3. INGRESOS caja 1 (huaca_ingreso Fijos/Otros vs incomes caja=1).
 *   4. CAJA 3 (huaca_ingreso3/huaca_entrada3 vs incomes/expenses caja=3).
 *   5. CRÉDITOS OTORGADOS (huaca_cab_cuentacorriente vs credits).
 *
 * Sale con código 1 si hay diferencias (útil para cron/alertas).
 *
 * Uso (en el droplet o local):
 *   php artisan reports:comparar-legacy                      # hoy
 *   php artisan reports:comparar-legacy --fecha=2026-07-16
 *   php artisan reports:comparar-legacy --desde=2026-07-13 --hasta=2026-07-17
 */
class ReportsCompararLegacy extends Command
{
    protected $signature = 'reports:comparar-legacy
        {--fecha= : Fecha a comparar (YYYY-MM-DD, por defecto hoy)}
        {--desde= : Inicio de rango (YYYY-MM-DD)}
        {--hasta= : Fin de rango (YYYY-MM-DD, por defecto igual a --desde)}';

    protected $description = 'Concilia la digitación del día entre el legacy (huacachi_prestamo) y el sistema nuevo: pagos, gastos, ingresos y créditos.';

    private int $issues = 0;

    public function handle(): int
    {
        try {
            DB::connection('legacy')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('No hay conexión a la BD legacy. Configura DB_LEGACY_HOST/DATABASE/USERNAME/PASSWORD en el .env.');
            $this->line('  Detalle: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('desde')) {
            $desde = Carbon::parse($this->option('desde'));
            $hasta = Carbon::parse($this->option('hasta') ?: $this->option('desde'));
        } else {
            $desde = $hasta = Carbon::parse($this->option('fecha') ?: now()->format('Y-m-d'));
        }

        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            $this->compararDia($d->format('Y-m-d'));
        }

        $this->newLine();
        if ($this->issues === 0) {
            $this->info('✓ TODO CUADRA — sin diferencias entre legacy y el sistema nuevo.');

            return self::SUCCESS;
        }

        $this->warn("✗ {$this->issues} diferencia(s) encontrada(s). Revisar arriba qué falta digitar o ajustar.");

        return self::FAILURE;
    }

    private function compararDia(string $fecha): void
    {
        $this->newLine();
        $this->line('══════ CONCILIACIÓN '.$fecha.' ══════');

        $this->compararPagos($fecha);
        $this->compararMovimientos(
            'GASTOS CAJA 1',
            $this->legacyRows('entrada', $fecha, ['Fijos', 'Otros']),
            $this->nuevoRows('expenses', $fecha, 1, ['Fijos', 'Otros'])
        );
        $this->compararMovimientos(
            'INGRESOS CAJA 1 (Fijos/Otros)',
            $this->legacyRows('ingreso', $fecha, ['Fijos', 'Otros'], 'fechaentrada'),
            $this->nuevoRows('incomes', $fecha, 1, ['Fijos', 'Otros'])
        );
        $this->compararMovimientos(
            'INGRESOS CAJA 3',
            $this->legacyRows('ingreso3', $fecha, null, 'fechaentrada'),
            $this->nuevoRows('incomes', $fecha, 3, null)
        );
        $this->compararMovimientos(
            'GASTOS CAJA 3',
            $this->legacyRows('entrada3', $fecha, null),
            $this->nuevoRows('expenses', $fecha, 3, null)
        );
        $this->compararCreditos($fecha);
    }

    // ─── 1) PAGOS por crédito ──────────────────────────────────────────

    private function compararPagos(string $fecha): void
    {
        $leg = DB::connection('legacy')->table('ingreso')
            ->where('fechaentrada', $fecha)->where('modo', 'CREDITO')
            ->selectRaw("nroentrada as credito,
                SUM(CASE WHEN documento='CAPITAL' THEN totalgeneral ELSE 0 END) cap,
                SUM(CASE WHEN documento='INTERES' THEN totalgeneral ELSE 0 END) info,
                SUM(CASE WHEN LEFT(documento,4)='MORA' THEN totalgeneral ELSE 0 END) mora,
                SUM(CASE WHEN documento='EXCEDENTE' THEN totalgeneral ELSE 0 END) exc")
            ->groupBy('nroentrada')->get()->keyBy('credito');

        $nue = DB::table('payments')
            ->where('fecha', $fecha)->where('modo', 'CREDITO')
            ->selectRaw("credit_id as credito,
                SUM(CASE WHEN tipo='CAPITAL' THEN monto ELSE 0 END) cap,
                SUM(CASE WHEN tipo='INTERES' THEN monto ELSE 0 END) info,
                SUM(CASE WHEN tipo='MORA' THEN monto ELSE 0 END) mora,
                SUM(CASE WHEN tipo='EXCEDENTE' THEN monto ELSE 0 END) exc")
            ->groupBy('credit_id')->get()->keyBy('credito');

        $sumL = fn ($c) => round((float) $c->cap + (float) $c->info + (float) $c->mora + (float) $c->exc, 2);
        $totalL = round($leg->sum($sumL), 2);
        $totalN = round($nue->sum($sumL), 2);

        $this->line(sprintf('─ PAGOS: legacy %d créditos · S/ %s   |   nuevo %d créditos · S/ %s',
            $leg->count(), number_format($totalL, 2), $nue->count(), number_format($totalN, 2)));

        foreach ($leg->keys()->merge($nue->keys())->unique()->sort() as $cid) {
            $l = $leg->get($cid);
            $n = $nue->get($cid);

            if ($l && ! $n) {
                $this->diferencia(sprintf('crédito %d: FALTA EN NUEVO — legacy: cap %s + int %s + mora %s = S/ %s',
                    $cid, number_format($l->cap, 2), number_format($l->info, 2), number_format($l->mora, 2), number_format($sumL($l), 2)));

                continue;
            }
            if ($n && ! $l) {
                $this->diferencia(sprintf('crédito %d: FALTA EN LEGACY — nuevo: cap %s + int %s + mora %s = S/ %s',
                    $cid, number_format($n->cap, 2), number_format($n->info, 2), number_format($n->mora, 2), number_format($sumL($n), 2)));

                continue;
            }

            $dTot = round($sumL($l) - $sumL($n), 2);
            $dCap = round((float) $l->cap - (float) $n->cap, 2);
            $dInt = round((float) $l->info - (float) $n->info, 2);
            $dMora = round((float) $l->mora - (float) $n->mora, 2);

            if (abs($dTot) > 0.005) {
                $this->diferencia(sprintf('crédito %d: MONTO DISTINTO — legacy S/ %s vs nuevo S/ %s (diff %s)',
                    $cid, number_format($sumL($l), 2), number_format($sumL($n), 2), number_format($dTot, 2)));
            } elseif (abs($dCap) > 0.005 || abs($dInt) > 0.005 || abs($dMora) > 0.005) {
                $this->diferencia(sprintf('crédito %d: DESGLOSE DISTINTO (total igual S/ %s) — cap L/N %s/%s · int L/N %s/%s · mora L/N %s/%s',
                    $cid, number_format($sumL($l), 2),
                    number_format($l->cap, 2), number_format($n->cap, 2),
                    number_format($l->info, 2), number_format($n->info, 2),
                    number_format($l->mora, 2), number_format($n->mora, 2)));
            }
        }
    }

    // ─── 2-4) Movimientos de caja (gastos/ingresos) ────────────────────

    /** @return Collection<int, object{modo:string,total:float,detalle:string}> */
    private function legacyRows(string $tabla, string $fecha, ?array $modos, string $colFecha = 'fechaentrada'): Collection
    {
        $q = DB::connection('legacy')->table($tabla)->where($colFecha, $fecha);
        if ($modos) {
            $q->whereIn('modo', $modos);
        }

        return $q->get(['modo', 'totalgeneral', 'detalle'])
            ->map(fn ($r) => (object) ['modo' => (string) $r->modo, 'total' => round((float) $r->totalgeneral, 2), 'detalle' => (string) $r->detalle]);
    }

    /** @return Collection<int, object{modo:string,total:float,detalle:string}> */
    private function nuevoRows(string $tabla, string $fecha, int $caja, ?array $modos): Collection
    {
        $q = DB::table($tabla)->where('caja', $caja)->whereDate('date', $fecha);
        if ($modos) {
            $q->whereIn('modo', $modos);
        }

        return $q->get(['modo', 'total', 'detail'])
            ->map(fn ($r) => (object) ['modo' => (string) $r->modo, 'total' => round((float) $r->total, 2), 'detalle' => (string) ($r->detail ?? '')]);
    }

    private function compararMovimientos(string $titulo, Collection $leg, Collection $nue): void
    {
        $this->line(sprintf('─ %s: legacy %d filas · S/ %s   |   nuevo %d filas · S/ %s',
            $titulo, $leg->count(), number_format($leg->sum('total'), 2),
            $nue->count(), number_format($nue->sum('total'), 2)));

        if (abs($leg->sum('total') - $nue->sum('total')) < 0.005 && $leg->count() === $nue->count()) {
            return;
        }

        // Empareja por (modo, total): lo que sobre en cada lado es lo faltante.
        $restantes = $nue->map(fn ($r) => clone $r)->all();
        foreach ($leg as $l) {
            foreach ($restantes as $k => $n) {
                if ($n->modo === $l->modo && abs($n->total - $l->total) < 0.005) {
                    unset($restantes[$k]);

                    continue 2;
                }
            }
            $this->diferencia(sprintf('FALTA EN NUEVO — %s S/ %s | %s',
                $l->modo, number_format($l->total, 2), mb_substr($l->detalle, 0, 70)));
        }
        foreach ($restantes as $n) {
            $this->diferencia(sprintf('FALTA EN LEGACY — %s S/ %s | %s',
                $n->modo, number_format($n->total, 2), mb_substr($n->detalle, 0, 70)));
        }
    }

    // ─── 5) Créditos otorgados ─────────────────────────────────────────

    private function compararCreditos(string $fecha): void
    {
        $leg = DB::connection('legacy')->table('cab_cuentacorriente')
            ->where('fechaactua', $fecha)->get(['id', 'importe'])->keyBy('id');
        $nue = DB::table('credits')
            ->whereDate('fecha_actualizacion', $fecha)->get(['id', 'importe'])->keyBy('id');

        $this->line(sprintf('─ CRÉDITOS OTORGADOS: legacy %d · S/ %s   |   nuevo %d · S/ %s',
            $leg->count(), number_format($leg->sum('importe'), 2),
            $nue->count(), number_format($nue->sum('importe'), 2)));

        foreach ($leg->keys()->merge($nue->keys())->unique()->sort() as $id) {
            $l = $leg->get($id);
            $n = $nue->get($id);
            if ($l && ! $n) {
                $this->diferencia(sprintf('crédito %d: FALTA EN NUEVO — importe S/ %s', $id, number_format($l->importe, 2)));
            } elseif ($n && ! $l) {
                $this->diferencia(sprintf('crédito %d: FALTA EN LEGACY — importe S/ %s', $id, number_format($n->importe, 2)));
            } elseif (abs((float) $l->importe - (float) $n->importe) > 0.005) {
                $this->diferencia(sprintf('crédito %d: IMPORTE DISTINTO — legacy S/ %s vs nuevo S/ %s',
                    $id, number_format($l->importe, 2), number_format($n->importe, 2)));
            }
        }
    }

    private function diferencia(string $msg): void
    {
        $this->issues++;
        $this->warn('  ✗ '.$msg);
    }
}
