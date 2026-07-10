<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaymentsRenameMoraDetalle extends Command
{
    protected $signature = 'payments:rename-mora-detalle
        {--scan : Solo lectura: busca dónde vive el término (columna + valor exacto + conteo)}
        {--from= : Valor exacto a reemplazar (default: "Mora manual")}
        {--dry-run : Muestra cuántas filas se cambiarían, sin escribir}';

    protected $description = 'Renombra el detalle de pagos "Mora manual" a "Mora de cuota" (homologación de terminología).';

    private const FROM = 'Mora manual';

    private const TO = 'Mora de cuota';

    public function handle(): int
    {
        if ($this->option('scan')) {
            return $this->scan();
        }

        $from = (string) ($this->option('from') ?: self::FROM);
        $dryRun = (bool) $this->option('dry-run');

        $count = DB::table('payments')->where('detalle', $from)->count();

        if ($count === 0) {
            $this->warn('No hay pagos con detalle "'.$from.'". Probá primero: php artisan payments:rename-mora-detalle --scan');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] Se cambiarían {$count} pago(s): \"{$from}\" → \"".self::TO.'".');

            return self::SUCCESS;
        }

        $updated = DB::table('payments')->where('detalle', $from)->update(['detalle' => self::TO]);
        $this->info("Actualizados {$updated} pago(s): \"{$from}\" → \"".self::TO.'".');

        return self::SUCCESS;
    }

    /**
     * Busca cualquier valor que contenga "manual" (case-insensitive) en las
     * columnas de texto que alimentan /cash/incomes, para descubrir el término
     * exacto guardado en producción.
     */
    private function scan(): int
    {
        $targets = [
            ['payments', 'detalle'],
            ['payments', 'tipo'],
            ['payments', 'documento'],
            ['incomes', 'reason'],
            ['incomes', 'detail'],
            ['incomes', 'documento'],
        ];

        $found = false;
        foreach ($targets as [$table, $col]) {
            $rows = DB::table($table)
                ->whereRaw("LOWER({$col}) LIKE ?", ['%manual%'])
                ->select($col.' as val', DB::raw('COUNT(*) as n'))
                ->groupBy($col)
                ->orderByDesc('n')
                ->get();

            foreach ($rows as $r) {
                $found = true;
                $this->line(sprintf('%-9s.%-9s  x%-5d  [%s]', $table, $col, $r->n, $r->val));
            }
        }

        if (! $found) {
            $this->warn('No se encontró ningún valor con "manual" en payments/incomes. Quizás el término se muestra con otra palabra; pasame una captura del texto exacto.');
        }

        return self::SUCCESS;
    }
}
