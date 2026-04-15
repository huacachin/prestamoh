<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class ChangeStatus extends Component
{
    public $search = '';
    public $creditId = null;
    public $newSituacion = '';
    public $fecha;

    protected $situaciones = [
        'Cancelado',
        'Activo',
        'R. Capital',
        'R.C.P. Int. Cong',
        'Judicializado',
        'Condonado',
    ];

    public function mount()
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function rules()
    {
        return [
            'creditId'     => 'required|exists:credits,id',
            'newSituacion' => 'required|in:' . implode(',', $this->situaciones),
            'fecha'        => 'required|date',
        ];
    }

    public function messages()
    {
        return [
            'creditId.required'     => 'Debe seleccionar un crédito.',
            'newSituacion.required' => 'Debe seleccionar una situación.',
            'newSituacion.in'       => 'La situación seleccionada no es válida.',
            'fecha.required'        => 'La fecha es obligatoria.',
            'fecha.date'            => 'La fecha no es válida.',
        ];
    }

    public function selectCredit($id)
    {
        $this->creditId = $id;
    }

    public function changeStatus()
    {
        $this->validate();

        $credit = Credit::findOrFail($this->creditId);

        $credit->situacion = $this->newSituacion;

        if ($this->newSituacion === 'Activo') {
            $credit->estado = 'active';
        } else {
            $credit->estado = 'inactive';
        }

        if ($this->newSituacion === 'Cancelado') {
            $credit->fecha_cancelacion = $this->fecha;
        }

        $credit->save();

        $this->dispatch('successAlert', ['message' => 'Estado del crédito actualizado correctamente.']);

        $this->reset(['creditId', 'newSituacion', 'search']);
        $this->fecha = now()->format('Y-m-d');
    }

    public function getCredits()
    {
        $term = trim($this->search);

        return Credit::query()
            ->with(['client'])
            ->where('situacion', 'Activo')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('id', 'like', "%{$term}%")
                      ->orWhereHas('client', fn ($c) =>
                          $c->where('nombre', 'like', "%{$term}%")
                            ->orWhere('apellido_pat', 'like', "%{$term}%")
                            ->orWhere('apellido_mat', 'like', "%{$term}%")
                            ->orWhere('documento', 'like', "%{$term}%")
                      );
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.credits.change-status', [
            'credits'      => $this->getCredits(),
            'situaciones'  => $this->situaciones,
        ]);
    }
}
