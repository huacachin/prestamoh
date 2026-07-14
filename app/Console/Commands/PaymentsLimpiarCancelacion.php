<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpia los rastros que "eliminar masivo" (reverse) NO revierte cuando el
 * pago revertido fue una CANCELACIÓN, dejando el crédito listo para volver a
 * generar el pago:
 *
 *   1. Pagos MORA huérfanos del crédito en la fecha (MORA ACUM., MORA, MORA
 *      INTERES, MORA CAPITAL): se crean sin fila en mass_deletion_details,
 *      así que el reverse no los borra y quedan colgados en Ingresos.
 *      Huérfano = sin cabecera mass_deletions con mismo crédito+fecha+hora.
 *   2. dias_mora insertados al registrar ese pago (mismo crédito, creados en
 *      la fecha del pago).
 *   3. credits.fecha_cancelacion (el reverse restaura situacion/estado pero
 *      no la fecha).
 *   4. Restaura la fila mora_acumulada del crédito (al cancelar, pagar() la
 *      elimina y el reverse no la repone): desde huaca_moraacum del legacy,
 *      o con --mora-importe/--mora-dias si no hay conexión legacy.
 *
 * Además avisa si la cancelación condonó interés del cronograma (auditado
 * como "Cancelación anticipada del crédito N"), porque esa rebaja tampoco la
 * restaura el reverse y hay que revisarla a mano.
 *
 * Uso:
 *   php artisan payments:limpiar-cancelacion 29184 --dry-run
 *   php artisan payments:limpiar-cancelacion 29184
 *   php artisan payments:limpiar-cancelacion 29184 --fecha=2026-07-13
 *   php artisan payments:limpiar-cancelacion 29184 --mora-importe=3.68 --mora-dias=4
 */
class PaymentsLimpiarCancelacion extends Command
{
    protected $signature = 'payments:limpiar-cancelacion
        {credit : ID del crédito}
        {--fecha= : Fecha del pago cancelador (YYYY-MM-DD; por defecto credits.fecha_cancelacion)}
        {--mora-importe= : Importe de mora acumulada a restaurar (si no, se toma del legacy)}
        {--mora-dias= : Días de la mora acumulada a restaurar (con --mora-importe)}
        {--dry-run : Muestra qué haría sin aplicar cambios}';

    protected $description = 'Limpia los rastros de una cancelación revertida (pagos MORA huérfanos, fecha_cancelacion, dias_mora) y restaura la mora acumulada.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $creditId = (int) $this->argument('credit');

        $credit = DB::table('credits')->find($creditId);
        if (! $credit) {
            $this->error("Crédito #{$creditId} no existe.");

            return self::FAILURE;
        }
        if ($credit->situacion === 'Cancelado') {
            $this->error("Crédito #{$creditId} sigue Cancelado: primero revierte el pago en Registro → Eliminar Masivo.");

            return self::FAILURE;
        }

        $fecha = $this->option('fecha') ?: ($credit->fecha_cancelacion ? substr($credit->fecha_cancelacion, 0, 10) : null);
        if (! $fecha) {
            $this->error('No hay fecha_cancelacion en el crédito; indica la fecha del pago con --fecha=YYYY-MM-DD.');

            return self::FAILURE;
        }

        $this->line("Crédito #{$creditId} ({$credit->situacion}) — fecha del pago cancelador: {$fecha}");

        // ── 1) Pagos MORA huérfanos: sin masivo (crédito+fecha+hora) que los respalde
        $huerfanos = DB::table('payments as p')
            ->where('p.credit_id', $creditId)
            ->where('p.tipo', 'MORA')
            ->whereDate('p.fecha', $fecha)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('mass_deletions as m')
                    ->whereColumn('m.credit_id', 'p.credit_id')
                    ->whereColumn('m.date', 'p.fecha')
                    ->whereColumn('m.time', 'p.hora');
            })
            ->orderBy('p.id')
            ->get(['p.id', 'p.documento', 'p.monto', 'p.detalle', 'p.hora']);

        foreach ($huerfanos as $p) {
            $this->line("  borrar pago #{$p->id} {$p->documento} {$p->monto} ({$p->detalle}) {$fecha} {$p->hora}");
            if (in_array($p->documento, ['MORA CAPITAL', 'MORA INTERES'], true)) {
                $this->warn("    ⚠ {$p->documento} es mora manual: pudo incrementar importe_mora/mora_interes en una cuota; revisar a mano.");
            }
        }
        if ($huerfanos->isEmpty()) {
            $this->line('  (sin pagos MORA huérfanos en esa fecha)');
        }

        // ── 2) dias_mora insertados al registrar el pago. Se anclan al
        // created_at de los pagos huérfanos (misma transacción, ±60s) para no
        // tocar el historial migrado del legacy, que comparte crédito y fecha.
        $diasMora = collect();
        foreach ($huerfanos as $p) {
            $creado = DB::table('payments')->where('id', $p->id)->value('created_at');
            if (! $creado) {
                continue;
            }
            $diasMora = $diasMora->merge(DB::table('dias_mora')
                ->where('credit_id', $creditId)
                ->whereBetween('created_at', [
                    Carbon::parse($creado)->subMinute(),
                    Carbon::parse($creado)->addMinute(),
                ])
                ->get(['id', 'dias', 'dias_descontados']));
        }
        $diasMora = $diasMora->unique('id')->values();
        foreach ($diasMora as $d) {
            $this->line("  borrar dias_mora #{$d->id} (dias={$d->dias})");
        }

        // ── 3) fecha_cancelacion colgada
        $limpiarFecha = $credit->fecha_cancelacion !== null;
        if ($limpiarFecha) {
            $this->line("  limpiar credits.fecha_cancelacion ({$credit->fecha_cancelacion})");
        }

        // ── 4) mora_acumulada a restaurar
        $moraRestore = $this->resolveMoraAcumulada($creditId);

        // ── Aviso: condonación de interés auditada (el reverse no la restaura)
        $condonacion = DB::table('activity_log')
            ->where('description', 'like', "Cancelación anticipada del crédito {$creditId}:%")
            ->orderByDesc('id')
            ->first(['description', 'created_at']);
        if ($condonacion) {
            $this->warn("  ⚠ {$condonacion->description} ({$condonacion->created_at}).");
            $this->warn('    El reverse NO restaura el interés condonado del cronograma; revisar importe_interes de las últimas cuotas impagas.');
        }

        if ($dry) {
            $this->newLine();
            $this->info('DRY RUN — no se aplicó nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($creditId, $huerfanos, $diasMora, $limpiarFecha, $moraRestore) {
            if ($huerfanos->isNotEmpty()) {
                DB::table('payments')->whereIn('id', $huerfanos->pluck('id'))->delete();
            }
            if ($diasMora->isNotEmpty()) {
                DB::table('dias_mora')->whereIn('id', $diasMora->pluck('id'))->delete();
            }
            if ($limpiarFecha) {
                DB::table('credits')->where('id', $creditId)->update(['fecha_cancelacion' => null, 'updated_at' => now()]);
            }
            if ($moraRestore) {
                DB::table('mora_acumulada')->insert([
                    'credit_id' => $creditId,
                    'importe' => $moraRestore['importe'],
                    'dias' => $moraRestore['dias'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '✓ Crédito #%d limpio: %d pago(s) MORA huérfano(s) borrado(s), %d dias_mora, fecha_cancelacion %s, mora_acumulada %s.',
            $creditId,
            $huerfanos->count(),
            $diasMora->count(),
            $limpiarFecha ? 'limpiada' : 'ya estaba limpia',
            $moraRestore ? "restaurada ({$moraRestore['importe']} / {$moraRestore['dias']} días)" : 'sin cambios'
        ));

        return self::SUCCESS;
    }

    /**
     * Valor de mora_acumulada a restaurar: manual (--mora-importe/--mora-dias)
     * o copiado de huaca_moraacum del legacy. Null si ya existe o no hay fuente.
     */
    private function resolveMoraAcumulada(int $creditId): ?array
    {
        if (DB::table('mora_acumulada')->where('credit_id', $creditId)->exists()) {
            $this->line('  mora_acumulada: el crédito ya tiene fila, no se toca.');

            return null;
        }

        if ($this->option('mora-importe') !== null) {
            $restore = [
                'importe' => round((float) $this->option('mora-importe'), 2),
                'dias' => (int) ($this->option('mora-dias') ?? 0),
            ];
            $this->line("  restaurar mora_acumulada (manual): {$restore['importe']} / {$restore['dias']} días");

            return $restore;
        }

        try {
            $legacy = DB::connection('legacy')->table('moraacum')->where('idcab', $creditId)->first();
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Conexión legacy no disponible; usa --mora-importe/--mora-dias para restaurar la mora acumulada.');

            return null;
        }

        if (! $legacy) {
            $this->line('  mora_acumulada: el legacy no tiene fila para este crédito, nada que restaurar.');

            return null;
        }

        $restore = ['importe' => (float) $legacy->importe, 'dias' => (int) $legacy->dias];
        $this->line("  restaurar mora_acumulada (desde legacy): {$restore['importe']} / {$restore['dias']} días");

        return $restore;
    }
}
