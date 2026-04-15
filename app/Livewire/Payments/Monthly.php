<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Livewire\Component;

class Monthly extends Component
{
    public int $mes;
    public int $anio;

    public function mount()
    {
        $this->mes = (int) now()->format('m');
        $this->anio = (int) now()->format('Y');
    }

    public function render()
    {
        $payments = Payment::query()
            ->with(['credit.client', 'installment:id,num_cuota', 'user:id,name'])
            ->whereMonth('fecha', $this->mes)
            ->whereYear('fecha', $this->anio)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $totalMonto = $payments->sum('monto');

        return view('livewire.payments.monthly', compact('payments', 'totalMonto'));
    }
}
