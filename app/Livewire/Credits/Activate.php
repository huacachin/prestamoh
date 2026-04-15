<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class Activate extends Component
{
    public $creditId = '';
    public $search = '';

    public function rules()
    {
        return [
            'creditId' => 'required|exists:credits,id',
        ];
    }

    public function messages()
    {
        return [
            'creditId.required' => 'Debe seleccionar un crédito.',
            'creditId.exists' => 'El crédito seleccionado no existe.',
        ];
    }

    public function activate()
    {
        $this->validate();

        $credit = Credit::findOrFail($this->creditId);

        if (! in_array($credit->situacion, ['Cancelado', 'Refinanciado'])) {
            $this->dispatch('errorAlert', ['message' => 'Solo se pueden activar créditos Cancelados o Refinanciados.']);
            return;
        }

        $credit->update([
            'situacion'         => 'Activo',
            'estado'            => 1,
            'fecha_cancelacion' => null,
        ]);

        $this->creditId = '';
        $this->dispatch('successAlert', ['message' => 'Préstamo activado correctamente.']);
    }

    public function getCreditsList()
    {
        $term = trim($this->search);

        return Credit::query()
            ->with('client')
            ->whereIn('situacion', ['Cancelado', 'Refinanciado'])
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->whereHas('client', fn ($c) =>
                        $c->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('apellido_mat', 'like', "%{$term}%")
                          ->orWhere('documento', 'like', "%{$term}%")
                    )
                    ->orWhere('id', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        $credits = $this->getCreditsList();

        return view('livewire.credits.activate', compact('credits'));
    }
}
