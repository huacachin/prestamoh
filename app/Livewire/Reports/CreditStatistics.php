<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class CreditStatistics extends Component
{
    public $month;
    public $year;
    public $filterTipo = '';
    public $filterAdvisor = '';

    public $interestRates = [3, 5, 10, 12, 15, 20];

    public function mount()
    {
        $this->month = (int) now()->month;
        $this->year = (int) now()->year;
    }

    public function generateReport(): array
    {
        $daysInMonth = Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;

        // Discover all distinct interest rates used in the period
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        $ratesQuery = Credit::whereBetween('fecha_prestamo', [$startDate, $endDate])
            ->where('situacion', '!=', 'Eliminado')
            ->when($this->filterTipo !== '', fn ($q) =>
                $q->where('tipo_planilla', $this->filterTipo)
            )
            ->when($this->filterAdvisor !== '', fn ($q) =>
                $q->where('asesor', $this->filterAdvisor)
            )
            ->selectRaw('DISTINCT CAST(interes AS DECIMAL(10,2)) as rate')
            ->orderBy('rate')
            ->pluck('rate')
            ->map(fn ($r) => (float) $r)
            ->values()
            ->toArray();

        // Merge with default rates and sort
        $allRates = collect(array_merge($this->interestRates, $ratesQuery))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->interestRates = $allRates;

        // Query all credits for the month
        $credits = Credit::whereBetween('fecha_prestamo', [$startDate, $endDate])
            ->where('situacion', '!=', 'Eliminado')
            ->when($this->filterTipo !== '', fn ($q) =>
                $q->where('tipo_planilla', $this->filterTipo)
            )
            ->when($this->filterAdvisor !== '', fn ($q) =>
                $q->where('asesor', $this->filterAdvisor)
            )
            ->selectRaw('DAY(fecha_prestamo) as dia, CAST(interes AS DECIMAL(10,2)) as rate, SUM(importe) as total_capital, SUM(interes_total) as total_interes, COUNT(*) as count')
            ->groupByRaw('DAY(fecha_prestamo), CAST(interes AS DECIMAL(10,2))')
            ->get();

        // Index by day and rate
        $indexed = [];
        foreach ($credits as $row) {
            $indexed[(int) $row->dia][(float) $row->rate] = [
                'capital' => (float) $row->total_capital,
                'interes' => (float) $row->total_interes,
                'count'   => (int) $row->count,
            ];
        }

        // Build rows
        $rows = [];
        $totals = [];
        foreach ($allRates as $rate) {
            $totals[$rate] = ['capital' => 0, 'interes' => 0, 'count' => 0];
        }
        $grandTotalCapital = 0;
        $grandTotalInteres = 0;
        $grandTotalCount = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($this->year, $this->month, $day);
            $isSunday = $date->dayOfWeek === Carbon::SUNDAY;

            $dayData = [];
            $dayTotalCapital = 0;
            $dayTotalInteres = 0;
            $dayTotalCount = 0;

            foreach ($allRates as $rate) {
                $cell = $indexed[$day][$rate] ?? ['capital' => 0, 'interes' => 0, 'count' => 0];
                $dayData[$rate] = $cell;

                $dayTotalCapital += $cell['capital'];
                $dayTotalInteres += $cell['interes'];
                $dayTotalCount += $cell['count'];

                $totals[$rate]['capital'] += $cell['capital'];
                $totals[$rate]['interes'] += $cell['interes'];
                $totals[$rate]['count'] += $cell['count'];
            }

            $grandTotalCapital += $dayTotalCapital;
            $grandTotalInteres += $dayTotalInteres;
            $grandTotalCount += $dayTotalCount;

            $rows[] = [
                'day'           => $day,
                'date'          => $date->format('d/m/Y'),
                'day_name'      => $date->locale('es')->isoFormat('ddd'),
                'is_sunday'     => $isSunday,
                'rates'         => $dayData,
                'total_capital' => $dayTotalCapital,
                'total_interes' => $dayTotalInteres,
                'total_count'   => $dayTotalCount,
            ];
        }

        return [
            'rows'               => $rows,
            'totals'             => $totals,
            'grand_total_capital' => $grandTotalCapital,
            'grand_total_interes' => $grandTotalInteres,
            'grand_total_count'   => $grandTotalCount,
        ];
    }

    public function render()
    {
        $report = $this->generateReport();
        $asesores = User::orderBy('name')->pluck('name', 'name');

        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $years = range(now()->year - 3, now()->year + 1);

        return view('livewire.reports.credit-statistics', [
            'report'       => $report,
            'asesores'     => $asesores,
            'months'       => $months,
            'years'        => $years,
            'rates'        => $this->interestRates,
        ]);
    }
}
