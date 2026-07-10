<?php

namespace App\Livewire\Reports;

use Livewire\Component;

class Simulator extends Component
{
    public string $nombre = '';

    public $capital = '';

    public $interes = '';

    public bool $hasResult = false;

    public function rules(): array
    {
        return [
            'nombre' => 'nullable|string|max:150',
            'capital' => 'required|numeric|min:1',
            'interes' => 'required|numeric|min:0',
        ];
    }

    public function simulate(): void
    {
        $this->validate();
        $this->hasResult = true;
    }

    public function render()
    {
        $capital = (float) ($this->capital ?: 0);
        $interes = (float) ($this->interes ?: 0);

        $mensual = [];
        $semanal = [];

        if ($this->hasResult && $capital > 0) {
            for ($i = 1; $i <= 60; $i++) {
                // Mensual: capital/i + interés fijo. La cuota real sale con el
                // esquema de cuota uniforme: redondeada hacia arriba a 0.10
                // (el delta se cobra como excedente).
                $capi2Mens = ($capital / $i) + (($capital * $interes) / 100);
                $cuotaMens = ceil(round($capi2Mens / 0.10, 6)) * 0.10;
                $moraMens = ($cuotaMens * $interes / 100) / 30 * 2;

                $mensual[$i] = [
                    'pagar' => round($cuotaMens, 2),
                    'mora' => round($moraMens, 2),
                ];

                // Semanal: capital/(i*4) + interés/4, misma cuota uniforme
                $cuomes = $i * 4;
                $capi2Sem = ($capital / $cuomes) + (($capital * $interes) / 100 / 4);
                $cuotaSem = ceil(round($capi2Sem / 0.10, 6)) * 0.10;
                $moraSem = ($cuotaSem * $interes / 100) / 30 * 2;

                $semanal[$i] = [
                    'pagar' => round($cuotaSem, 2),
                    'mora' => round($moraSem, 2),
                ];
            }
        }

        return view('livewire.reports.simulator', [
            'mensual' => $mensual,
            'semanal' => $semanal,
            'bloques' => [
                [1, 12],
                [13, 24],
                [25, 36],
                [37, 48],
                [49, 60],
            ],
        ]);
    }
}
