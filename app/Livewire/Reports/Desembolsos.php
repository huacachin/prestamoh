<?php

namespace App\Livewire\Reports;

use App\Services\DesembolsosService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Desembolsos del período: drill-down de los chips Nuevos/Refinanciados del
 * héroe del dashboard. Misma fuente de datos (DesembolsosService) → los
 * totales cuadran con el dashboard por construcción.
 */
class Desembolsos extends Component
{
    #[Url(as: 'mes')]
    public $month;

    #[Url(as: 'anio')]
    public $year;

    /** '' = todo el mes; '1'..'31' = solo ese día. */
    #[Url(as: 'dia')]
    public $day = '';

    /** todos | nuevos | refinanciados */
    #[Url(as: 'tipo')]
    public $tipo = 'todos';

    public function mount(): void
    {
        if (! $this->month) {
            $this->month = (int) now()->month;
        }
        if (! $this->year) {
            $this->year = (int) now()->year;
        }
        if (! in_array($this->tipo, ['todos', 'nuevos', 'refinanciados'], true)) {
            $this->tipo = 'todos';
        }
    }

    public function render()
    {
        $year = (int) $this->year;
        $month = (int) $this->month;
        $diasDelMes = Carbon::create($year, $month)->daysInMonth;
        $day = $this->day !== '' ? min((int) $this->day, $diasDelMes) : null;

        if ($day) {
            $desde = Carbon::create($year, $month, $day)->format('Y-m-d');
            $hasta = $desde;
            $etiqueta = ucfirst(Carbon::create($year, $month, $day)->locale('es')->translatedFormat('l j \d\e F \d\e Y'));
        } else {
            $desde = Carbon::create($year, $month, 1)->format('Y-m-d');
            $hasta = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
            $etiqueta = ucfirst(Carbon::create($year, $month, 1)->locale('es')->translatedFormat('F Y'));
        }

        $svc = app(DesembolsosService::class);

        return view('livewire.reports.desembolsos', [
            'etiqueta' => $etiqueta,
            'diasDelMes' => $diasDelMes,
            'anios' => range((int) now()->year, 2015),
            'resumen' => $svc->resumen($desde, $hasta),
            'creditos' => $svc->listado($desde, $hasta, $this->tipo),
        ]);
    }
}
