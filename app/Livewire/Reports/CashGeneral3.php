<?php

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class CashGeneral3 extends Component
{
    public $month;
    public $year;

    public function mount()
    {
        $this->month = (int) date('m');
        $this->year  = (int) date('Y');
    }

    public function search() {}

    public function render()
    {
        $year  = (int) $this->year;
        $month = (int) $this->month;

        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
        $endMonth   = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today      = Carbon::today()->format('Y-m-d');
        $endLimit   = min($endMonth, $today);

        // ─── PRECARGAS (1 query c/u) ───────────────────────────────────
        // INTERES y MORA por día (legacy: huaca_totcaj3.interes y .mora)
        $payTotalsByDate = Payment::query()
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->whereIn('tipo', ['INTERES', 'MORA'])
            ->selectRaw("DATE(fecha) as f, tipo, SUM(monto) as total")
            ->groupBy('f', 'tipo')
            ->get()
            ->groupBy('f');

        // INGRESOS Caja 3 (legacy: huaca_ingreso3)
        $incomesByDate = Income::query()
            ->where('caja', 3)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));

        // EGRESOS Caja 3 (legacy: huaca_entrada3)
        $expensesByDate = Expense::query()
            ->where('caja', 3)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($r) => $r->date->format('Y-m-d'));

        // ─── PROCESAR DÍA POR DÍA ──────────────────────────────────────
        $rows = [];
        $balanceAcumulado = 0; // legacy: $totalImporte0
        $sumIngresoTotal = 0;
        $sumEgresoTotal  = 0;
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $today) break;

            $items = [];
            $cont = 0;
            $cuacaj22 = 0;
            $sumIngresoDay = 0;
            $sumEgresoDay  = 0;

            // 1. INTERES y MORA del día (legacy: huaca_totcaj3)
            $payTotals = $payTotalsByDate->get($date, collect());
            $interes = (float) ($payTotals->where('tipo', 'INTERES')->first()->total ?? 0);
            $mora    = (float) ($payTotals->where('tipo', 'MORA')->first()->total ?? 0);

            if ($interes > 0 || $mora > 0) {
                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => 'CLIENTE', 'detalle' => 'INTERES',
                    'ingreso' => $interes, 'egreso' => 0,
                ];
                $sumIngresoDay += $interes;
                $cuacaj22 += $interes;
                $balanceAcumulado += $interes;

                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => 'CLIENTE', 'detalle' => 'MORA',
                    'ingreso' => $mora, 'egreso' => 0,
                ];
                $sumIngresoDay += $mora;
                $cuacaj22 += $mora;
                $balanceAcumulado += $mora;
            }

            // 2. EGRESOS Caja 3 (huaca_entrada3)
            foreach ($expensesByDate->get($date, collect()) as $exp) {
                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => $exp->reason ?? '',
                    'detalle' => $exp->detail ?? '',
                    'ingreso' => 0, 'egreso' => (float) $exp->total,
                ];
                $sumEgresoDay += (float) $exp->total;
                $cuacaj22 += (float) $exp->total;
            }

            // 3. INGRESOS Caja 3 (huaca_ingreso3)
            foreach ($incomesByDate->get($date, collect()) as $inc) {
                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => trim(($inc->reason ?? '') . ' ' . ($inc->asesor ?? '')),
                    'detalle' => $inc->detail ?? '',
                    'ingreso' => (float) $inc->total, 'egreso' => 0,
                ];
                $sumIngresoDay += (float) $inc->total;
                $cuacaj22 += (float) $inc->total;
                $balanceAcumulado += (float) $inc->total;
            }

            if ($cuacaj22 == 0) continue;

            $saldoDia = $balanceAcumulado - $sumEgresoDay;
            $balanceAcumulado = $saldoDia;
            $sumIngresoTotal += $sumIngresoDay;
            $sumEgresoTotal  += $sumEgresoDay;

            $rows[] = [
                'date'           => $date,
                'date_label'     => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                'items'          => $items,
                'total_ingreso'  => $sumIngresoDay,
                'total_egreso'   => $sumEgresoDay,
                'saldo'          => $saldoDia,
            ];
        }

        // ─── RESUMEN MENSUAL ───────────────────────────────────────────
        $totalInteresMes = (float) Payment::where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->where('tipo', 'INTERES')->sum('monto');

        $totalMoraMes = (float) Payment::where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->where('tipo', 'MORA')->sum('monto');

        // Por asesor: agrupar incomes Caja 3 por (reason, asesor)
        $byAdvisorRaw = Income::query()
            ->where('caja', 3)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->selectRaw('reason as aa, asesor as asesores, SUM(total) as gm, SUM(COALESCE(documento, 0)) as tm')
            ->groupBy('aa', 'asesores')
            ->orderBy('asesores')
            ->get();

        // Sumar totalGm para totalGeneral del resumen
        $totGm = (float) $byAdvisorRaw->sum('gm');
        $newMonto = $totalInteresMes + $totalMoraMes + $totGm;

        return view('livewire.reports.cash-general-3', [
            'report' => [
                'days'            => $rows,
                'total_ingresos'  => $sumIngresoTotal,
                'total_egresos'   => $sumEgresoTotal,
                'balance_general' => $balanceAcumulado,
                'total_interes'   => $totalInteresMes,
                'total_mora'      => $totalMoraMes,
                'by_advisor'      => $byAdvisorRaw,
                'total_advisor'   => $totGm,
                'total_resumen'   => $newMonto,
            ],
        ]);
    }
}
