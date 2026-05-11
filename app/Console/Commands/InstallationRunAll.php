<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallationRunAll extends Command
{
    protected $signature = 'installation:run-all
                            {--force : Ejecutar todos los fixes sin pedir confirmación uno por uno}';
    protected $description = 'Ejecuta TODOS los comandos de saneamiento en orden seguro tras importación.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('═══ Saneamiento post-importación ═══');
        $this->line('Se ejecutará la siguiente secuencia:');
        $this->line(' 1. installation:fix-invalid-dates       (0000-00-00 → NULL)');
        $this->line(' 2. installation:fix-expense-documento   (NULL → "GUIA")');
        $this->line(' 3. installation:fix-installment-dates   (cronograma cuotas)');
        $this->line(' 4. installation:sync-correlativos       (Cliente, Credito)');
        $this->line(' 5. mass-deletions:fix-amounts           (recálculo amount = sum(details))');
        $this->line(' 6. installation:migrate-roles           (roles legacy → nuevos)');
        $this->line(' 7. installation:fix-autoincrement       (AI = max(id)+1)');
        $this->line(' 8. installation:check                   (health check final)');
        $this->newLine();

        if (!$force && !$this->confirm('¿Continuar?', true)) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $opts = $force ? ['--no-interaction' => true] : [];

        $steps = [
            'installation:fix-invalid-dates',
            'installation:fix-expense-documento',
            'installation:fix-installment-dates',
            'installation:sync-correlativos',
            'mass-deletions:fix-amounts',
            'installation:migrate-roles',
            'installation:fix-autoincrement',
        ];

        foreach ($steps as $i => $cmd) {
            $this->newLine();
            $this->line("─── Paso " . ($i + 1) . ": $cmd ───");
            $code = $this->call($cmd, $opts);
            if ($code !== self::SUCCESS && !$force) {
                if (!$this->confirm("El paso falló. ¿Continuar con el siguiente?", false)) {
                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->line('─── Health check final ───');
        $checkResult = $this->call('installation:check');

        $this->newLine();
        $checkResult === self::SUCCESS
            ? $this->info('✓ Saneamiento completado sin issues pendientes.')
            : $this->warn('Quedan issues — revisa el output del health check arriba.');

        return self::SUCCESS;
    }
}
