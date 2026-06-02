<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Elimina un refinanciamiento DESINCRONIZADO (creado con un código fuera de la
 * secuencia real, por un correlativo envenenado) y reactiva el crédito original.
 *
 * Qué hace por cada crédito-refinanciamiento indicado:
 *   1. Valida que sea un refi (cod_rem='REF' con idcan) sin pagos ni dependientes.
 *   2. Borra sus cuotas (credit_installments) y el crédito.
 *   3. Reactiva el crédito original (idcan): Activo, estado=1, fecha_cancelacion=NULL.
 *      Si el original era un crédito base (idcan NULL) restaura refinanciado=0 y
 *      cod_rem=NULL; si era a su vez un producto de refi (idcan no nulo) conserva
 *      refinanciado=1 / cod_rem='REF'.
 *
 * Uso:
 *   php artisan refinance:revert 87423            # elimina ese refi
 *   php artisan refinance:revert 87422 87423      # varios
 *   php artisan refinance:revert --detect         # lista candidatos desincronizados
 *   php artisan refinance:revert --detect --dry-run
 */
class RefinanceRevert extends Command
{
    protected $signature = 'refinance:revert
        {credits?* : IDs de los créditos-refinanciamiento a eliminar}
        {--detect : Detecta refinanciamientos desincronizados (id fuera de la secuencia legacy)}
        {--dry-run : Muestra qué haría sin aplicar cambios}';

    protected $description = 'Elimina refinanciamientos desincronizados y reactiva el crédito original.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $ids = array_map('intval', $this->argument('credits') ?? []);

        if ($this->option('detect')) {
            $detected = $this->detectDesynced();
            if (empty($detected)) {
                $this->info('✓ No se detectaron refinanciamientos desincronizados.');

                return self::SUCCESS;
            }
            $this->warn('Refinanciamientos desincronizados detectados (cod_rem=REF con id fuera de secuencia):');
            $this->line('  '.implode(', ', $detected));
            $ids = array_values(array_unique(array_merge($ids, $detected)));
        }

        if (empty($ids)) {
            $this->error('Indica al menos un id de crédito, o usa --detect.');

            return self::FAILURE;
        }

        $aplicados = 0;
        foreach ($ids as $id) {
            if ($this->revertOne($id, $dry)) {
                $aplicados++;
            }
        }

        $this->newLine();
        $this->info($dry
            ? "DRY RUN — {$aplicados} refinanciamiento(s) reversible(s). No se aplicó nada."
            : "✓ {$aplicados} refinanciamiento(s) revertido(s).");

        if (! $dry && $aplicados > 0) {
            $this->warn('Recuerda revisar el correlativo: php artisan installation:sync-correlativos');
        }

        return self::SUCCESS;
    }

    /** Umbral = contador autoritativo del legacy (tabla.correl). Refis con id mayor = desincronizados. */
    private function detectDesynced(): array
    {
        $threshold = null;
        try {
            $threshold = (int) DB::connection('legacy')->table('tabla')
                ->where('tipo', 'Credito')->value('correl');
        } catch (\Throwable $e) {
            $this->warn('  (Conexión legacy no disponible; uso MAX(id) de créditos no-REF como umbral.)');
        }
        if (! $threshold) {
            $threshold = (int) DB::table('credits')->where('cod_rem', '<>', 'REF')->max('id');
        }

        return DB::table('credits')
            ->where('cod_rem', 'REF')
            ->where('id', '>', $threshold)
            ->orderBy('id')
            ->pluck('id')->map(fn ($x) => (int) $x)->all();
    }

    private function revertOne(int $id, bool $dry): bool
    {
        $credit = DB::table('credits')->find($id);
        if (! $credit) {
            $this->error("#{$id}: no existe. Omitido.");

            return false;
        }
        if ($credit->cod_rem !== 'REF' || ! $credit->idcan) {
            $this->error("#{$id}: no es un refinanciamiento (cod_rem≠REF o sin idcan). Omitido.");

            return false;
        }

        // Seguridad: no eliminar si tiene pagos o si otro crédito lo referencia como origen.
        $pagos = DB::table('payments')->where('credit_id', $id)->count();
        if ($pagos > 0) {
            $this->error("#{$id}: tiene {$pagos} pago(s) registrado(s). NO se elimina.");

            return false;
        }
        $refBy = DB::table('credits')->where('idcan', $id)->count();
        if ($refBy > 0) {
            $this->error("#{$id}: es origen de otro refinanciamiento (idcan). NO se elimina.");

            return false;
        }

        $orig = DB::table('credits')->find($credit->idcan);
        if (! $orig) {
            $this->error("#{$id}: crédito original #{$credit->idcan} no existe. Omitido.");

            return false;
        }

        // El original recupera Activo. Si era base (idcan NULL) se limpia el flag de refi;
        // si era a su vez producto de refi, se conservan refinanciado=1 / cod_rem='REF'.
        $restoreRefi = $orig->idcan ? 1 : 0;
        $restoreCodRem = $orig->idcan ? 'REF' : null;
        $insCount = DB::table('credit_installments')->where('credit_id', $id)->count();

        $this->newLine();
        $this->line("─ #{$id} (cliente {$credit->client_id}) → reactivar original #{$credit->idcan}");
        $this->line("   borrar crédito #{$id} + {$insCount} cuota(s)");
        $this->line("   restaurar #{$credit->idcan}: Activo, estado=1, fecha_cancelacion=NULL, refinanciado={$restoreRefi}, cod_rem=".var_export($restoreCodRem, true));

        if ($dry) {
            return true;
        }

        DB::transaction(function () use ($id, $credit, $restoreRefi, $restoreCodRem) {
            DB::table('credit_installments')->where('credit_id', $id)->delete();
            DB::table('credits')->where('id', $id)->delete();
            DB::table('credits')->where('id', $credit->idcan)->update([
                'situacion' => 'Activo',
                'estado' => 1,
                'fecha_cancelacion' => null,
                'refinanciado' => $restoreRefi,
                'cod_rem' => $restoreCodRem,
                'updated_at' => now(),
            ]);
        });

        $this->info("   ✓ #{$id} eliminado, #{$credit->idcan} reactivado.");

        return true;
    }
}
