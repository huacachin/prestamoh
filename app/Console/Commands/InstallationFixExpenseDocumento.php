<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationFixExpenseDocumento extends Command
{
    protected $signature = 'installation:fix-expense-documento {--dry-run}';
    protected $description = 'Pobla expenses.documento = "GUIA" donde esté NULL (default legacy).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $count = DB::table('expenses')->whereNull('documento')->count();
        if ($count === 0) {
            $this->info('✓ Todos los expenses ya tienen documento. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info("Expenses con documento NULL: $count");

        if ($dryRun) { $this->warn('DRY RUN — no aplicado.'); return self::SUCCESS; }
        if (!$this->confirm("¿Setear documento='GUIA' a $count rows?", true)) return self::SUCCESS;

        $rows = DB::table('expenses')->whereNull('documento')->update([
            'documento' => 'GUIA', 'updated_at' => now(),
        ]);

        $this->info("✓ $rows expenses actualizados a documento='GUIA'.");
        return self::SUCCESS;
    }
}
