<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationSyncCorrelativos extends Command
{
    protected $signature = 'installation:sync-correlativos {--dry-run}';
    protected $description = 'Sincroniza correlativos.correl con MAX(id) de cada tabla. Tras importar.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $map = [
            'Cliente' => ['table' => 'clients', 'column' => 'id'],
            'Credito' => ['table' => 'credits', 'column' => 'id'],
        ];

        $changes = [];
        foreach ($map as $tipo => $cfg) {
            $current = (int) DB::table('correlativos')->where('tipo', $tipo)->value('correl');
            $maxId   = (int) DB::table($cfg['table'])->max($cfg['column']);

            if ($maxId > $current) {
                $changes[] = compact('tipo') + ['table' => $cfg['table'], 'antes' => $current, 'despues' => $maxId];
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

        if (!$this->confirm('¿Aplicar los cambios?', true)) {
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
}
