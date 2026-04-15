<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Livewire\Component;

class Daily extends Component
{
    public string $fecha = '';

    public function mount()
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function render()
    {
        $payments = Payment::query()
            ->with(['credit.client', 'installment:id,num_cuota', 'user:id,name'])
            ->whereDate('fecha', $this->fecha)
            ->orderBy('id')
            ->get();

        $totalMonto = $payments->sum('monto');

        return view('livewire.payments.daily', compact('payments', 'totalMonto'));
    }
}
