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
            // Solo cajas operativas 1 y 3 (comportamiento histórico del reporte):
            // los asientos del Área Legal (caja=4) llevan su propio tablero y no
            // deben mezclarse aquí. OJO deuda técnica preexistente: incluir la
            // caja 3 duplica los movimientos "Fijos" espejados — se conserva tal
            // cual porque cambiarlo altera un reporte que el negocio ya concilia.
            $incomes = Income::query()
                ->whereIn('caja', [1, 3])
                ->whereBetween('date', [$this->fecha_desde, $this->fecha_hasta])
                ->orderByDesc('date')
                ->get();

            $expenses = Expense::query()
                ->whereIn('caja', [1, 3])
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
