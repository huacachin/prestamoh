<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationSyncCorrelativos extends Command
{
    protected $signature = 'installation:sync-correlativos
        {--dry-run}
        {--credito= : Forzar el correlativo de Crédito a este valor exacto}
        {--cliente= : Forzar el correlativo de Cliente a este valor exacto}';

    protected $description = 'Sincroniza correlativos.correl con el máximo id real (detección 100% local, ignora outliers).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // 100% local: el correlativo = máximo id del bloque real de cada tabla,
        // ignorando outliers lejanos (ids aislados muy por encima del resto, p.ej.
        // un crédito legacy con id=87421 cuando la secuencia real va por ~29143).
        // No depende de la conexión legacy.
        $map = [
            'Cliente' => ['table' => 'clients', 'option' => 'cliente'],
            'Credito' => ['table' => 'credits', 'option' => 'credito'],
        ];

        $changes = [];
        foreach ($map as $tipo => $cfg) {
            $current = (int) DB::table('correlativos')->where('tipo', $tipo)->value('correl');
            $target = $this->resolveTarget($cfg['option'], $cfg['table']);

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
     * Salto que delata un outlier: un id separado del id inmediatamente inferior
     * por más de este margen se considera atípico (registro legacy fuera de
     * secuencia, p.ej. id=87421 cuando el bloque real va por ~29143).
     */
    private const OUTLIER_GAP = 20000;

    /**
     * Correlativo correcto = máximo id del BLOQUE REAL de la tabla (100% local).
     *
     * Recorre los ids de mayor a menor y descarta los outliers aislados (cuyo
     * hueco hacia el id inferior supera OUTLIER_GAP), quedándose con el tope del
     * bloque denso. Así el outlier 87421 no infla el correlativo, y a la vez nunca
     * baja por debajo de un id real ya existente (evita colisiones).
     *
     * Override manual opcional: --credito=N / --cliente=N.
     */
    private function resolveTarget(string $option, string $table): int
    {
        $override = $this->option($option);
        if ($override !== null && $override !== '') {
            return (int) $override;
        }

        $ids = DB::table($table)->orderByDesc('id')->pluck('id')
            ->map(fn ($x) => (int) $x)->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $top = $ids[0];
        for ($k = 0; $k + 1 < $ids->count(); $k++) {
            if (($ids[$k] - $ids[$k + 1]) > self::OUTLIER_GAP) {
                // ids[k] está aislado muy por encima del resto → outlier; bajar.
                $top = $ids[$k + 1];

                continue;
            }
            break;
        }

        return $top;
    }
}
