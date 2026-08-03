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
 * Revierte la operacion del 3-ago-2026 del credito 28487 dejando el credito
 * EXACTAMENTE como estaba el 2-ago ("como si el 3 de agosto no hubiese
 * existido"). El usuario volvera a ingresar el pago despues.
 *
 * Son dos rastros:
 *   ①  Cobro por deposito Yape (mass_deletion 120063, 8.541,20): 20 pagos,
 *      10 cuotas y el egreso automatico del deposito. Se revierte con la MISMA
 *      logica del boton "revertir" de la UI (App\Livewire\Credits\MassDeleteEdit
 *      ::reverse()): resta los montos exactos de mass_deletion_details a cada
 *      cuota (no las pone a cero, respeta abonos previos), borra pagos, borra
 *      egreso + voucher, borra cabecera y detalles, y deja el credito Activo.
 *   ②  Pago huerfano de mora (payment 502485, 5.420,20 "MORA ACUM."): quedo
 *      suelto tras el cancelar+reactivar de las 16:00. No tiene cabecera de
 *      lote, se borra directo. El /schedule calcula la mora desde payments
 *      (documento LIKE 'MORA%'), asi que al borrarlo desaparecen los 5.420.
 *
 * Acotado por diseno al credito 28487. --dry-run muestra el plan sin tocar nada.
 */
class Payments28487RevertirAgosto extends Command
{
    protected $signature = 'payments:revertir-28487-agosto
        {--credit=28487 : Credito esperado (guarda de seguridad)}
        {--mass=120063 : Cabecera mass_deletion del cobro a revertir}
        {--orphan=502485 : Pago huerfano de mora a borrar}
        {--dry-run : Solo mostrar el plan, sin ejecutar}';

    protected $description = 'Revierte el 3-ago-2026 del credito 28487 (cobro + mora huerfana) como si el dia no hubiese existido';

    public function handle(): int
    {
        $creditId = (int) $this->option('credit');
        $massId = (int) $this->option('mass');
        $orphanId = (int) $this->option('orphan');
        $dry = (bool) $this->option('dry-run');

        $md = MassDeletion::with('details')->find($massId);
        if (! $md) {
            $this->error("No existe mass_deletion {$massId}.");

            return self::FAILURE;
        }
        if ((int) $md->credit_id !== $creditId) {
            $this->error("Guarda: mass_deletion {$massId} pertenece al credito {$md->credit_id}, no al {$creditId}. Abortado.");

            return self::FAILURE;
        }

        $orphan = Payment::find($orphanId);
        if ($orphan && (int) $orphan->credit_id !== $creditId) {
            $this->error("Guarda: pago huerfano {$orphanId} pertenece al credito {$orphan->credit_id}, no al {$creditId}. Abortado.");

            return self::FAILURE;
        }
        if ($orphan && strtoupper(substr((string) $orphan->documento, 0, 4)) !== 'MORA') {
            $this->error("Guarda: pago huerfano {$orphanId} no es MORA (documento='{$orphan->documento}'). Abortado.");

            return self::FAILURE;
        }

        $this->info($dry ? '── DRY-RUN (no se toca nada) ──' : '── EJECUCION REAL ──');
        $this->line("Credito {$creditId} · cobro mass_deletion {$massId} (S/ {$md->amount}) · huerfano mora {$orphanId}");
        $this->newLine();

        // ── Plan detallado (cuota por cuota) ────────────────────────────────
        $this->line('① Cobro (mass_deletion '.$massId.'):');
        $planCuotas = [];
        foreach ($md->details as $det) {
            if (! $det->installment_id) {
                continue;
            }
            $inst = CreditInstallment::find($det->installment_id);
            if (! $inst) {
                continue;
            }
            $planCuotas[$det->installment_id] ??= [
                'num' => $inst->num_cuota,
                'cap' => (float) $inst->importe_aplicado,
                'int' => (float) $inst->interes_aplicado,
                'exc' => (float) $inst->excedente_aplicado,
            ];
            $monto = (float) $det->amount;
            match ($det->tipo) {
                'C', 'C1', 'C3' => $planCuotas[$det->installment_id]['cap'] = max(0, $planCuotas[$det->installment_id]['cap'] - $monto),
                'I', 'I1' => $planCuotas[$det->installment_id]['int'] = max(0, $planCuotas[$det->installment_id]['int'] - $monto),
                'E' => $planCuotas[$det->installment_id]['exc'] = max(0, $planCuotas[$det->installment_id]['exc'] - $monto),
                default => null,
            };
        }
        foreach ($planCuotas as $insId => $p) {
            $orig = CreditInstallment::find($insId);
            $this->line(sprintf(
                '   cuota %2d: capital %s→%s · interes %s→%s · pagado 1→0',
                $p['num'],
                number_format((float) $orig->importe_aplicado, 2),
                number_format($p['cap'], 2),
                number_format((float) $orig->interes_aplicado, 2),
                number_format($p['int'], 2),
            ));
        }
        $nPagos = $md->details->whereNotNull('payment_id')->count();
        $this->line("   pagos a borrar: {$nPagos}");

        $egresos = Expense::where('mass_deletion_id', $massId)->get();
        foreach ($egresos as $e) {
            $nAtt = $e->attachments()->count();
            $this->line("   egreso a borrar: #{$e->id} · S/ {$e->total} · {$e->detail} ({$nAtt} voucher/s)");
        }

        $this->newLine();
        $this->line('② Mora huerfana:');
        if ($orphan) {
            $this->line("   pago #{$orphan->id} · {$orphan->documento} · S/ {$orphan->monto} → borrar");
        } else {
            $this->line("   (pago {$orphanId} no existe; nada que borrar)");
        }

        if ($dry) {
            $this->newLine();
            $this->info('Dry-run: no se ejecuto nada. Quita --dry-run para aplicar.');

            return self::SUCCESS;
        }

        // ── Ejecucion (misma logica que MassDeleteEdit::reverse) ────────────
        DB::transaction(function () use ($md, $creditId, $massId, $orphan) {
            foreach ($md->details as $det) {
                if ($det->payment_id) {
                    Payment::where('id', $det->payment_id)->delete();
                }
                if ($det->installment_id) {
                    $inst = CreditInstallment::find($det->installment_id);
                    if ($inst) {
                        $monto = (float) $det->amount;
                        match ($det->tipo) {
                            'C', 'C1', 'C3' => $inst->importe_aplicado = max(0, (float) $inst->importe_aplicado - $monto),
                            'I', 'I1' => $inst->interes_aplicado = max(0, (float) $inst->interes_aplicado - $monto),
                            'E' => $inst->excedente_aplicado = max(0, (float) $inst->excedente_aplicado - $monto),
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

            Credit::where('id', $creditId)->update([
                'estado' => 1,
                'situacion' => 'Activo',
            ]);

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

            // ② Mora huerfana suelta (sin cabecera de lote).
            if ($orphan) {
                $orphan->delete();
            }
        });

        $this->newLine();
        $this->info('✓ Revertido. El credito 28487 quedo como el 2-ago. Listo para reingresar el pago.');

        return self::SUCCESS;
    }
}
