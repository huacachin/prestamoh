<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CashStatistics extends Component
{
    public int $month;
    public int $year;

    public function mount(): void
    {
        $this->month = (int) now()->month;
        $this->year  = (int) now()->year;
    }

    public function search(): void
    {
        // triggers re-render
    }

    public function render()
    {
        $data = $this->generateReport();

        return view('livewire.reports.cash-statistics', $data);
    }

    private function generateReport(): array
    {
        $daysInMonth = Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;
        $rows = [];

        // Accumulators for totals
        $totals = [
            'capital'            => 0,
            'capital_cobrado'    => 0,
            'mensual_n'          => 0,
            'mensual_interes'    => 0,
            'mensual_mora'       => 0,
            'semanal_n'          => 0,
            'semanal_interes'    => 0,
            'semanal_mora'       => 0,
            'diario_n'           => 0,
            'diario_interes'     => 0,
            'diario_mora'        => 0,
            'total_credito'      => 0,
            'otros_ingreso'      => 0,
            'otros_egreso'       => 0,
            'utilidad_caja'      => 0,
            'ingreso_fijos'      => 0,
            'ingreso_otros'      => 0,
            'ingreso_total'      => 0,
            'egreso_fijos'       => 0,
            'egreso_otros'       => 0,
            'egreso_total'       => 0,
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($this->year, $this->month, $day)->toDateString();
            $dayOfWeek = Carbon::parse($date)->dayOfWeek; // 0 = Sunday

            // Capital disbursed (new credits on this date)
            $capital = (float) Credit::whereDate('fecha_prestamo', $date)
                ->where('situacion', '!=', 'Eliminado')
                ->sum('importe');

            // Capital payments collected
            $capitalCobrado = (float) Payment::whereDate('fecha', $date)
                ->where('tipo', 'CAPITAL')
                ->sum('monto');

            // Interest payments by credit type (tipoplani)
            $interestByType = Payment::whereDate('payments.fecha', $date)
                ->where('payments.tipo', 'INTERES')
                ->join('credits', 'payments.credit_id', '=', 'credits.id')
                ->selectRaw("credits.tipo_planilla, count(distinct credits.id) as cnt, sum(payments.monto) as total")
                ->groupBy('credits.tipo_planilla')
                ->get()
                ->keyBy('tipo_planilla');

            // Mora payments by credit type
            $moraByType = Payment::whereDate('payments.fecha', $date)
                ->where('payments.tipo', 'MORA')
                ->join('credits', 'payments.credit_id', '=', 'credits.id')
                ->selectRaw("credits.tipo_planilla, sum(payments.monto) as total")
                ->groupBy('credits.tipo_planilla')
                ->get()
                ->keyBy('tipo_planilla');

            $mensualN       = (int) ($interestByType[3]->cnt ?? 0);
            $mensualInteres = (float) ($interestByType[3]->total ?? 0);
            $mensualMora    = (float) ($moraByType[3]->total ?? 0);

            $semanalN       = (int) ($interestByType[1]->cnt ?? 0);
            $semanalInteres = (float) ($interestByType[1]->total ?? 0);
            $semanalMora    = (float) ($moraByType[1]->total ?? 0);

            $diarioN       = (int) ($interestByType[4]->cnt ?? 0);
            $diarioInteres = (float) ($interestByType[4]->total ?? 0);
            $diarioMora    = (float) ($moraByType[4]->total ?? 0);

            $totalCredito = $mensualInteres + $mensualMora
                + $semanalInteres + $semanalMora
                + $diarioInteres + $diarioMora;

            // Other income (non-credit)
            $otrosIngreso = (float) Income::whereDate('date', $date)->sum('total');

            // Other expenses (non-credit)
            $otrosEgreso = (float) Expense::whereDate('date', $date)->sum('total');

            $utilidadCaja = $totalCredito + $otrosIngreso - $otrosEgreso;

            // Income breakdown: Fijos vs Otros (by reason field)
            $ingresoFijos = (float) Income::whereDate('date', $date)
                ->where('reason', 'like', '%fijo%')
                ->sum('total');
            $ingresoOtros = (float) Income::whereDate('date', $date)
                ->where('reason', 'not like', '%fijo%')
                ->sum('total');

            // Expense breakdown: Fijos vs Otros
            $egresoFijos = (float) Expense::whereDate('date', $date)
                ->where('reason', 'like', '%fijo%')
                ->sum('total');
            $egresoOtros = (float) Expense::whereDate('date', $date)
                ->where('reason', 'not like', '%fijo%')
                ->sum('total');

            $row = [
                'date'              => $date,
                'day'               => $day,
                'is_sunday'         => $dayOfWeek === 0,
                'capital'           => $capital,
                'capital_cobrado'   => $capitalCobrado,
                'mensual_n'         => $mensualN,
                'mensual_interes'   => $mensualInteres,
                'mensual_mora'      => $mensualMora,
                'semanal_n'         => $semanalN,
                'semanal_interes'   => $semanalInteres,
                'semanal_mora'      => $semanalMora,
                'diario_n'          => $diarioN,
                'diario_interes'    => $diarioInteres,
                'diario_mora'       => $diarioMora,
                'total_credito'     => $totalCredito,
                'otros_ingreso'     => $otrosIngreso,
                'otros_egreso'      => $otrosEgreso,
                'utilidad_caja'     => $utilidadCaja,
                'ingreso_fijos'     => $ingresoFijos,
                'ingreso_otros'     => $ingresoOtros,
                'ingreso_total'     => $ingresoFijos + $ingresoOtros,
                'egreso_fijos'      => $egresoFijos,
                'egreso_otros'      => $egresoOtros,
                'egreso_total'      => $egresoFijos + $egresoOtros,
            ];

            $rows[] = $row;

            // Accumulate totals
            foreach ($totals as $key => &$val) {
                $val += $row[$key] ?? 0;
            }
        }

        // Distribution calculations
        // Based on legacy: income total from interest (mensual+semanal) and diario
        $totalInteresMS = $totals['mensual_interes'] + $totals['mensual_mora']
            + $totals['semanal_interes'] + $totals['semanal_mora'];
        $totalInteresD  = $totals['diario_interes'] + $totals['diario_mora'];

        // 50% / 3 = 16.67% each for egreso, sueldo, provisiones; 50% utilidad
        $msHalf = $totalInteresMS > 0 ? $totalInteresMS / 2 : 0;
        $msThird = $msHalf > 0 ? $msHalf / 3 : 0;

        $dHalf = $totalInteresD > 0 ? $totalInteresD / 2 : 0;
        $dThird = $dHalf > 0 ? $dHalf / 3 : 0;

        $distribution = [
            ['label' => 'Egreso',      'pct' => '16.67%', 'ms' => $msThird,   'd' => $dThird,   'total' => $msThird + $dThird],
            ['label' => 'Sueldo',      'pct' => '16.67%', 'ms' => $msThird,   'd' => $dThird,   'total' => $msThird + $dThird],
            ['label' => 'Provisiones', 'pct' => '16.67%', 'ms' => $msThird,   'd' => $dThird,   'total' => $msThird + $dThird],
            ['label' => 'Utilidad',    'pct' => '50.00%', 'ms' => $msHalf,    'd' => $dHalf,    'total' => $msHalf + $dHalf],
            ['label' => 'Total',       'pct' => '100.00%','ms' => $totalInteresMS, 'd' => $totalInteresD, 'total' => $totalInteresMS + $totalInteresD],
        ];

        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return compact('rows', 'totals', 'distribution', 'months');
    }
}
