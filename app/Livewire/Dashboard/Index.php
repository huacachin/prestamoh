<?php

namespace App\Livewire\Dashboard;

use App\Models\Credit;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard principal — el "corazón" del sistema.
 *
 * Métricas del período filtrado (mes completo o un día puntual):
 *  - CAPITAL PRESTADO: créditos ACTIVADOS en el período (fecha_actualizacion,
 *    excluye refinanciados cod_rem='REF') — la plata que realmente salió de
 *    caja, misma definición que el Egreso de Caja Estadística, así el
 *    dashboard cuadra con los reportes de caja. [Definición confirmada por
 *    Antony el 24/07/2026: julio = S/ 367,312.60 en 28 créditos.]
 *  - INTERÉS COBRADO: payments tipo INTERES del período (verdad de caja).
 *  - MORA COBRADA: payments tipo MORA (incluye MORA, MORA INTERES, MORA
 *    CAPITAL y MORA ACUM.).
 */
class Index extends Component
{
    #[Url(as: 'mes')]
    public $month;

    #[Url(as: 'anio')]
    public $year;

    /** '' = todo el mes; '1'..'31' = solo ese día. */
    #[Url(as: 'dia')]
    public $day = '';

    public function mount(): void
    {
        if (! $this->month) {
            $this->month = (int) now()->month;
        }
        if (! $this->year) {
            $this->year = (int) now()->year;
        }
    }

    public function render()
    {
        $year = (int) $this->year;
        $month = (int) $this->month;
        $diasDelMes = Carbon::create($year, $month)->daysInMonth;

        // Clamp del día al cambiar de mes (p. ej. día 31 → febrero)
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

        // ── CAPITAL PRESTADO: créditos activados, sin refinanciados ────
        $prestado = Credit::query()
            ->whereBetween('fecha_actualizacion', [$desde, $hasta])
            ->where(function ($q) {
                $q->where('cod_rem', '<>', 'REF')->orWhereNull('cod_rem');
            })
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(importe), 0) as total')
            ->first();

        // ── INTERÉS y MORA cobrados en el período (verdad de caja) ─────
        $cobrado = Payment::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('tipo', ['INTERES', 'MORA'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipo = 'INTERES' THEN monto ELSE 0 END), 0) as interes,
                COALESCE(SUM(CASE WHEN tipo = 'INTERES' THEN 1 ELSE 0 END), 0) as n_interes,
                COALESCE(SUM(CASE WHEN tipo = 'MORA' THEN monto ELSE 0 END), 0) as mora,
                COALESCE(SUM(CASE WHEN tipo = 'MORA' THEN 1 ELSE 0 END), 0) as n_mora
            ")
            ->first();

        // Años con actividad para el filtro (del primer crédito al actual)
        $minFecha = Credit::min('fecha_prestamo');
        $primerAnio = $minFecha ? (int) Carbon::parse($minFecha)->year : (int) now()->year;

        return view('livewire.dashboard.index', [
            'etiqueta' => $etiqueta,
            'esDia' => (bool) $day,
            'diasDelMes' => $diasDelMes,
            'anios' => range((int) now()->year, $primerAnio),
            'capitalPrestado' => (float) $prestado->total,
            'nCreditos' => (int) $prestado->n,
            'interesCobrado' => (float) $cobrado->interes,
            'nInteres' => (int) $cobrado->n_interes,
            'moraCobrada' => (float) $cobrado->mora,
            'nMora' => (int) $cobrado->n_mora,
        ]);
    }
}
