<?php

namespace App\Console\Commands;

use App\Models\Concept;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de las copias en CAJA 3 para los movimientos modo='Fijos' creados en
 * Laravel ANTES del fix de doble registro (caja1 + caja3). El legacy, al registrar
 * un Fijos, lo inserta en caja 1 (OTROS MOVIMIENTOS) y en caja 3 (columnas
 * Otros/Egreso de CREDITO en /reports/cash-statistics).
 *
 *   - Egreso Fijos  → copia caja3 con el MISMO monto.
 *   - Ingreso Fijos → copia caja3 con el NETO = monto − (factor_egreso × cantidad).
 *   - 'Otros' → NO lleva copia (igual que el legacy).
 *
 * SEGURIDAD FRENTE AL HISTÓRICO MIGRADO (clave):
 *   Las copias caja3 que trae la migración legacy quedan con `parent_id` NULL; las
 *   que crea Laravel (al guardar o este comando) llevan `parent_id`. Por eso el
 *   "piso" de la migración = MAX(created_at) de las caja3 con parent_id NULL. SOLO
 *   se backfillea lo creado DESPUÉS de ese piso (= nativo de la app). Así nunca se
 *   tocan los miles de Fijos migrados (que en el legacy no tienen copia caja3).
 *
 *   ⚠️ NO usar un --since "a mano": el histórico migrado tiene created_at = fecha de
 *   la migración (reciente), así que un --since anterior a la migración arrastraría
 *   TODO el histórico. Por eso el piso se autodetecta.
 *
 * Idempotente: salta si ya hay copia caja3 enlazada (parent_id) o una hermana
 * migrada (misma fecha/modo/motivo/usuario).
 *
 * Uso:
 *   php artisan cash:backfill-caja3 --dry-run     # previsualizar
 *   php artisan cash:backfill-caja3               # aplicar
 *   php artisan cash:backfill-caja3 --revert --dry-run   # deshacer un backfill mal hecho
 *   php artisan cash:backfill-caja3 --revert
 */
class CashBackfillCaja3 extends Command
{
    protected $signature = 'cash:backfill-caja3
        {--revert : Borra copias caja3 mal creadas (parent_id apuntando a un movimiento migrado).}
        {--dry-run : Muestra qué haría sin escribir.}';

    protected $description = 'Crea (o revierte) las copias en caja 3 de los movimientos Fijos creados en Laravel, sin tocar el histórico migrado.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('revert')) {
            $exp = $this->revert('expenses', $dry);
            $inc = $this->revert('incomes', $dry);
            $this->newLine();
            $this->info(($dry ? 'DRY RUN — ' : '✓ ')."Revertido — Egresos: {$exp} · Ingresos: {$inc} copia(s) caja3 mal creadas.");

            return self::SUCCESS;
        }

        $exp = $this->backfillExpenses($dry);
        $inc = $this->backfillIncomes($dry);

        $this->newLine();
        $this->info(($dry ? 'DRY RUN — ' : '✓ ')."Egresos: {$exp} copia(s) caja3 · Ingresos: {$inc} copia(s) caja3.");

        return self::SUCCESS;
    }

    /**
     * Piso de la migración para una tabla: MAX(created_at) de las copias caja3 con
     * parent_id NULL (las que trae la migración). Lo creado después es nativo.
     */
    private function migrationFloor(string $table): ?string
    {
        return DB::table($table)->where('caja', 3)->whereNull('parent_id')->max('created_at');
    }

    private function backfillExpenses(bool $dry): int
    {
        $floor = $this->migrationFloor('expenses');
        $this->line('Piso migración (expenses): '.($floor ?? 'sin histórico migrado — se procesan todos'));

        $q = DB::table('expenses')->where('caja', 1)->where('modo', 'Fijos');
        if ($floor) {
            $q->where('created_at', '>', $floor);
        }
        $rows = $q->get();

        $n = 0;
        foreach ($rows as $e) {
            if ($this->yaTieneCopia('expenses', $e, withTotal: true)) {
                continue;
            }
            $this->line("  egreso #{$e->id} {$e->date} {$e->reason} → caja3 total={$e->total}");
            if (! $dry) {
                DB::table('expenses')->insert([
                    'date' => $e->date, 'reason' => $e->reason, 'modo' => $e->modo,
                    'documento' => $e->documento, 'detail' => $e->detail, 'total' => $e->total,
                    'document_type' => $e->document_type, 'in_charge' => $e->in_charge,
                    'user_id' => $e->user_id, 'headquarter_id' => $e->headquarter_id,
                    'caja' => 3, 'parent_id' => $e->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $n++;
        }

        return $n;
    }

    private function backfillIncomes(bool $dry): int
    {
        $floor = $this->migrationFloor('incomes');
        $this->line('Piso migración (incomes): '.($floor ?? 'sin histórico migrado — se procesan todos'));

        $q = DB::table('incomes')->where('caja', 1)->where('modo', 'Fijos');
        if ($floor) {
            $q->where('created_at', '>', $floor);
        }
        $rows = $q->get();

        $n = 0;
        foreach ($rows as $i) {
            if ($this->yaTieneCopia('incomes', $i, withTotal: false)) {
                continue;
            }
            $con = Concept::where('type', 'ingreso')->where('name', $i->reason)->first();
            $fIng = (float) ($con->factor_ingreso ?? 0);
            $fEgr = (float) ($con->factor_egreso ?? 0);
            $cant = $fIng > 0 ? (int) round($i->total / $fIng) : 1;
            $neto = round((float) $i->total - ($fEgr * $cant), 2);

            $this->line("  ingreso #{$i->id} {$i->date} {$i->reason} → caja3 neto={$neto} (monto={$i->total}, fEgr={$fEgr}, cant={$cant})");
            if (! $dry) {
                DB::table('incomes')->insert([
                    'date' => $i->date, 'reason' => $i->reason, 'modo' => $i->modo,
                    'documento' => $i->documento, 'asesor' => $i->asesor, 'detail' => $i->detail,
                    'total' => $neto, 'user_id' => $i->user_id, 'headquarter_id' => $i->headquarter_id,
                    'caja' => 3, 'parent_id' => $i->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $n++;
        }

        return $n;
    }

    /**
     * Revierte un backfill mal hecho: borra las copias caja3 (con parent_id) cuyo
     * padre es un movimiento MIGRADO (created_at <= piso). Las copias de
     * movimientos NATIVOS (padre creado después del piso) se conservan.
     */
    private function revert(string $table, bool $dry): int
    {
        $floor = $this->migrationFloor($table);
        if (! $floor) {
            $this->line("Piso migración ({$table}): sin histórico migrado — nada que revertir.");

            return 0;
        }
        $this->line("Piso migración ({$table}): {$floor}");

        $ids = DB::table("{$table} as c3")
            ->join("{$table} as p", 'p.id', '=', 'c3.parent_id')
            ->where('c3.caja', 3)
            ->whereNotNull('c3.parent_id')
            ->where('p.created_at', '<=', $floor)
            ->pluck('c3.id');

        foreach ($ids as $id) {
            $this->line("  borrar copia caja3 #{$id} (padre migrado)");
        }

        if (! $dry && $ids->isNotEmpty()) {
            DB::table($table)->whereIn('id', $ids)->delete();
        }

        return $ids->count();
    }

    /** True si ya hay copia caja3 enlazada (parent_id) o una hermana migrada (misma fecha/modo/motivo/usuario). */
    private function yaTieneCopia(string $table, object $row, bool $withTotal): bool
    {
        if (DB::table($table)->where('caja', 3)->where('parent_id', $row->id)->exists()) {
            return true;
        }

        $q = DB::table($table)->where('caja', 3)
            ->where('date', $row->date)->where('modo', 'Fijos')
            ->where('reason', $row->reason)->where('user_id', $row->user_id);
        if ($withTotal) {
            $q->where('total', $row->total);
        }

        return $q->exists();
    }
}
