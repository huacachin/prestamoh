<?php

namespace App\Console\Commands;

use App\Services\CajaDailyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Calcula el snapshot diario de "Capital Neto" (huaca_capineto del legacy) y lo
 * persiste en la tabla `capital_neto`, que el reporte /reports/cash-statistics lee
 * para la columna "Capital T." y el campo `recucapi` del resumen.
 *
 * PORTE DE data_capineto.php (legacy /sistema/data_capineto.php):
 *   El legacy recorre todos los créditos con situacion<>'Cancelado', los agrupa por
 *   tasa de interés en huaca_tmp mediante una cadena de `if($row['interes']=='X')`,
 *   y suma `prestamo` (= importe) SOLO de los grupos cuya tasa coincide con una de
 *   las tasas cableadas en esos if. $newprez = SUM(prestamo de los grupos) y eso es
 *   lo que inserta en huaca_capineto. En consecuencia:
 *     - Es la SUMA de `importe` de créditos NO Cancelados...
 *     - ...PERO solo de aquellos cuya `interes` esté en el set cableado (RATES).
 *       Cualquier crédito con una tasa fuera del set (1.5, 10.4, 20.8, 28, 31.2, 32,
 *       48, 60, 62.4, ...) NO entra en ningún if y queda excluido silenciosamente.
 *   Por eso una SUM(importe) ingenua sobreestima (~2,784,862 vs 2,042,903.21 el
 *   2026-05-31): la diferencia son los créditos con tasas fuera del set.
 *
 * RECONSTRUCCIÓN "A LA FECHA" (para backfill de días pasados):
 *   El cron legacy corría con `situacion<>'Cancelado'` AL MOMENTO. Para reproducir
 *   un día D del pasado se reconstruye el estado del crédito a esa fecha:
 *     - Debe existir en D: fecha_actualizacion (o fecha_prestamo) <= D.
 *     - No debe estar cancelado en D: si situacion='Cancelado' se excluye cuando
 *       fecha_cancelacion <= D, o cuando NO tiene fecha de cancelación válida
 *       (Cancelados legacy con 0000-00-00: son cancelaciones antiguas de fecha
 *       desconocida, ya inactivos — 42 créditos = 89,159.00, ver memoria
 *       project_legacy_orphans_invalid_dates).
 *   Esto reproduce exactamente los oráculos de huaca_capineto del 2026-01-15,
 *   2026-05-15 y 2026-05-31 (y el 2025-06-16 con +125 por un único crédito antiguo
 *   con estado intermedio no representable).
 */
class SnapshotCapitalNeto extends Command
{
    protected $signature = 'reports:snapshot-capital-neto
        {--date= : Calcula un único día (YYYY-MM-DD)}
        {--from= : Inicio del rango a backfillear (YYYY-MM-DD), requiere --to}
        {--to= : Fin del rango a backfillear (YYYY-MM-DD), requiere --from}
        {--today : Calcula el día de hoy (comportamiento por defecto)}
        {--dry-run : Muestra el cálculo sin escribir en capital_neto}';

    protected $description = 'Calcula y persiste el snapshot diario de Capital Neto (porte de data_capineto.php).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $dates = $this->resolveDates();
        if ($dates === null) {
            return self::FAILURE;
        }

        $svc = app(CajaDailyService::class);

        foreach ($dates as $date) {
            $importe = $svc->capitalNetoEnFecha($date);

            if ($dry) {
                $this->line(sprintf('  %s  capital_neto = %s  (dry-run, no escrito)', $date, number_format($importe, 2)));

                continue;
            }

            DB::table('capital_neto')->updateOrInsert(
                ['fecha' => $date],
                ['importe' => round($importe, 2), 'updated_at' => now()]
            );

            $this->info(sprintf('  %s  capital_neto = %s', $date, number_format($importe, 2)));
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve el conjunto de fechas a procesar según las opciones.
     *
     * @return list<string>|null null si las opciones son inválidas
     */
    private function resolveDates(): ?array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($from !== null || $to !== null) {
            if ($from === null || $to === null) {
                $this->error('--from y --to deben usarse juntos.');

                return null;
            }
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Fechas --from/--to inválidas.');

                return null;
            }
            if ($end->lt($start)) {
                $this->error('--to no puede ser anterior a --from.');

                return null;
            }

            $dates = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $dates[] = $d->format('Y-m-d');
            }

            return $dates;
        }

        if ($date !== null) {
            try {
                return [Carbon::parse($date)->format('Y-m-d')];
            } catch (\Throwable $e) {
                $this->error('Fecha --date inválida.');

                return null;
            }
        }

        // --today o por defecto.
        return [Carbon::today()->format('Y-m-d')];
    }
}
