<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MassDeleteEdit extends Component
{
    public MassDeletion $record;

    public function mount(int $id): void
    {
        $this->record = MassDeletion::with(['credit.client', 'details.installment'])->findOrFail($id);
    }

    public function reverse(): void
    {
        if (!auth()->user()?->can('registro.eliminar-masivo.revertir')) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permisos para revertir esta operación.']);
            return;
        }

        DB::transaction(function () {
            foreach ($this->record->details as $det) {
                if ($det->payment_id) {
                    Payment::where('id', $det->payment_id)->delete();
                }

                if ($det->installment_id) {
                    $inst = CreditInstallment::find($det->installment_id);
                    if ($inst) {
                        $monto = (float) $det->amount;
                        match ($det->tipo) {
                            'C', 'C1', 'C3' => $inst->importe_aplicado = max(0, (float) $inst->importe_aplicado - $monto),
                            'I', 'I1'       => $inst->interes_aplicado = max(0, (float) $inst->interes_aplicado - $monto),
                            'M'             => $inst->importe_mora = 0,
                            default         => null,
                        };
                        $inst->pagado = false;
                        $inst->fecha_pago = null;
                        $inst->observacion = null;
                        $inst->save();
                    }
                }
            }

            // Legacy editmasivo.php usa por error `id` de la última cuota como id del crédito
            // (UPDATE cab_cuentacorriente WHERE id=$codigocuotas), por lo que la query nunca
            // matcheaba y el crédito quedaba "Cancelado". Aquí restauramos correctamente.
            if ($this->record->credit_id) {
                Credit::where('id', $this->record->credit_id)->update([
                    'estado'    => 1,
                    'situacion' => 'Activo',
                ]);
            }

            $this->record->details()->delete();
            $this->record->delete();
        });

        session()->flash('success', 'Operación masiva revertida correctamente.');
        $this->redirect(route('credits.mass-delete'));
    }

    public function render()
    {
        return view('livewire.credits.mass-delete-edit');
    }
}
