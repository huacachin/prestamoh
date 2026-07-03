<?php

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class Cash extends Component
{
    #[Url(as: 'desde')]
    public $fecha_desde;

    #[Url(as: 'hasta')]
    public $fecha_hasta;

    public function mount()
    {
        if (! request()->has('desde')) {
            $this->fecha_desde = Carbon::today()->startOfMonth()->format('Y-m-d');
        }
        if (! request()->has('hasta')) {
            $this->fecha_hasta = Carbon::today()->format('Y-m-d');
        }
    }

    public function search()
    {
        // triggers re-render
    }

    public function render()
    {
        $incomes = collect();
        $expenses = collect();
        $summary = (object) ['total_ingresos' => 0, 'total_egresos' => 0, 'balance' => 0];

        if ($this->fecha_desde && $this->fecha_hasta) {
            $incomes = Income::query()
                ->whereBetween('date', [$this->fecha_desde, $this->fecha_hasta])
                ->orderByDesc('date')
                ->get();

            $expenses = Expense::query()
                ->whereBetween('date', [$this->fecha_desde, $this->fecha_hasta])
                ->orderByDesc('date')
                ->get();

            $totalIngresos = $incomes->sum('total');
            $totalEgresos = $expenses->sum('total');

            $summary = (object) [
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'balance' => $totalIngresos - $totalEgresos,
            ];
        }

        return view('livewire.reports.cash', compact('incomes', 'expenses', 'summary'));
    }
}
