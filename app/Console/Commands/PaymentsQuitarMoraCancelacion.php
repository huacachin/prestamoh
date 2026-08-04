<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\MassDeletion;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quita la MORA ACUM. cobrada por error al cancelar (se marcó "Reservar mora"
 * pero no "Quitar mora y mora acumulada", así que en vez de perdonarse se
 * cobró). Deja el cobro "como debe ser": capital + interés, sin mora — igual
 * que quedó el 28487 reingresado, y cuadrando con el legacy (huaca_ingreso),
 * que registró estos cobros SIN mora.
 *
 * Por cada crédito, de la fecha dada:
 *   - localiza el pago MORA ACUM. (debe haber exactamente uno),
 *   - su lote (mass_deletion de ese credito+fecha+hora) → le resta la mora al
 *     amount (el total cobrado baja),
 *   - el egreso de depósito vinculado a ese lote (si el cobro fue por depósito)
 *     → le resta la mora al total (para que la caja siga cuadrada),
 *   - borra el pago de mora.
 *
 * NO toca capital/interés, NO reactiva el crédito (sigue Cancelado). El
 * /schedule calcula la mora desde payments tipo MORA, así que al borrarla
 * desaparece. --dry-run muestra el plan sin ejecutar.
 */
class PaymentsQuitarMoraCancelacion extends Command
{
    protected $signature = 'payments:quitar-mora-cancelacion
        {--fecha=2026-08-03 : Fecha de las cancelaciones}
        {--creditos=28955,29174 : Créditos a corregir (coma)}
        {--dry-run : Solo mostrar el plan}';

    protected $description = 'Quita la MORA ACUM. cobrada por error al cancelar, dejando el cobro sin mora (cuadra con el legacy)';

    public function handle(): int
    {
        $fecha = (string) $this->option('fecha');
        $creditos = array_filter(array_map('intval', explode(',', (string) $this->option('creditos'))));
        $dry = (bool) $this->option('dry-run');

        $this->info($dry ? '── DRY-RUN (no se toca nada) ──' : '── EJECUCIÓN REAL ──');
        $this->line("Fecha {$fecha} · créditos: ".implode(', ', $creditos));
        $this->newLine();

        $plan = [];
        foreach ($creditos as $id) {
            $moras = Payment::where('credit_id', $id)->where('tipo', 'MORA')
                ->whereDate('fecha', $fecha)
                ->where('documento', 'like', 'MORA ACUM%')->get();

            if ($moras->count() === 0) {
                $this->line("  crédito {$id}: sin MORA ACUM. hoy — nada que hacer.");

                continue;
            }
            if ($moras->count() > 1) {
                $this->error("  crédito {$id}: hay {$moras->count()} MORA ACUM. hoy (se esperaba 1). Se OMITE por seguridad.");

                continue;
            }

            $mora = $moras->first();
            $lote = MassDeletion::where('credit_id', $id)->whereDate('date', $fecha)
                ->where('time', $mora->hora)->first();
            $egreso = $lote ? Expense::where('mass_deletion_id', $lote->id)->first() : null;

            $this->line("  <fg=yellow>crédito {$id}</>");
            $this->line("     borrar pago MORA #{$mora->id} · S/ {$mora->monto} · {$mora->documento} ({$mora->hora})");
            if ($lote) {
                $nuevo = round((float) $lote->amount - (float) $mora->monto, 2);
                $this->line("     lote #{$lote->id}: amount {$lote->amount} → {$nuevo}");
            } else {
                $this->error('     ¡sin lote para esa hora! (revisar manualmente)');
            }
            if ($egreso) {
                $nuevoE = round((float) $egreso->total - (float) $mora->monto, 2);
                $this->line("     egreso depósito #{$egreso->id}: total {$egreso->total} → {$nuevoE}");
            } else {
                $this->line('     (sin egreso de depósito en ese lote — fue efectivo)');
            }

            $plan[] = compact('mora', 'lote', 'egreso');
        }

        if (empty($plan)) {
            $this->newLine();
            $this->info('Nada que aplicar.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->newLine();
            $this->info('Dry-run: no se ejecutó nada. Quita --dry-run para aplicar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $p) {
                if ($p['lote']) {
                    $p['lote']->update(['amount' => round((float) $p['lote']->amount - (float) $p['mora']->monto, 2)]);
                }
                if ($p['egreso']) {
                    $p['egreso']->update(['total' => round((float) $p['egreso']->total - (float) $p['mora']->monto, 2)]);
                }
                $p['mora']->delete();
            }
        });

        $this->newLine();
        $this->info('✓ Mora quitada. Los cobros quedaron sin mora (capital + interés), créditos siguen Cancelados.');

        return self::SUCCESS;
    }
}
