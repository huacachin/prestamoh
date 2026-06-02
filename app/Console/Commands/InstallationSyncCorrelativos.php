<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationSyncCorrelativos extends Command
{
    protected $signature = 'installation:sync-correlativos {--dry-run}';

    protected $description = 'Sincroniza correlativos.correl con el contador autoritativo del legacy (tabla.correl). Tras importar.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // tabla.correl del legacy es el contador real de asignación. NO usamos
        // MAX(id) porque puede haber registros legacy atípicos con id fuera de
        // secuencia (p.ej. un crédito con id=87421) que envenenarían el correlativo.
        $map = [
            'Cliente' => ['table' => 'clients', 'legacy_tipo' => 'Cliente'],
            'Credito' => ['table' => 'credits', 'legacy_tipo' => 'Credito'],
        ];

        $changes = [];
        foreach ($map as $tipo => $cfg) {
            $current = (int) DB::table('correlativos')->where('tipo', $tipo)->value('correl');
            $target = $this->resolveTarget($cfg['legacy_tipo'], $cfg['table']);

            // Permitimos bajar el correlativo (corregir uno envenenado), no solo subir.
            if ($target > 0 && $target !== $current) {
                $changes[] = compact('tipo') + ['table' => $cfg['table'], 'antes' => $current, 'despues' => $target];
            }
        }

        if (empty($changes)) {
            $this->info('✓ Correlativos ya sincronizados.');

            return self::SUCCESS;
        }

        $this->table(['Tipo', 'Tabla', 'Antes', 'Después'], array_map(fn ($c) => [
            $c['tipo'], $c['table'], $c['antes'], $c['despues'],
        ], $changes));

        if ($dryRun) {
            $this->warn('DRY RUN — no aplicado.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar los cambios?', true)) {
            return self::SUCCESS;
        }

        foreach ($changes as $c) {
            DB::table('correlativos')->updateOrInsert(
                ['tipo' => $c['tipo']],
                ['correl' => $c['despues'], 'updated_at' => now()]
            );
        }

        $this->info('✓ Correlativos sincronizados.');

        return self::SUCCESS;
    }

    /**
     * Valor correcto del correlativo:
     *   max( contador legacy tabla.correl , máximo id local de la secuencia ).
     *
     * El "máximo id local de la secuencia" excluye outliers lejanos (registros
     * legacy atípicos con id muy por encima del bloque principal, p.ej. un crédito
     * con id=87421 cuando la secuencia real va por ~29143). Sin esto, el correlativo
     * se dispararía y los nuevos códigos saldrían desincronizados.
     *
     * A la vez, tomar el MAX local (dentro del rango) evita BAJAR el correlativo por
     * debajo de ids ya creados por la app tras la migración (que colisionaría).
     */
    private const OUTLIER_GAP = 20000;

    private function resolveTarget(string $legacyTipo, string $table): int
    {
        $legacyCorrel = 0;
        try {
            $legacyCorrel = (int) DB::connection('legacy')->table('tabla')
                ->where('tipo', $legacyTipo)->value('correl');
        } catch (\Throwable $e) {
            $this->warn("  (Legacy no disponible para '{$legacyTipo}'; uso solo ids locales.)");
        }

        // Cota para excluir outliers: el contador legacy + un margen de crecimiento.
        // Si no hay legacy, usamos el máximo absoluto como cota (no excluye nada).
        $cota = $legacyCorrel > 0 ? $legacyCorrel + self::OUTLIER_GAP : PHP_INT_MAX;

        $maxLocal = (int) DB::table($table)->where('id', '<=', $cota)->max('id');

        return max($legacyCorrel, $maxLocal);
    }
}
