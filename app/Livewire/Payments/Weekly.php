<?php

namespace App\Livewire\Payments;

use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class Weekly extends Component
{
    public string $fecha_inicio = '';
    public string $fecha_fin = '';

    public function mount()
    {
        $this->fecha_inicio = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->fecha_fin = Carbon::now()->endOfWeek()->format('Y-m-d');
    }

    public function render()
    {
        $payments = Payment::query()
            ->with(['credit.client', 'installment:id,num_cuota', 'user:id,name'])
            ->whereDate('fecha', '>=', $this->fecha_inicio)
            ->whereDate('fecha', '<=', $this->fecha_fin)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $totalMonto = $payments->sum('monto');

        return view('livewire.payments.weekly', compact('payments', 'totalMonto'));
    }
}
