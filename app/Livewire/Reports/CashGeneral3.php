<?php

namespace App\Livewire\Reports;

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

    public function search()
    {
        // triggers re-render
    }

    public function generateReport(): array
    {
        $daysInMonth = Carbon::create($this->year, $this->month)->daysInMonth;
        $days = [];
        $totalInteresGeneral = 0;
        $totalMoraGeneral    = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');

            if ($date > Carbon::today()->format('Y-m-d')) {
                break;
            }

            $interesDay = Payment::where('fecha', $date)->where('tipo', 'INTERES')->sum('monto');
            $moraDay    = Payment::where('fecha', $date)->where('tipo', 'MORA')->sum('monto');

            $dayRows = [];
            $dayTotal = 0;

            if ($interesDay > 0) {
                $dayRows[] = [
                    'cliente' => 'CLIENTE',
                    'detalle' => 'INTERES',
                    'ingreso' => (float) $interesDay,
                    'egreso'  => 0,
                ];
                $dayTotal += (float) $interesDay;
                $totalInteresGeneral += (float) $interesDay;
            }

            if ($moraDay > 0) {
                $dayRows[] = [
                    'cliente' => 'CLIENTE',
                    'detalle' => 'MORA',
                    'ingreso' => (float) $moraDay,
                    'egreso'  => 0,
                ];
                $dayTotal += (float) $moraDay;
                $totalMoraGeneral += (float) $moraDay;
            }

            if (count($dayRows) > 0) {
                $days[] = [
                    'date'       => $date,
                    'date_label' => Carbon::parse($date)->translatedFormat('l d \d\e F Y'),
                    'rows'       => $dayRows,
                    'total'      => $dayTotal,
                ];
            }
        }

        // Monthly summary by advisor
        $startDate = Carbon::create($this->year, $this->month, 1)->format('Y-m-d');
        $endDate   = Carbon::create($this->year, $this->month, $daysInMonth)->format('Y-m-d');

        $advisorSummary = Payment::query()
            ->whereIn('tipo', ['INTERES', 'MORA'])
            ->whereBetween('fecha', [$startDate, $endDate])
            ->selectRaw('asesor, tipo, SUM(monto) as total')
            ->groupBy('asesor', 'tipo')
            ->get();

        $byAdvisor = [];
        foreach ($advisorSummary as $row) {
            $name = $row->asesor ?: 'Sin Asesor';
            if (!isset($byAdvisor[$name])) {
                $byAdvisor[$name] = ['interes' => 0, 'mora' => 0, 'total' => 0];
            }
            if ($row->tipo === 'INTERES') {
                $byAdvisor[$name]['interes'] += (float) $row->total;
            } else {
                $byAdvisor[$name]['mora'] += (float) $row->total;
            }
            $byAdvisor[$name]['total'] = $byAdvisor[$name]['interes'] + $byAdvisor[$name]['mora'];
        }

        $totalGeneral = $totalInteresGeneral + $totalMoraGeneral;

        return [
            'days'              => $days,
            'total_interes'     => $totalInteresGeneral,
            'total_mora'        => $totalMoraGeneral,
            'total_general'     => $totalGeneral,
            'by_advisor'        => $byAdvisor,
        ];
    }

    public function render()
    {
        $report = $this->generateReport();

        return view('livewire.reports.cash-general-3', [
            'report' => $report,
        ]);
    }
}
