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
            $this->search = $credit->id . ' - ' . ($credit->client?->nombre ?? '') . ' ' . ($credit->client?->apellido_pat ?? '');
        }
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

        $credit->update([
            'refinanciado'      => false,
            'estado'            => 1,
            'situacion'         => 'Activo',
            'fecha_cancelacion' => null,
        ]);

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
            $isSuperUsuario = $user->hasRole('superusuario');

            $query = Credit::query()
                ->with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->select('id', 'client_id', 'importe', 'situacion', 'fecha_cancelacion', 'cuotas', 'tipo_planilla', 'interes', 'fecha_prestamo');

            if (!$isSuperUsuario) {
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

        if ($this->selectedId) {
            $selectedCredit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->find($this->selectedId);
        }

        return view('livewire.credits.activate', [
            'results'        => $results,
            'selectedCredit' => $selectedCredit,
        ]);
    }
}
