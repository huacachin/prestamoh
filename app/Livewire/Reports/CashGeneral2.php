<?php

namespace App\Livewire\Reports;

use App\Models\CashOpening;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Credit;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class CashGeneral2 extends Component
{
    public $month;
    public $year;

    public function mount()
    {
        $this->month = (int) date('m');
        $this->year  = (int) date('Y');
    }

    public function search()
    {
        // triggers re-render
    }

    public function generateReport(): array
    {
        $daysInMonth = Carbon::create($this->year, $this->month)->daysInMonth;
        $rows = [];
        $cont = 0;
        $runningBalance = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');

            if ($date > Carbon::today()->format('Y-m-d')) {
                break;
            }

            $dayHasData = false;
            $dayRows = [];

            // 1. Cash Opening (Apertura)
            $opening = CashOpening::where('fecha', $date)->first();
            if ($opening) {
                $cont++;
                $dayRows[] = [
                    'n'       => $cont,
                    'fecha'   => $date,
                    'cliente' => 'APERTURA DE MES',
                    'detalle' => 'SALDO MES ANTERIOR',
                    'ingreso' => (float) $opening->saldo_inicial,
                    'egreso'  => 0,
                ];
                $runningBalance += (float) $opening->saldo_inicial;
                $dayHasData = true;
            }

            // 2. Credit payments (income from payments table) - sum by date
            $paymentsTotal = Payment::where('fecha', $date)->sum('monto');
            if ($paymentsTotal > 0) {
                $cont++;
                $dayRows[] = [
                    'n'       => $cont,
                    'fecha'   => $date,
                    'cliente' => 'CLIENTE',
                    'detalle' => 'CREDITO',
                    'ingreso' => (float) $paymentsTotal,
                    'egreso'  => 0,
                ];
                $runningBalance += (float) $paymentsTotal;
                $dayHasData = true;
            }

            // 3. Credits disbursed (expenses - money going out)
            $creditsDisbursed = Credit::where('fecha_prestamo', $date)->sum('importe');
            if ($creditsDisbursed > 0) {
                $cont++;
                $dayRows[] = [
                    'n'       => $cont,
                    'fecha'   => $date,
                    'cliente' => 'CLIENTE',
                    'detalle' => 'CREDITO',
                    'ingreso' => 0,
                    'egreso'  => (float) $creditsDisbursed,
                ];
                $runningBalance -= (float) $creditsDisbursed;
                $dayHasData = true;
            }

            // 4. Other income (from incomes table)
            $incomes = Income::where('date', $date)->get();
            foreach ($incomes as $income) {
                $cont++;
                $userName = $income->user ? $income->user->name : '';
                $dayRows[] = [
                    'n'       => $cont,
                    'fecha'   => $date,
                    'cliente' => $userName . ' - ' . $income->reason,
                    'detalle' => $income->detail ?? '',
                    'ingreso' => (float) $income->total,
                    'egreso'  => 0,
                ];
                $runningBalance += (float) $income->total;
                $dayHasData = true;
            }

            // 5. Expenses (from expenses table)
            $expenses = Expense::where('date', $date)->get();
            foreach ($expenses as $expense) {
                $cont++;
                $userName = $expense->user ? $expense->user->name : '';
                $dayRows[] = [
                    'n'       => $cont,
                    'fecha'   => $date,
                    'cliente' => $userName . ' - ' . $expense->reason,
                    'detalle' => $expense->detail ?? '',
                    'ingreso' => 0,
                    'egreso'  => (float) $expense->total,
                ];
                $runningBalance -= (float) $expense->total;
                $dayHasData = true;
            }

            if ($dayHasData) {
                $dayTotalIngreso = collect($dayRows)->sum('ingreso');
                $dayTotalEgreso  = collect($dayRows)->sum('egreso');

                $rows[] = [
                    'date'       => $date,
                    'date_label' => Carbon::parse($date)->translatedFormat('l d \d\e F Y'),
                    'items'      => $dayRows,
                    'total_ingreso' => $dayTotalIngreso,
                    'total_egreso'  => $dayTotalEgreso,
                    'saldo'         => $dayTotalIngreso - $dayTotalEgreso,
                ];
            }

            $cont = 0; // reset counter per day like legacy
        }

        $totalIngresos = collect($rows)->sum('total_ingreso');
        $totalEgresos  = collect($rows)->sum('total_egreso');

        return [
            'days'            => $rows,
            'total_ingresos'  => $totalIngresos,
            'total_egresos'   => $totalEgresos,
            'balance_general' => $totalIngresos - $totalEgresos,
        ];
    }

    public function render()
    {
        $report = $this->generateReport();

        return view('livewire.reports.cash-general-2', [
            'report' => $report,
        ]);
    }
}
