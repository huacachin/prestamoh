<?php

namespace App\Console\Commands;

use App\Support\Auditoria\CapturaAuditoria;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Borra las capturas de pantalla de la auditoría más viejas que N días
 * (08/10/2026). Van en carpetas por mes (auditoria/capturas/AAAA/MM), así que
 * se purga mes completo: un mes se va cuando su último día ya pasó el límite.
 * Las filas de activity_log se quedan; el visor avisa "captura purgada".
 */
class AuditoriaPurgarCapturas extends Command
{
    protected $signature = 'auditoria:purgar-capturas {--dias= : Conservar las de los últimos N días (por defecto, config auditoria.capturas.dias)} {--dry-run : Solo mostrar qué borraría}';

    protected $description = 'Borra las capturas de pantalla de auditoría más viejas que N días (carpetas por mes)';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?: config('auditoria.capturas.dias', 180));
        $limite = now()->subDays($dias)->startOfMonth();
        $disco = CapturaAuditoria::disco();
        $borrados = 0;

        foreach ($disco->directories('auditoria/capturas') as $anio) {
            foreach ($disco->directories($anio) as $mes) {
                if (! preg_match('#(\d{4})/(\d{2})$#', $mes, $m)) {
                    continue;
                }
                $finDeMes = Carbon::create((int) $m[1], (int) $m[2], 1)->endOfMonth();
                if ($finDeMes->greaterThanOrEqualTo($limite)) {
                    continue;
                }
                $n = count($disco->files($mes));
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '')."{$mes}: {$n} capturas");
                if (! $this->option('dry-run')) {
                    $disco->deleteDirectory($mes);
                }
                $borrados += $n;
            }
        }

        $this->info("Capturas de más de {$dias} días: {$borrados}".($this->option('dry-run') ? ' (no se borró nada)' : ' borradas'));

        return self::SUCCESS;
    }
}
