<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MorosidadService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Registra el snapshot diario de morosidad en cache_morosidad_diaria.
 *
 * Sin opciones: registra HOY. Con --desde (y opcionalmente --hasta):
 * backfill del rango, calculando solo los días que falten (idempotente).
 * El dashboard además se auto-completa al abrirse (self-healing), así que
 * este comando es para el backfill inicial o para forzar un recálculo.
 */
class MorosidadSnapshot extends Command
{
    protected $signature = 'morosidad:snapshot
                            {--desde= : Backfill desde esta fecha (YYYY-MM-DD)}
                            {--hasta= : Fin del backfill (default: hoy)}
                            {--recalcular : Recalcula aunque el día ya exista}';

    protected $description = 'Snapshot diario de morosidad (saldo en mora, cartera, %) — reconstrucción validada contra Portfolio';

    public function handle(MorosidadService $svc): int
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $desde = $this->option('desde') ?: $hoy;
        $hasta = $this->option('hasta') ?: $hoy;

        if ($this->option('recalcular')) {
            $cursor = Carbon::parse($desde);
            $fin = Carbon::parse(min($hasta, $hoy));
            $bar = $this->output->createProgressBar((int) $cursor->diffInDays($fin) + 1);
            while ($cursor->lte($fin)) {
                $svc->snapshot($cursor->format('Y-m-d'));
                $bar->advance();
                $cursor->addDay();
            }
            $bar->finish();
        } else {
            $svc->completarRango($desde, $hasta);
        }

        $this->newLine();
        $ultimo = $svc->alDia($hoy);
        $this->info("Snapshot al día. HOY: mora S/ {$ultimo['saldo_mora']} ({$ultimo['pct']}%) en {$ultimo['n_creditos_mora']} créditos.");

        return self::SUCCESS;
    }
}
