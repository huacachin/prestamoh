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
 * Seguro frente al histórico migrado:
 *   - `--since` (obligatorio): solo procesa filas con created_at >= esa fecha, para
 *     no tocar lo migrado. Usa la fecha/hora posterior a tu última `legacy:migrate`.
 *   - Idempotente: salta si ya existe la copia caja3 enlazada (parent_id).
 *   - Guarda anti-duplicado: salta si ya existe una caja3 "hermana" (misma fecha,
 *     modo, motivo y usuario) aunque no tenga parent_id (caso migrado).
 *
 * Uso:
 *   php artisan cash:backfill-caja3 --since=2026-05-31 --dry-run
 *   php artisan cash:backfill-caja3 --since=2026-05-31
 */
class CashBackfillCaja3 extends Command
{
    protected $signature = 'cash:backfill-caja3
        {--since= : Solo movimientos con created_at >= esta fecha (YYYY-MM-DD). Obligatorio.}
        {--dry-run : Muestra qué haría sin escribir.}';

    protected $description = 'Crea las copias en caja 3 faltantes de los movimientos Fijos (egresos/ingresos) creados en Laravel.';

    public function handle(): int
    {
        $since = $this->option('since');
        if (! $since) {
            $this->error('--since=YYYY-MM-DD es obligatorio (fecha posterior a tu última migración legacy, para no tocar el histórico).');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        $exp = $this->backfillExpenses($since, $dry);
        $inc = $this->backfillIncomes($since, $dry);

        $this->newLine();
        $this->info(($dry ? 'DRY RUN — ' : '✓ ')."Egresos: {$exp} copia(s) caja3 · Ingresos: {$inc} copia(s) caja3.");

        return self::SUCCESS;
    }

    private function backfillExpenses(string $since, bool $dry): int
    {
        $rows = DB::table('expenses')->where('caja', 1)->where('modo', 'Fijos')
            ->where('created_at', '>=', $since)->get();

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

    private function backfillIncomes(string $since, bool $dry): int
    {
        $rows = DB::table('incomes')->where('caja', 1)->where('modo', 'Fijos')
            ->where('created_at', '>=', $since)->get();

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
