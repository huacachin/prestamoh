<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $fecha_desde = '';
    public string $fecha_hasta = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);

        $payments = Payment::query()
            ->with(['credit.client', 'user:id,name'])
            ->when($this->fecha_desde !== '', fn ($q) =>
                $q->whereDate('fecha', '>=', $this->fecha_desde)
            )
            ->when($this->fecha_hasta !== '', fn ($q) =>
                $q->whereDate('fecha', '<=', $this->fecha_hasta)
            )
            ->when($term !== '', function ($q) use ($term) {
                $q->whereHas('credit.client', fn ($c) =>
                    $c->where('nombre', 'like', "%{$term}%")
                      ->orWhere('apellido_pat', 'like', "%{$term}%")
                      ->orWhere('apellido_mat', 'like', "%{$term}%")
                      ->orWhere('documento', 'like', "%{$term}%")
                );
            })
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.payments.index', compact('payments'));
    }
}
