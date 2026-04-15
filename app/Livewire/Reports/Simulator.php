<?php

namespace App\Livewire\Reports;

use Livewire\Component;

class Simulator extends Component
{
    public $capital = '';
    public $interes_mensual = '';
    public $cuotas = '';
    public $tipo_planilla = '3'; // 1=semanal, 3=mensual, 4=diario

    public $cronograma = [];
    public $resumen = null;

    public function rules()
    {
        return [
            'capital'         => 'required|numeric|min:1',
            'interes_mensual' => 'required|numeric|min:0',
            'cuotas'          => 'required|integer|min:1|max:120',
            'tipo_planilla'   => 'required|in:1,3,4',
        ];
    }

    public function simulate()
    {
        $this->validate();

        $capital = (float) $this->capital;
        $interes = (float) $this->interes_mensual;
        $nroCuotas = (int) $this->cuotas;

        $cuotaCapital = round($capital / $nroCuotas, 2);
        $cuotaInteres = round(($capital * $interes) / 100, 2);
        $cuotaTotal = $cuotaCapital + $cuotaInteres;
        $moraDiaria = round(($cuotaTotal * $interes / 100) / 30 * 2, 2);

        $cronograma = [];
        $saldoCapital = $capital;

        for ($i = 1; $i <= $nroCuotas; $i++) {
            // Adjust last installment for rounding
            if ($i === $nroCuotas) {
                $cuotaCapital = round($saldoCapital, 2);
            }

            $saldoCapital = round($saldoCapital - $cuotaCapital, 2);

            $cronograma[] = (object) [
                'num'            => $i,
                'cuota_capital'  => $cuotaCapital,
                'cuota_interes'  => $cuotaInteres,
                'cuota_total'    => $cuotaCapital + $cuotaInteres,
                'mora_diaria'    => $moraDiaria,
                'saldo_capital'  => max($saldoCapital, 0),
            ];
        }

        $this->cronograma = $cronograma;

        $this->resumen = (object) [
            'capital'        => $capital,
            'interes_total'  => $cuotaInteres * $nroCuotas,
            'total_pagar'    => $capital + ($cuotaInteres * $nroCuotas),
            'cuota_capital'  => round($capital / $nroCuotas, 2),
            'cuota_interes'  => $cuotaInteres,
            'mora_diaria'    => $moraDiaria,
            'tipo_label'     => match ((int) $this->tipo_planilla) {
                1 => 'Semanal', 3 => 'Mensual', 4 => 'Diario', default => 'Otro',
            },
        ];
    }

    public function render()
    {
        return view('livewire.reports.simulator');
    }
}
