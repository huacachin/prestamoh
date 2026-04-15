<?php

namespace App\Livewire\Dashboard;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $today = Carbon::today();

        $creditosActivos = Credit::where('situacion', 'Activo')->count();

        $totalCartera = Credit::where('situacion', 'Activo')->sum('importe');

        $cobranzaHoy = Payment::whereDate('fecha', $today)->sum('monto');

        $morosidad = Credit::where('situacion', 'Activo')
            ->whereHas('installments', function ($q) use ($today) {
                $q->where('pagado', false)
                  ->where('fecha_vencimiento', '<', $today);
            })
            ->count();

        $ingresosHoy = Income::whereDate('date', $today)->sum('total');

        $egresosHoy = Expense::whereDate('date', $today)->sum('total');

        $ultimosPagos = Payment::with('credit.client')
            ->latest('fecha')
            ->latest('id')
            ->take(10)
            ->get();

        $creditosRecientes = Credit::with('client')
            ->latest('fecha_prestamo')
            ->latest('id')
            ->take(5)
            ->get();

        return view('livewire.dashboard.index', compact(
            'creditosActivos',
            'totalCartera',
            'cobranzaHoy',
            'morosidad',
            'ingresosHoy',
            'egresosHoy',
            'ultimosPagos',
            'creditosRecientes',
        ));
    }
}
