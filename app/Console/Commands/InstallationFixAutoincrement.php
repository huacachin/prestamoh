<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationFixAutoincrement extends Command
{
    protected $signature = 'installation:fix-autoincrement {--dry-run}';
    protected $description = 'Resetea AUTO_INCREMENT a MAX(id)+1 en tablas críticas tras importar.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tables = [
            'clients', 'credits', 'credit_installments', 'payments',
            'incomes', 'expenses', 'mass_deletions', 'mass_deletion_details',
            'cash_openings', 'concepts', 'headquarters', 'users',
            'client_attachments', 'client_avales', 'income_attachments', 'expense_attachments',
            'mora_acumulada', 'dias_mora',
        ];

        $changes = [];
        foreach ($tables as $t) {
            try {
                $max = (int) DB::table($t)->max('id');
                $row = DB::selectOne("SHOW TABLE STATUS LIKE '$t'");
                $ai  = (int) ($row->Auto_increment ?? 0);
                if ($ai <= $max) {
                    $changes[] = ['tabla' => $t, 'max' => $max, 'ai_actual' => $ai, 'ai_nuevo' => $max + 1];
                }
            } catch (\Throwable $e) {}
        }

        if (empty($changes)) {
            $this->info('✓ Todos los AUTO_INCREMENT correctos.');
            return self::SUCCESS;
        }

        $this->table(['Tabla', 'MAX(id)', 'AI actual', 'AI nuevo'],
            array_map(fn ($c) => [$c['tabla'], $c['max'], $c['ai_actual'], $c['ai_nuevo']], $changes));

        if ($dryRun) { $this->warn('DRY RUN — no aplicado.'); return self::SUCCESS; }
        if (!$this->confirm('¿Aplicar ALTER TABLE AUTO_INCREMENT?', true)) return self::SUCCESS;

        foreach ($changes as $c) {
            DB::statement("ALTER TABLE `{$c['tabla']}` AUTO_INCREMENT = {$c['ai_nuevo']}");
        }

        $this->info('✓ AUTO_INCREMENT reseteados.');
        return self::SUCCESS;
    }
}
