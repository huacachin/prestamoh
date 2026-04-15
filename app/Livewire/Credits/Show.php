<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class Show extends Component
{
    public Credit $credit;

    public function mount(int $id)
    {
        $this->credit = Credit::with([
            'client',
            'installments' => fn ($q) => $q->orderBy('num_cuota'),
            'payments' => fn ($q) => $q->orderByDesc('fecha'),
            'payments.user:id,name',
            'user:id,name',
            'headquarter:id,name',
        ])->findOrFail($id);
    }

    public function render()
    {
        $totalPagado = $this->credit->payments->sum('monto');
        $totalDeuda = $this->credit->installments->sum(fn ($i) => $i->importe_cuota + $i->importe_interes);
        $saldoPendiente = $totalDeuda - $totalPagado;

        return view('livewire.credits.show', compact('totalPagado', 'totalDeuda', 'saldoPendiente'));
    }
}
