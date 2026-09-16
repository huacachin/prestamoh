<?php

namespace App\Console\Commands;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Expense;
use App\Models\MassDeletion;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Revierte el cobro de prueba del 8-set-2026 sobre el credito 28943
 * (mass_deletion 120923, S/ 10.875,00, "No, dejarlo vigente" + Reserva Mora)
 * dejando el credito EXACTAMENTE como estaba antes de las 23:10:58.
 *
 * Son tres rastros:
 *   ①  El cobro: 44 pagos (22 capital + 22 interes) aplicados a las cuotas
 *      19-40 y su cabecera/detalles. Se revierte con la MISMA logica del boton
 *      "revertir" de la UI (App\Livewire\Credits\MassDeleteEdit::reverse()).
 *   ②  Reserva Mora: el cobro SUMO la mora del dia (167,79 / 47 dias) a la
 *      fila de mora_acumulada del credito (48.960,00 / 204 → 49.127,79 / 251).
 *      reverse() no lo deshace; aqui se restaura a los valores previos, que
 *      se pasan como guarda (--mora-importe / --mora-dias) y se verifican
 *      contra la fila actual antes de tocar nada.
 *   ③  dias_mora: pagar() inserta una fila por cobro con atraso; se borra la
 *      de esta operacion (misma fecha+hora que la cabecera).
 *
 * Acotado por diseno al credito 28943. --dry-run muestra el plan sin tocar nada.
 */
class Payments28943RevertirSetiembre extends Command
{
    protected $signature = 'payments:revertir-28943-setiembre
        {--credit=28943 : Credito esperado (guarda)}
        {--mass=120923 : Cabecera mass_deletion del cobro a revertir}
        {--monto=10875.00 : Monto esperado de la cabecera (guarda)}
        {--mora-importe=48960.00 : Importe de mora_acumulada ANTES del cobro (a restaurar)}
        {--mora-dias=204 : Dias de mora_acumulada ANTES del cobro (a restaurar)}
        {--dry-run : Solo mostrar el plan, sin ejecutar}';

    protected $description = 'Revierte el cobro de prueba del 8-set-2026 del credito 28943 (cobro + reserva de mora + dias_mora) como si no hubiese existido';

    public function handle(): int
    {
        $creditId = (int) $this->option('credit');
        $massId = (int) $this->option('mass');
        $montoEsperado = round((float) $this->option('monto'), 2);
        $moraImporte = round((float) $this->option('mora-importe'), 2);
        $moraDias = (int) $this->option('mora-dias');
        $dry = (bool) $this->option('dry-run');

        // ── Guardas ─────────────────────────────────────────────────────────
        $md = MassDeletion::with('details')->find($massId);
        if (! $md) {
            $this->error("No existe mass_deletion {$massId} (¿ya revertido?).");

            return self::FAILURE;
        }
        if ((int) $md->credit_id !== $creditId) {
            $this->error("Guarda: mass_deletion {$massId} pertenece al credito {$md->credit_id}, no al {$creditId}. Abortado.");

            return self::FAILURE;
        }
        if (abs((float) $md->amount - $montoEsperado) > 0.001) {
            $this->error("Guarda: la cabecera {$massId} es de S/ {$md->amount}, se esperaba S/ {$montoEsperado}. Abortado.");

            return self::FAILURE;
        }
        $fecha = $md->getRawOriginal('date');
        $hora = $md->time;

        $mora = DB::table('mora_acumulada')->where('credit_id', $creditId)->first();
        // La mora reservada por ESTE cobro: unica MORA de la operacion no se
        // registro como pago (Reserva), asi que se deduce de la fila actual.
        $moraReservada = $mora ? round((float) $mora->importe - $moraImporte, 2) : 0.0;
        $diasReservados = $mora ? (int) $mora->dias - $moraDias : 0;
        if (! $mora || $moraReservada < -0.001 || $diasReservados < 0) {
            $this->error('Guarda: la fila de mora_acumulada no cuadra con los valores previos indicados ('
                .($mora ? "actual {$mora->importe} / {$mora->dias} dias" : 'no existe')
                ."; previos {$moraImporte} / {$moraDias}). Abortado.");

            return self::FAILURE;
        }

        $diasMora = DB::table('dias_mora')->where('credit_id', $creditId)
            ->where('created_at', $fecha.' '.$hora)->get();

        $this->info($dry ? '── DRY-RUN (no se toca nada) ──' : '── EJECUCION REAL ──');
        $this->line("Credito {$creditId} · cobro mass_deletion {$massId} (S/ {$md->amount}, {$fecha} {$hora})");
        $this->newLine();

        // ── Plan cuota por cuota ────────────────────────────────────────────
        $this->line("① Cobro (mass_deletion {$massId}):");
        $plan = [];
        foreach ($md->details as $det) {
            if (! $det->installment_id) {
                continue;
            }
            $inst = CreditInstallment::find($det->installment_id);
            if (! $inst) {
                continue;
            }
            $plan[$det->installment_id] ??= [
                'num' => $inst->num_cuota,
                'cap0' => (float) $inst->importe_aplicado, 'int0' => (float) $inst->interes_aplicado,
                'cap' => (float) $inst->importe_aplicado, 'int' => (float) $inst->interes_aplicado,
                'exc' => (float) $inst->excedente_aplicado,
            ];
            $m = (float) $det->amount;
            match ($det->tipo) {
                'C', 'C1', 'C3' => $plan[$det->installment_id]['cap'] = max(0, $plan[$det->installment_id]['cap'] - $m),
                'I', 'I1' => $plan[$det->installment_id]['int'] = max(0, $plan[$det->installment_id]['int'] - $m),
                'E' => $plan[$det->installment_id]['exc'] = max(0, $plan[$det->installment_id]['exc'] - $m),
                default => null,
            };
        }
        ksort($plan);
        foreach ($plan as $p) {
            $this->line(sprintf('   cuota %2d: capital %s→%s · interes %s→%s · pagado→0',
                $p['num'], number_format($p['cap0'], 2), number_format($p['cap'], 2),
                number_format($p['int0'], 2), number_format($p['int'], 2)));
        }
        $this->line('   pagos a borrar: '.$md->details->whereNotNull('payment_id')->count()
            .' (+ MORA sueltas de la misma operacion: '.Payment::where('credit_id', $creditId)
                ->where('fecha', $fecha)->where('hora', $hora)->where('tipo', 'MORA')->count().')');
        foreach (Expense::where('mass_deletion_id', $massId)->get() as $e) {
            $this->line("   egreso a borrar: #{$e->id} · S/ {$e->total} · {$e->detail}");
        }

        $this->newLine();
        $this->line('② Reserva de mora (mora_acumulada id '.$mora->id.'):');
        $this->line(sprintf('   importe %s→%s · dias %d→%d (reservado por el cobro: %s / %d dias)',
            number_format((float) $mora->importe, 2), number_format($moraImporte, 2),
            (int) $mora->dias, $moraDias, number_format($moraReservada, 2), $diasReservados));

        $this->newLine();
        $this->line('③ dias_mora de la operacion: '.$diasMora->count().' fila/s a borrar'
            .($diasMora->count() ? ' (ids '.$diasMora->pluck('id')->implode(', ').')' : ''));

        if ($dry) {
            $this->newLine();
            $this->info('Dry-run: no se ejecuto nada. Quita --dry-run para aplicar.');

            return self::SUCCESS;
        }

        // ── Ejecucion (misma logica que MassDeleteEdit::reverse + ② y ③) ────
        DB::transaction(function () use ($md, $creditId, $massId, $fecha, $hora, $mora, $moraImporte, $moraDias, $diasMora) {
            foreach ($md->details as $det) {
                if ($det->payment_id) {
                    Payment::where('id', $det->payment_id)->delete();
                }
                if ($det->installment_id) {
                    $inst = CreditInstallment::find($det->installment_id);
                    if ($inst) {
                        $m = (float) $det->amount;
                        match ($det->tipo) {
                            'C', 'C1', 'C3' => $inst->importe_aplicado = max(0, (float) $inst->importe_aplicado - $m),
                            'I', 'I1' => $inst->interes_aplicado = max(0, (float) $inst->interes_aplicado - $m),
                            'E' => $inst->excedente_aplicado = max(0, (float) $inst->excedente_aplicado - $m),
                            'M' => $inst->importe_mora = 0,
                            default => null,
                        };
                        $inst->pagado = false;
                        $inst->fecha_pago = null;
                        $inst->observacion = null;
                        $inst->save();
                    }
                }
            }

            Payment::where('credit_id', $creditId)->where('fecha', $fecha)->where('hora', $hora)
                ->where('tipo', 'MORA')->delete();

            Credit::where('id', $creditId)->update(['estado' => 1, 'situacion' => 'Activo']);

            foreach (Expense::where('mass_deletion_id', $massId)->get() as $egreso) {
                foreach ($egreso->attachments ?? [] as $att) {
                    $disk = Storage::disk('public');
                    if ($att->path && $disk->exists($att->path)) {
                        $disk->delete($att->path);
                    }
                    if ($att->thumb_path && $disk->exists($att->thumb_path)) {
                        $disk->delete($att->thumb_path);
                    }
                    $att->delete();
                }
                $egreso->delete();
            }

            $md->details()->delete();
            $md->delete();

            // ② La fila vuelve a sus valores previos (incluida la marca de tiempo:
            //    la ultima modificacion real fue la migracion del 1-set).
            DB::table('mora_acumulada')->where('id', $mora->id)->update([
                'importe' => $moraImporte, 'dias' => $moraDias, 'updated_at' => $mora->created_at,
            ]);

            // ③
            if ($diasMora->count()) {
                DB::table('dias_mora')->whereIn('id', $diasMora->pluck('id'))->delete();
            }
        });

        $this->newLine();
        $this->info("✓ Revertido. El credito {$creditId} quedo como antes del cobro de las {$hora}.");

        return self::SUCCESS;
    }
}
