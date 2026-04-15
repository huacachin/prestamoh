<?php

namespace App\Livewire\Cash;

use App\Models\CashOpening;
use App\Models\Expense;
use App\Models\Income;
use Livewire\Component;

class Balance extends Component
{
    public string $fecha = '';

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function render()
    {
        $user = auth()->user();
        $hqId = $user->headquarter_id;

        $opening = CashOpening::where('headquarter_id', $hqId)
            ->where('fecha', $this->fecha)
            ->first();

        $saldo_inicial = $opening ? (float) $opening->saldo_inicial : 0;

        $incomes = Income::where('headquarter_id', $hqId)
            ->whereDate('date', $this->fecha)
            ->orderBy('id')
            ->get();

        $expenses = Expense::where('headquarter_id', $hqId)
            ->whereDate('date', $this->fecha)
            ->orderBy('id')
            ->get();

        $total_ingresos = $incomes->sum('total');
        $total_egresos  = $expenses->sum('total');
        $saldo_final    = $saldo_inicial + $total_ingresos - $total_egresos;

        return view('livewire.cash.balance', compact(
            'opening',
            'saldo_inicial',
            'incomes',
            'expenses',
            'total_ingresos',
            'total_egresos',
            'saldo_final'
        ));
    }
}
