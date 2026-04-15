<?php

namespace App\Livewire\Reports;

use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class Payments extends Component
{
    public $fecha_desde;
    public $fecha_hasta;

    public function mount()
    {
        $this->fecha_desde = Carbon::today()->startOfMonth()->format('Y-m-d');
        $this->fecha_hasta = Carbon::today()->format('Y-m-d');
    }

    public function search()
    {
        // triggers re-render
    }

    public function render()
    {
        $payments = collect();
        $totals = (object) ['CAPITAL' => 0, 'INTERES' => 0, 'MORA' => 0, 'total' => 0];

        if ($this->fecha_desde && $this->fecha_hasta) {
            $payments = Payment::query()
                ->with(['credit.client'])
                ->whereBetween('fecha', [$this->fecha_desde, $this->fecha_hasta])
                ->orderByDesc('fecha')
                ->get();

            $totals = (object) [
                'CAPITAL' => $payments->where('tipo', 'CAPITAL')->sum('monto'),
                'INTERES' => $payments->where('tipo', 'INTERES')->sum('monto'),
                'MORA'    => $payments->where('tipo', 'MORA')->sum('monto'),
                'total'   => $payments->sum('monto'),
            ];
        }

        return view('livewire.reports.payments', compact('payments', 'totals'));
    }
}
