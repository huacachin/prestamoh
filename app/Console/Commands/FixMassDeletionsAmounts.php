<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMassDeletionsAmounts extends Command
{
    /**
     * Recalcula `mass_deletions.amount` como SUM(mass_deletion_details.amount).
     *
     * Por qué: el legacy guardaba en `cab_masivo.monto` el "total general"
     * (monto + mora), incluso cuando la mora se reservaba (no se cobraba).
     * Eso infla el total real cobrado al cliente. Este comando ajusta los
     * mass_deletions para que coincidan con la suma real de sus detalles.
     *
     * Casos donde corre:
     *  - Tras importar datos legacy (huaca_cab_masivo + huaca_det_masivo).
     *  - Tras correr seeders que insertan en bruto.
     *  - Por cron periódico de saneamiento (opcional).
     */
    protected $signature = 'mass-deletions:fix-amounts
                            {--dry-run : Solo muestra qué cambiaría, sin ejecutar UPDATE}
                            {--show=10 : Cantidad de muestras a listar (default 10)}';

    protected $description = 'Recalcula mass_deletions.amount = SUM(mass_deletion_details.amount). Usar tras importaciones.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $show   = max(0, (int) $this->option('show'));

        $this->info('Escaneando inconsistencias en mass_deletions…');

        $diffs = DB::table('mass_deletions as m')
            ->leftJoin('mass_deletion_details as d', 'd.mass_deletion_id', '=', 'm.id')
            ->select('m.id', 'm.credit_id', 'm.date', 'm.amount as antes',
                     DB::raw('COALESCE(SUM(d.amount), 0) as despues'))
            ->groupBy('m.id', 'm.credit_id', 'm.date', 'm.amount')
            ->havingRaw('ROUND(m.amount - COALESCE(SUM(d.amount), 0), 2) != 0')
            ->get();

        $totalRows  = $diffs->count();
        $sumAntes   = $diffs->sum('antes');
        $sumDespues = $diffs->sum('despues');
        $diffNeta   = $sumAntes - $sumDespues;

        if ($totalRows === 0) {
            $this->info('✓ Sin inconsistencias. Nada que actualizar.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Rows con diferencia',     number_format($totalRows)],
                ['Suma actual (amount)',    'S/ ' . number_format($sumAntes, 2)],
                ['Suma corregida (details)', 'S/ ' . number_format($sumDespues, 2)],
                ['Total a ajustar',         'S/ ' . number_format($diffNeta, 2)],
            ]
        );

        if ($show > 0) {
            $this->newLine();
            $this->line("── Muestra ({$show} más recientes) ──");
            $rows = $diffs->sortByDesc('id')->take($show)->map(fn ($d) => [
                'id'      => $d->id,
                'credit'  => $d->credit_id,
                'fecha'   => $d->date,
                'antes'   => number_format($d->antes, 2),
                'despues' => number_format($d->despues, 2),
                'diff'    => number_format($d->antes - $d->despues, 2),
            ])->all();
            $this->table(['id', 'credit', 'fecha', 'antes', 'despues', 'diff'], $rows);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN — no se aplicaron cambios. Vuelve a correr sin --dry-run para aplicar.');
            return self::SUCCESS;
        }

        if (!$this->confirm("¿Confirmás aplicar el UPDATE en los {$totalRows} registros?", true)) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Aplicando UPDATE en transacción…');

        $start = microtime(true);
        DB::beginTransaction();

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        $updated = 0;
        foreach ($diffs as $d) {
            DB::table('mass_deletions')
                ->where('id', $d->id)
                ->update([
                    'amount'     => $d->despues,
                    'updated_at' => now(),
                ]);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Verificación post-update
        $pendientes = DB::table('mass_deletions as m')
            ->leftJoin('mass_deletion_details as d', 'd.mass_deletion_id', '=', 'm.id')
            ->select('m.id')
            ->groupBy('m.id', 'm.amount')
            ->havingRaw('ROUND(m.amount - COALESCE(SUM(d.amount), 0), 2) != 0')
            ->count();

        $elapsed = round(microtime(true) - $start, 2);

        if ($pendientes === 0 && $updated === $totalRows) {
            DB::commit();
            $this->info("✓ COMMIT — {$updated} rows actualizados en {$elapsed}s. Inconsistencias restantes: 0.");
            return self::SUCCESS;
        }

        DB::rollBack();
        $this->error("✗ ROLLBACK — actualizados={$updated}, esperados={$totalRows}, pendientes={$pendientes}.");
        return self::FAILURE;
    }
}
