<?php

namespace App\Livewire\Reports;

use App\Models\CashOpening;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function search() {}

    public function render()
    {
        $year  = (int) $this->year;
        $month = (int) $this->month;

        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
        $endMonth   = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today      = Carbon::today()->format('Y-m-d');
        $endLimit   = min($endMonth, $today);

        // ─── PRECARGAS DEL MES (1 query c/u) ───────────────────────────
        $allPayments = Payment::query()
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->with(['credit:id,tipo_planilla,refinanciado,fecha_cancelacion,importe,interes,cuotas'])
            ->get();

        $allCredits = Credit::query()
            ->where('fecha_actualizacion', '>=', $startMonth)
            ->where('fecha_actualizacion', '<=', $endLimit)
            ->get(['id', 'fecha_actualizacion', 'importe']);

        $allOpenings = CashOpening::query()
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->get(['id', 'fecha', 'saldo_inicial']);

        $allIncomes = Income::query()
            ->where('caja', 1)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->whereIn('modo', ['Otros', 'Fijos'])
            ->with('user:id,name,username')
            ->get();

        $allExpenses = Expense::query()
            ->where('caja', 1)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->whereIn('modo', ['Otros', 'Fijos'])
            ->with('user:id,name,username')
            ->get();

        // Pre-calcular pagos previos para REFI cancelados
        $refiCancelIds = $allPayments
            ->pluck('credit')
            ->filter(fn ($c) => $c && $c->refinanciado &&
                $c->fecha_cancelacion?->format('Y-m-d') >= $startMonth &&
                $c->fecha_cancelacion?->format('Y-m-d') <= $endLimit)
            ->pluck('id')
            ->unique()
            ->values();

        $pagosPreviosPorCredito = [];
        if ($refiCancelIds->isNotEmpty()) {
            $rows = DB::table('payments as p')
                ->join('credits as c', 'p.credit_id', '=', 'c.id')
                ->whereIn('p.credit_id', $refiCancelIds)
                ->whereColumn('p.fecha', '<', 'c.fecha_cancelacion')
                ->groupBy('p.credit_id')
                ->select('p.credit_id', DB::raw('SUM(p.monto) as total'))
                ->get();
            foreach ($rows as $r) {
                $pagosPreviosPorCredito[$r->credit_id] = (float) $r->total;
            }
        }

        // Agrupar por fecha
        $paymentsByDate = $allPayments->groupBy(fn ($p) => $p->fecha->format('Y-m-d'));
        $creditsByDate  = $allCredits->groupBy(fn ($c) => $c->fecha_actualizacion->format('Y-m-d'));
        $openingsByDate = $allOpenings->groupBy(fn ($o) => $o->fecha->format('Y-m-d'));
        $incomesByDate  = $allIncomes->groupBy(fn ($i) => $i->date->format('Y-m-d'));
        $expensesByDate = $allExpenses->groupBy(fn ($e) => $e->date->format('Y-m-d'));

        // ─── PROCESAR DÍA POR DÍA ──────────────────────────────────────
        $rows = [];
        $balanceAcumulado = 0; // legacy: $totalImporte0
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $today) break;

            $items = [];
            $cont = 0;
            $sumIngresoDay = 0;
            $sumEgresoDay  = 0;

            // 1. APERTURA DE MES (caja)
            foreach ($openingsByDate->get($date, collect()) as $op) {
                $cont++;
                $monto = (float) $op->saldo_inicial;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => 'APERTURA DE MES',
                    'detalle' => 'SALDO MES ANTERIOR',
                    'ingreso' => $monto, 'egreso' => 0,
                ];
                $sumIngresoDay += $monto;
                $balanceAcumulado += $monto;
            }

            // 2. INGRESO CRÉDITO (sum total con mora + lógica REFI igual a CashGeneral1)
            $dayPayments = $paymentsByDate->get($date, collect());
            $totalIngresoCredito = $this->calcularTotalIngresoCredito($dayPayments, $date, $pagosPreviosPorCredito);
            if ($totalIngresoCredito > 0) {
                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => 'CLIENTE',
                    'detalle' => 'CREDITO',
                    'ingreso' => $totalIngresoCredito, 'egreso' => 0,
                ];
                $sumIngresoDay += $totalIngresoCredito;
                $balanceAcumulado += $totalIngresoCredito;
            }

            // 3. EGRESO CRÉDITO (créditos cuyo fecha_actualizacion = date)
            $sumCreditEgreso = (float) $creditsByDate->get($date, collect())->sum('importe');
            if ($sumCreditEgreso > 0) {
                $cont++;
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => 'CLIENTE',
                    'detalle' => 'CREDITO',
                    'ingreso' => 0, 'egreso' => $sumCreditEgreso,
                ];
                $sumEgresoDay += $sumCreditEgreso;
            }

            // 4. INGRESO Otros/Fijos
            foreach ($incomesByDate->get($date, collect()) as $inc) {
                $cont++;
                $userName = $inc->user?->username ?? $inc->user?->name ?? '';
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => trim("{$userName}-{$inc->reason}-{$inc->asesor}"),
                    'detalle' => $inc->detail ?? '',
                    'ingreso' => (float) $inc->total, 'egreso' => 0,
                ];
                $sumIngresoDay += (float) $inc->total;
                $balanceAcumulado += (float) $inc->total;
            }

            // 5. EGRESO Otros/Fijos
            foreach ($expensesByDate->get($date, collect()) as $exp) {
                $cont++;
                $userName = $exp->user?->username ?? $exp->user?->name ?? '';
                $items[] = [
                    'n' => $cont, 'fecha' => $date,
                    'cliente' => trim("{$userName}-{$exp->reason}"),
                    'detalle' => $exp->detail ?? '',
                    'ingreso' => 0, 'egreso' => (float) $exp->total,
                ];
                $sumEgresoDay += (float) $exp->total;
            }

            if (count($items) === 0) continue;

            // Saldo acumulado del día (legacy: $tottoto2 = $totalImporte0 - egresos)
            $saldoDia = $balanceAcumulado - $sumEgresoDay;
            $balanceAcumulado = $saldoDia; // reasignar como legacy

            $rows[] = [
                'date'           => $date,
                'date_label'     => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                'items'          => $items,
                'total_ingreso'  => $sumIngresoDay,
                'total_egreso'   => $sumEgresoDay,
                'saldo'          => $saldoDia,
            ];
        }

        return view('livewire.reports.cash-general-2', [
            'report' => [
                'days'            => $rows,
                'balance_general' => $balanceAcumulado, // legacy: $totalImporte0 final
            ],
        ]);
    }

    /**
     * Calcula el ingreso crédito del día replicando la lógica del legacy CashGeneral1.
     * Equivale a sum por crédito de (montotX + mora), que se guardaba en huaca_totalesmor.
     */
    private function calcularTotalIngresoCredito($dayPayments, string $date, array $pagosPreviosPorCredito): float
    {
        $totalConMora = 0;
        $byCredit = $dayPayments->groupBy('credit_id');

        foreach ($byCredit as $cid => $pays) {
            $credit = $pays->first()->credit;
            if (!$credit) continue;

            $tipoplani = (int) $credit->tipo_planilla;
            $isRefi = (bool) $credit->refinanciado;
            $fechaCan = $credit->fecha_cancelacion?->format('Y-m-d');
            $totalSinMora = (float) $pays->whereIn('tipo', ['CAPITAL', 'INTERES'])->sum('monto');
            $mora = (float) $pays->where('tipo', 'MORA')->sum('monto');

            if ($isRefi && $fechaCan === $date) {
                // RAMA REFI cancelado este día
                $interesTotal = in_array($tipoplani, [1, 4])
                    ? round(($credit->importe * $credit->interes) / 100, 2)
                    : round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;

                $montotX = (float) $credit->importe + $interesTotal;
                $pagosPrevios = $pagosPreviosPorCredito[$cid] ?? 0.0;
                if ($pagosPrevios > 0) {
                    $montotX -= $pagosPrevios;
                }
            } else {
                $montotX = $totalSinMora;
            }

            $totalConMora += $montotX + $mora;
        }

        return $totalConMora;
    }
}
