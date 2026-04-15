<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class CashGeneral1 extends Component
{
    public $month;
    public $year;
    public $filterTipo = '';

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
        $days = [];
        $grandTotalIngresos = 0;
        $grandTotalCapital  = 0;
        $grandTotalInteres  = 0;
        $grandTotalMora     = 0;
        $grandTotalEgresos  = 0;
        $grandTotalEgresosInteres = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');

            // Stop if date is in the future
            if ($date > Carbon::today()->format('Y-m-d')) {
                break;
            }

            // INGRESOS: Payments received grouped by credit
            $paymentsQuery = Payment::query()
                ->where('fecha', $date)
                ->with(['credit.client']);

            if ($this->filterTipo !== '' && $this->filterTipo !== '0000') {
                $paymentsQuery->whereHas('credit', function ($q) {
                    $q->where('tipo_planilla', $this->filterTipo);
                });
            }

            $payments = $paymentsQuery->get();

            $ingresos = [];
            $paymentsByCredit = $payments->groupBy('credit_id');

            foreach ($paymentsByCredit as $creditId => $creditPayments) {
                $credit = $creditPayments->first()->credit;
                if (!$credit) continue;

                $capital  = $creditPayments->where('tipo', 'CAPITAL')->sum('monto');
                $interes  = $creditPayments->where('tipo', 'INTERES')->sum('monto');
                $mora     = $creditPayments->where('tipo', 'MORA')->sum('monto');
                $total    = $capital + $interes + $mora;

                $clientName = $credit->client ? $credit->client->fullName() : 'N/A';
                $nroCuotas  = $creditPayments->pluck('installment_id')->unique()->count();

                $ingresos[] = [
                    'credit_id'   => $creditId,
                    'cliente'     => $clientName,
                    'detalle'     => $credit->tipoPlanillaLabel(),
                    'nro_cuotas'  => $nroCuotas,
                    'total'       => $total,
                    'capital'     => $capital,
                    'interes'     => $interes,
                    'mora'        => $mora,
                    'asesor'      => $credit->asesor ?? '',
                    'tipo_planilla' => $credit->tipo_planilla,
                ];
            }

            // EGRESOS: Credits disbursed on this date
            $creditsQuery = Credit::query()
                ->where('fecha_prestamo', $date)
                ->with('client');

            if ($this->filterTipo !== '' && $this->filterTipo !== '0000') {
                $creditsQuery->where('tipo_planilla', $this->filterTipo);
            }

            $credits = $creditsQuery->get();

            $egresos = [];
            foreach ($credits as $credit) {
                $clientName = $credit->client ? $credit->client->fullName() : 'N/A';
                $interesCalc = 0;
                if (in_array((int) $credit->tipo_planilla, [1, 4])) {
                    $interesCalc = round(($credit->importe * $credit->interes) / 100, 2);
                } else {
                    $interesCalc = round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;
                }

                $egresos[] = [
                    'credit_id'     => $credit->id,
                    'cliente'       => $clientName,
                    'monto'         => $credit->importe,
                    'interes_pct'   => $credit->interes,
                    'interes_monto' => $interesCalc,
                    'asesor'        => $credit->asesor ?? '',
                    'tipo_planilla' => $credit->tipo_planilla,
                ];
            }

            $dayTotalIngresos = collect($ingresos)->sum('total');
            $dayTotalCapital  = collect($ingresos)->sum('capital');
            $dayTotalInteres  = collect($ingresos)->sum('interes');
            $dayTotalMora     = collect($ingresos)->sum('mora');
            $dayTotalEgresos  = collect($egresos)->sum('monto');
            $dayTotalEgresosInteres = collect($egresos)->sum('interes_monto');

            if (count($ingresos) > 0 || count($egresos) > 0) {
                $days[] = [
                    'date'       => $date,
                    'date_label' => Carbon::parse($date)->translatedFormat('l d \d\e F Y'),
                    'ingresos'   => $ingresos,
                    'egresos'    => $egresos,
                    'subtotal_ingresos' => $dayTotalIngresos,
                    'subtotal_capital'  => $dayTotalCapital,
                    'subtotal_interes'  => $dayTotalInteres,
                    'subtotal_mora'     => $dayTotalMora,
                    'subtotal_egresos'  => $dayTotalEgresos,
                    'subtotal_egresos_interes' => $dayTotalEgresosInteres,
                ];

                $grandTotalIngresos += $dayTotalIngresos;
                $grandTotalCapital  += $dayTotalCapital;
                $grandTotalInteres  += $dayTotalInteres;
                $grandTotalMora     += $dayTotalMora;
                $grandTotalEgresos  += $dayTotalEgresos;
                $grandTotalEgresosInteres += $dayTotalEgresosInteres;
            }
        }

        return [
            'days' => $days,
            'grand_total_ingresos' => $grandTotalIngresos,
            'grand_total_capital'  => $grandTotalCapital,
            'grand_total_interes'  => $grandTotalInteres,
            'grand_total_mora'     => $grandTotalMora,
            'grand_total_egresos'  => $grandTotalEgresos,
            'grand_total_egresos_interes' => $grandTotalEgresosInteres,
        ];
    }

    public function render()
    {
        $report = $this->generateReport();

        return view('livewire.reports.cash-general-1', [
            'report' => $report,
        ]);
    }
}
