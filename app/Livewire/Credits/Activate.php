<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class Activate extends Component
{
    public $tipoe = 'Pago-Credito';
    public $search = '';
    public $selectedId = null;
    public $showDropdown = false;

    public function updatedSearch()
    {
        $this->selectedId = null;
        $this->showDropdown = strlen(trim($this->search)) >= 1;
    }

    public function selectCredit($id)
    {
        $this->selectedId = $id;
        $this->showDropdown = false;

        $credit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')->find($id);
        if ($credit) {
            $this->search = $credit->id . ' - ' . trim(($credit->client?->apellido_pat ?? '') . ' ' . ($credit->client?->apellido_mat ?? '') . ' ' . ($credit->client?->nombre ?? ''));
        }
    }

    /**
     * Saldo pendiente del cronograma (cap + int + exc − aplicados). Un
     * crédito cancelado/refinanciado con saldo > 0 quedó así a propósito
     * (interés condonado al cancelar, o saldo trasladado al refinanciar):
     * re-activarlo reabriría deuda que ya no existe.
     */
    private function saldoPendienteDe(int $creditId): float
    {
        return round((float) \Illuminate\Support\Facades\DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->selectRaw('SUM(importe_cuota + importe_interes + importe_excedente
                - importe_aplicado - interes_aplicado - excedente_aplicado) s')
            ->value('s'), 2);
    }

    public function activate()
    {
        if (!$this->selectedId) {
            $this->dispatch('errorAlert', ['message' => 'Debe seleccionar un préstamo.']);
            return;
        }

        $credit = Credit::find($this->selectedId);
        if (!$credit) {
            $this->dispatch('errorAlert', ['message' => 'El préstamo seleccionado no existe.']);
            return;
        }

        $saldo = $this->saldoPendienteDe($credit->id);
        if ($saldo > 0.01) {
            $this->dispatch('errorAlert', ['message' => 'No se puede re-activar: el crédito tiene saldo pendiente de S/ '.number_format($saldo, 2).'.']);

            return;
        }

        $credit->update([
            'refinanciado'      => false,
            'estado'            => 1,
            'situacion'         => 'Activo',
            'fecha_cancelacion' => null,
        ]);

        \App\Support\Audit::log("Re-activó el crédito #{$credit->id}", $credit);

        $this->selectedId = null;
        $this->search = '';
        $this->showDropdown = false;
        $this->dispatch('successAlert', ['message' => 'Se Re-Activó con éxito']);
    }

    public function render()
    {
        $results = collect();
        $selectedCredit = null;

        if ($this->showDropdown && strlen(trim($this->search)) >= 1) {
            $term = trim($this->search);
            $user = auth()->user();

            $query = Credit::query()
                ->with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->select('id', 'client_id', 'importe', 'situacion', 'fecha_cancelacion', 'cuotas', 'tipo_planilla', 'interes', 'fecha_prestamo');

            // Sin permiso de bypass, solo se activan los cancelados HOY (legacy).
            if (!$user->can('caja.bypass-fecha-anterior')) {
                $query->where('fecha_cancelacion', today());
            }

            $query->where(function ($q) use ($term) {
                $q->where('id', 'like', "%{$term}%")
                  ->orWhereHas('client', fn ($c) =>
                      $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('apellido_pat', 'like', "%{$term}%")
                        ->orWhere('apellido_mat', 'like', "%{$term}%")
                        ->orWhere('documento', 'like', "%{$term}%")
                  );
            });

            $results = $query->orderByDesc('id')->limit(20)->get();
        }

        $saldoSel = 0.0;
        if ($this->selectedId) {
            $selectedCredit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->find($this->selectedId);
            $saldoSel = $this->saldoPendienteDe($this->selectedId);
        }

        return view('livewire.credits.activate', [
            'results'        => $results,
            'selectedCredit' => $selectedCredit,
            'saldoSel'       => $saldoSel,
        ]);
    }
}
