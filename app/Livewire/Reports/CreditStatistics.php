<?php

namespace App\Livewire\Reports;

use App\Services\CajaDailyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class CreditStatistics extends Component
{
    #[Url(as: 'mes')]
    public $selemes;

    #[Url(as: 'anio')]
    public $selecano;

    #[Url(as: 'tipo', except: '')]
    public $seletipl = '';

    #[Url(as: 'asesor', except: 'Todos')]
    public $nomasesores = 'Todos';

    public array $rates = [0.01, 3, 4, 5, 5.2, 6, 6.5, 7, 8, 10, 12, 15, 16, 20, 24, 36];

    public function mount()
    {
        if (! request()->has('mes')) {
            $this->selemes = date('m');
        }
        if (! request()->has('anio')) {
            $this->selecano = date('Y');
        }
    }

    public function search() {}

    /**
     * Recomputa cache_ingreso_diario (legacy huaca_totalesmor) del mes desde datos
     * vivos: por día = suma de (total + mora) de los ingresos de caja, idéntico a
     * lo que reporte1a escribe en totalesmor. Self-healing, sin cron.
     */
    private function rebuildIngresoDiario(int $year, int $month, string $endLimit): void
    {
        $ingresosPorDia = app(CajaDailyService::class)->ingresosPorDia($year, $month, null, $endLimit);

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $endLimit) {
                break;
            }

            $importe = 0.0;
            foreach ($ingresosPorDia[$date] ?? [] as $i) {
                $importe += $i['total'] + $i['mora'];
            }

            DB::table('cache_ingreso_diario')->updateOrInsert(
                ['fecha' => $date],
                ['importe' => round($importe, 2), 'updated_at' => now()]
            );
        }
    }

    public function render()
    {
        // Self-healing: "Ingresos Créditos" sale de cache_ingreso_diario (legacy:
        // huaca_totalesmor, que escribe reporte1a = sub_ingresos + mora por día).
        // Laravel no la mantenía, así que la recomputamos del mes visto con datos
        // vivos (servicio compartido) para que cuadre con el legacy balancedia. Sin cron.
        $year = (int) $this->selecano;
        $month = (int) $this->selemes;
        $endMonth = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');
        $this->rebuildIngresoDiario($year, $month, min($endMonth, $today));

        // ─── DAILY TABLE (selected month) ──────────────────────────────
        [$dailyRows, $dailyTotals] = $this->buildDaily();

        // ─── MONTHLY TABLE (selected year) ─────────────────────────────
        [$monthlyRows, $monthlyTotals] = $this->buildMonthly();

        // Ocultar tasas sin movimiento en el periodo (int se deriva del cap,
        // así que basta mirar el capital acumulado por tasa).
        $dailyRates = array_values(array_filter($this->rates,
            fn ($r) => $dailyTotals['rates_cap'][(string) $r] != 0));
        $monthlyRates = array_values(array_filter($this->rates,
            fn ($r) => $monthlyTotals['rates_cap'][(string) $r] != 0));

        $months = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        $asesores = DB::table('users')->orderBy('name')->pluck('name', 'username');

        return view('livewire.reports.credit-statistics', [
            'dailyRows' => $dailyRows,
            'dailyTotals' => $dailyTotals,
            'monthlyRows' => $monthlyRows,
            'monthlyTotals' => $monthlyTotals,
            'dailyRates' => $dailyRates,
            'monthlyRates' => $monthlyRates,
            'months' => $months,
            'asesores' => $asesores,
        ]);
    }

    private function buildDaily(): array
    {
        $year = (int) $this->selecano;
        $month = (int) $this->selemes;
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Pre-cargar agrupacion de credits por fecha + interes
        $query = DB::table('credits')
            ->whereYear('fecha_actualizacion', $year)
            ->whereMonth('fecha_actualizacion', $month);

        if ($this->seletipl !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }
        if ($this->nomasesores !== 'Todos' && $this->nomasesores !== '') {
            $query->where('asesor', $this->nomasesores);
        }

        $agg = $query
            ->selectRaw('fecha_actualizacion as fecha, interes, SUM(importe) as total_importe')
            ->groupBy('fecha_actualizacion', 'interes')
            ->get();

        // Indexar: [fecha][rate] = total_importe
        $byDate = [];
        foreach ($agg as $row) {
            $byDate[$row->fecha][(string) (float) $row->interes] = (float) $row->total_importe;
        }

        // Total importe por fecha (independiente del rate, para "Egresos Capital")
        $egrQuery = DB::table('credits')
            ->whereYear('fecha_actualizacion', $year)
            ->whereMonth('fecha_actualizacion', $month);
        if ($this->seletipl !== '' && $this->seletipl !== '0000') {
            $egrQuery->where('tipo_planilla', $this->seletipl);
        }
        if ($this->nomasesores !== 'Todos' && $this->nomasesores !== '') {
            $egrQuery->where('asesor', $this->nomasesores);
        }
        $egresos = $egrQuery->selectRaw('fecha_actualizacion as fecha, SUM(importe) as total')
            ->groupBy('fecha_actualizacion')
            ->pluck('total', 'fecha')
            ->toArray();

        // Ingresos diarios desde cache_ingreso_diario
        $ingresos = DB::table('cache_ingreso_diario')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->pluck('importe', 'fecha')
            ->toArray();

        $rows = [];
        $totals = [
            'ingresos' => 0,
            'egresos' => 0,
            'rates_cap' => array_fill_keys(array_map(fn ($r) => (string) $r, $this->rates), 0),
            'rates_int' => array_fill_keys(array_map(fn ($r) => (string) $r, $this->rates), 0),
            'total_inter' => 0,
        ];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $fecha = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isSunday = Carbon::parse($fecha)->dayOfWeek === Carbon::SUNDAY;

            $row = [
                'fecha' => $fecha,
                'ingresos' => (float) ($ingresos[$fecha] ?? 0),
                'egresos' => (float) ($egresos[$fecha] ?? 0),
                'is_sunday' => $isSunday,
                'rates' => [],
                'total_int' => 0,
            ];

            $intetotalX = 0;
            foreach ($this->rates as $rate) {
                $im = (float) ($byDate[$fecha][(string) (float) $rate] ?? 0);
                $efind = round(($im * $rate) / 100, 2);
                $row['rates'][(string) $rate] = ['cap' => $im, 'int' => $efind];

                $totals['rates_cap'][(string) $rate] += $im;
                $totals['rates_int'][(string) $rate] += $efind;
                $intetotalX += $efind;
            }
            $row['total_int'] = $intetotalX;

            $totals['ingresos'] += $row['ingresos'];
            $totals['egresos'] += $row['egresos'];
            $totals['total_inter'] += $intetotalX;

            $rows[] = $row;
        }

        return [$rows, $totals];
    }

    private function buildMonthly(): array
    {
        $year = (int) $this->selecano;

        // Aggregate credits by month + interes (for the year)
        $query = DB::table('credits')
            ->whereYear('fecha_actualizacion', $year);
        if ($this->seletipl !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }
        if ($this->nomasesores !== 'Todos' && $this->nomasesores !== '') {
            $query->where('asesor', $this->nomasesores);
        }

        $agg = $query
            ->selectRaw('MONTH(fecha_actualizacion) as mes, interes, SUM(importe) as total_importe')
            ->groupByRaw('MONTH(fecha_actualizacion), interes')
            ->get();

        $byMonth = [];
        foreach ($agg as $row) {
            $byMonth[(int) $row->mes][(string) (float) $row->interes] = (float) $row->total_importe;
        }

        // Egresos capital por mes
        $egrQuery = DB::table('credits')->whereYear('fecha_actualizacion', $year);
        if ($this->seletipl !== '' && $this->seletipl !== '0000') {
            $egrQuery->where('tipo_planilla', $this->seletipl);
        }
        if ($this->nomasesores !== 'Todos' && $this->nomasesores !== '') {
            $egrQuery->where('asesor', $this->nomasesores);
        }
        $egresos = $egrQuery->selectRaw('MONTH(fecha_actualizacion) as mes, SUM(importe) as total')
            ->groupByRaw('MONTH(fecha_actualizacion)')
            ->pluck('total', 'mes')
            ->toArray();

        // Ingresos del año por mes
        $ingresos = DB::table('cache_ingreso_diario')
            ->whereYear('fecha', $year)
            ->selectRaw('MONTH(fecha) as mes, SUM(importe) as total')
            ->groupByRaw('MONTH(fecha)')
            ->pluck('total', 'mes')
            ->toArray();

        $monthLabels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $rows = [];
        $totals = [
            'ingresos' => 0,
            'egresos' => 0,
            'rates_cap' => array_fill_keys(array_map(fn ($r) => (string) $r, $this->rates), 0),
            'rates_int' => array_fill_keys(array_map(fn ($r) => (string) $r, $this->rates), 0),
            'total_inter' => 0,
        ];

        for ($m = 1; $m <= 12; $m++) {
            $row = [
                'mes_label' => $monthLabels[$m - 1],
                'ingresos' => (float) ($ingresos[$m] ?? 0),
                'egresos' => (float) ($egresos[$m] ?? 0),
                'rates' => [],
                'total_int' => 0,
            ];

            $intetotalX = 0;
            foreach ($this->rates as $rate) {
                $im = (float) ($byMonth[$m][(string) (float) $rate] ?? 0);
                // Legacy mensual usa SIN round (a diferencia del diario que sí redondea)
                $efind = ($im * $rate) / 100;
                $row['rates'][(string) $rate] = ['cap' => $im, 'int' => $efind];

                $totals['rates_cap'][(string) $rate] += $im;
                $totals['rates_int'][(string) $rate] += $efind;
                $intetotalX += $efind;
            }
            $row['total_int'] = $intetotalX;

            $totals['ingresos'] += $row['ingresos'];
            $totals['egresos'] += $row['egresos'];
            $totals['total_inter'] += $intetotalX;

            $rows[] = $row;
        }

        return [$rows, $totals];
    }
}
