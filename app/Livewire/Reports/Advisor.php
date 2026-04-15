<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class Advisor extends Component
{
    public $month;
    public $year;
    public $advisorId = '';

    public function mount()
    {
        $this->month = (int) now()->month;
        $this->year  = (int) now()->year;
    }

    public function search()
    {
        // triggers re-render
    }

    public function getAdvisors(): \Illuminate\Support\Collection
    {
        return User::role('Asesor')->where('status', 1)->orderBy('name')->get();
    }

    public function render()
    {
        $advisors  = $this->getAdvisors();
        $daysInMonth = Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;

        $reportData = [];
        $totals = (object) [
            'nuevos'            => 0,
            'renovaciones'      => 0,
            'cancelaciones'     => 0,
            'total_creditos'    => 0,
            'capital'           => 0,
            'cobrados_cant'     => 0,
            'cobrados_importe'  => 0,
            'no_cobrados_cant'    => 0,
            'no_cobrados_importe' => 0,
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($this->year, $this->month, $day);
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 6=Saturday

            // Credits created on this date
            $creditsQuery = Credit::whereDate('fecha_prestamo', $dateStr);
            if ($this->advisorId !== '') {
                $creditsQuery->where('user_id', $this->advisorId);
            }
            $creditsOfDay = $creditsQuery->get();

            // New clients: credits where the client had no previous credit before this one
            $nuevos = 0;
            $renovaciones = 0;
            foreach ($creditsOfDay as $credit) {
                $previousCredits = Credit::where('client_id', $credit->client_id)
                    ->where('id', '<', $credit->id)
                    ->exists();
                if ($previousCredits) {
                    $renovaciones++;
                } else {
                    $nuevos++;
                }
            }

            // Cancellations on this date
            $cancelQuery = Credit::whereDate('fecha_cancelacion', $dateStr);
            if ($this->advisorId !== '') {
                $cancelQuery->where('user_id', $this->advisorId);
            }
            $cancelaciones = $cancelQuery->count();

            $totalCreditos = $nuevos + $renovaciones;
            $capital = $creditsOfDay->sum('importe');

            // Payments collected on this date
            $paymentsQuery = Payment::whereDate('fecha', $dateStr);
            if ($this->advisorId !== '') {
                $paymentsQuery->whereHas('credit', function ($q) {
                    $q->where('user_id', $this->advisorId);
                });
            }
            $paymentsOfDay = $paymentsQuery->get();
            $cobradosCant    = $paymentsOfDay->unique('credit_id')->count();
            $cobradosImporte = $paymentsOfDay->sum('monto');

            // Installments due on this date that were not paid
            $uncollectedQuery = CreditInstallment::whereDate('fecha_vencimiento', $dateStr)
                ->where('pagado', false);
            if ($this->advisorId !== '') {
                $uncollectedQuery->whereHas('credit', function ($q) {
                    $q->where('user_id', $this->advisorId);
                });
            }
            $uncollected = $uncollectedQuery->get();
            $noCobradosCant    = $uncollected->count();
            $noCobradosImporte = $uncollected->sum(fn ($i) => $i->importe_cuota + $i->importe_interes);

            $row = (object) [
                'num'                 => $day,
                'fecha'               => $date->format('d/m/Y'),
                'dia'                 => $date->isoFormat('ddd'),
                'day_of_week'         => $dayOfWeek,
                'nuevos'              => $nuevos,
                'renovaciones'        => $renovaciones,
                'cancelaciones'       => $cancelaciones,
                'total_creditos'      => $totalCreditos,
                'capital'             => $capital,
                'cobrados_cant'       => $cobradosCant,
                'cobrados_importe'    => $cobradosImporte,
                'no_cobrados_cant'    => $noCobradosCant,
                'no_cobrados_importe' => $noCobradosImporte,
            ];

            $reportData[] = $row;

            // Accumulate totals
            $totals->nuevos            += $nuevos;
            $totals->renovaciones      += $renovaciones;
            $totals->cancelaciones     += $cancelaciones;
            $totals->total_creditos    += $totalCreditos;
            $totals->capital           += $capital;
            $totals->cobrados_cant     += $cobradosCant;
            $totals->cobrados_importe  += $cobradosImporte;
            $totals->no_cobrados_cant    += $noCobradosCant;
            $totals->no_cobrados_importe += $noCobradosImporte;
        }

        // Averages (per day)
        $averages = (object) [
            'nuevos'              => $daysInMonth > 0 ? round($totals->nuevos / $daysInMonth, 1) : 0,
            'renovaciones'        => $daysInMonth > 0 ? round($totals->renovaciones / $daysInMonth, 1) : 0,
            'cancelaciones'       => $daysInMonth > 0 ? round($totals->cancelaciones / $daysInMonth, 1) : 0,
            'total_creditos'      => $daysInMonth > 0 ? round($totals->total_creditos / $daysInMonth, 1) : 0,
            'capital'             => $daysInMonth > 0 ? round($totals->capital / $daysInMonth, 2) : 0,
            'cobrados_cant'       => $daysInMonth > 0 ? round($totals->cobrados_cant / $daysInMonth, 1) : 0,
            'cobrados_importe'    => $daysInMonth > 0 ? round($totals->cobrados_importe / $daysInMonth, 2) : 0,
            'no_cobrados_cant'    => $daysInMonth > 0 ? round($totals->no_cobrados_cant / $daysInMonth, 1) : 0,
            'no_cobrados_importe' => $daysInMonth > 0 ? round($totals->no_cobrados_importe / $daysInMonth, 2) : 0,
        ];

        return view('livewire.reports.advisor', compact('reportData', 'advisors', 'totals', 'averages'));
    }
}
