<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaymentsRenameMoraDetalle extends Command
{
    protected $signature = 'payments:rename-mora-detalle
        {--dry-run : Solo muestra cuántas filas se cambiarían, sin escribir}';

    protected $description = 'Renombra el detalle de pagos "Mora manual" a "Mora de cuota" (homologación de terminología).';

    private const FROM = 'Mora manual';

    private const TO = 'Mora de cuota';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $count = DB::table('payments')->where('detalle', self::FROM)->count();

        if ($count === 0) {
            $this->info('No hay pagos con detalle "'.self::FROM.'". Nada que hacer.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] Se cambiarían {$count} pago(s): \"".self::FROM.'" → "'.self::TO.'".');

            return self::SUCCESS;
        }

        $updated = DB::table('payments')->where('detalle', self::FROM)->update(['detalle' => self::TO]);

        $this->info("Actualizados {$updated} pago(s): \"".self::FROM.'" → "'.self::TO.'".');

        return self::SUCCESS;
    }
}
