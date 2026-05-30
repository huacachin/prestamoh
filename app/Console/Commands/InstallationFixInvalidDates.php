<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallationFixInvalidDates extends Command
{
    protected $signature = 'installation:fix-invalid-dates {--dry-run}';
    protected $description = 'Convierte fechas legacy 0000-00-00 a NULL en payments y credits.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Buscando fechas inválidas (0000-00-00)…');

        $changes = [];

        // payments.fecha
        try {
            $n = DB::table('payments')->whereRaw("CAST(fecha AS CHAR) = '0000-00-00'")->count();
            if ($n > 0) $changes[] = ['table' => 'payments', 'column' => 'fecha', 'rows' => $n];
        } catch (\Throwable $e) {}

        // credits.fecha_cancelacion
        try {
            $n = DB::table('credits')->whereRaw("CAST(fecha_cancelacion AS CHAR) = '0000-00-00'")->count();
            if ($n > 0) $changes[] = ['table' => 'credits', 'column' => 'fecha_cancelacion', 'rows' => $n];
        } catch (\Throwable $e) {}

        // credits.fecha_vencimiento (raro, pero por si acaso)
        try {
            $n = DB::table('credits')->whereRaw("CAST(fecha_vencimiento AS CHAR) = '0000-00-00'")->count();
            if ($n > 0) $changes[] = ['table' => 'credits', 'column' => 'fecha_vencimiento', 'rows' => $n];
        } catch (\Throwable $e) {}

        // incomes.date / expenses.date — fechas legacy 0000-00-00 que MySQL no
        // estricto pudo dejar entrar. Deben quedar NULL, no con fecha basura.
        foreach (['incomes', 'expenses'] as $tbl) {
            try {
                $n = DB::table($tbl)->whereRaw("CAST(`date` AS CHAR) = '0000-00-00'")->count();
                if ($n > 0) $changes[] = ['table' => $tbl, 'column' => 'date', 'rows' => $n];
            } catch (\Throwable $e) {}
        }

        if (empty($changes)) {
            $this->info('✓ Sin fechas 0000-00-00. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->table(['Tabla', 'Columna', 'Rows afectados'],
            array_map(fn ($c) => [$c['table'], $c['column'], $c['rows']], $changes));

        if ($dryRun) { $this->warn('DRY RUN — no aplicado.'); return self::SUCCESS; }
        if (!$this->confirm('¿Aplicar UPDATE → NULL?', true)) return self::SUCCESS;

        $total = 0;
        foreach ($changes as $c) {
            $rows = DB::table($c['table'])
                ->whereRaw("CAST({$c['column']} AS CHAR) = '0000-00-00'")
                ->update([$c['column'] => null, 'updated_at' => now()]);
            $total += $rows;
        }

        $this->info("✓ $total filas actualizadas a NULL.");
        return self::SUCCESS;
    }
}
