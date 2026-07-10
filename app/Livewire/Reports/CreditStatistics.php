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

    /** Qué columnas mostrar por tasa: ambos | cap | int */
    #[Url(as: 'ver', except: 'ambos')]
    public string $viewMode = 'ambos';

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
        [$dailyRows, $dailyTotals, $dailyRates] = $this->buildDaily();

        // ─── MONTHLY TABLE (selected year) ─────────────────────────────
        [$monthlyRows, $monthlyTotals, $monthlyRates] = $this->buildMonthly();

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

    /**
     * Filtros comunes (tipo de planilla + asesor) sobre la tabla credits;
     * $prefix permite aplicarlos cuando credits entra con alias en un join.
     */
    private function applyFilters($query, string $prefix = '')
    {
        if ($this->seletipl !== '' && $this->seletipl !== '0000') {
            $query->where($prefix.'tipo_planilla', $this->seletipl);
        }
        if ($this->nomasesores !== 'Todos' && $this->nomasesores !== '') {
            $query->where($prefix.'asesor', $this->nomasesores);
        }

        return $query;
    }

    private function buildDaily(): array
    {
        $year = (int) $this->selecano;
        $month = (int) $this->selemes;
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Capital colocado: credits por fecha de desembolso + interes
        $agg = $this->applyFilters(
            DB::table('credits')
                ->whereYear('fecha_actualizacion', $year)
                ->whereMonth('fecha_actualizacion', $month)
        )
            ->selectRaw('fecha_actualizacion as fecha, interes, SUM(importe) as total_importe')
            ->groupBy('fecha_actualizacion', 'interes')
            ->get();

        // Indexar: [fecha][rate] = total_importe
        $byDate = [];
        foreach ($agg as $row) {
            $byDate[$row->fecha][(string) (float) $row->interes] = (float) $row->total_importe;
        }

        // Interés según cronograma: cuotas que VENCEN en el mes, agrupadas por
        // fecha de vencimiento + tasa del crédito. (Antes era capital × tasa
        // atribuido al día del desembolso, lo que inflaba el interés del mes:
        // en semanal/diario `interes` es el % total del crédito.)
        $aggInt = $this->applyFilters(
            DB::table('credit_installments as ci')
                ->join('credits as c', 'c.id', '=', 'ci.credit_id')
                ->whereYear('ci.fecha_vencimiento', $year)
                ->whereMonth('ci.fecha_vencimiento', $month),
            'c.'
        )
            ->selectRaw('ci.fecha_vencimiento as fecha, c.interes, SUM(ci.importe_interes) as total_interes')
            ->groupBy('ci.fecha_vencimiento', 'c.interes')
            ->get();

        $byDateInt = [];
        foreach ($aggInt as $row) {
            $byDateInt[$row->fecha][(string) (float) $row->interes] = (float) $row->total_interes;
        }

        // Tasas dinámicas: las presentes en capital o interés del mes
        $rates = $agg->pluck('interes')
            ->merge($aggInt->pluck('interes'))
            ->map(fn ($v) => (float) $v)
            ->unique()->sort()->values()->all();

        // Total importe por fecha (independiente del rate, para "Egresos Capital")
        $egresos = $this->applyFilters(
            DB::table('credits')
                ->whereYear('fecha_actualizacion', $year)
                ->whereMonth('fecha_actualizacion', $month)
        )
            ->selectRaw('fecha_actualizacion as fecha, SUM(importe) as total')
            ->groupBy('fecha_actualizacion')
            ->pluck('total', 'fecha')
            ->toArray();

        // Ingresos diarios desde cache_ingreso_diario
        $ingresos = DB::table('cache_ingreso_diario')
            ->whereYear('fecha', $year)
            ->whereMonth('fecha', $month)
            ->pluck('importe', 'fecha')
            ->toArray();

        // Nº de créditos otorgados en el mes (para las cards KPI)
        $numCreditos = $this->applyFilters(
            DB::table('credits')
                ->whereYear('fecha_actualizacion', $year)
                ->whereMonth('fecha_actualizacion', $month)
        )->count();

        $rows = [];
        $totals = [
            'ingresos' => 0,
            'egresos' => 0,
            'creditos' => $numCreditos,
            'rates_cap' => array_fill_keys(array_map(fn ($r) => (string) $r, $rates), 0),
            'rates_int' => array_fill_keys(array_map(fn ($r) => (string) $r, $rates), 0),
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
            foreach ($rates as $rate) {
                $im = (float) ($byDate[$fecha][(string) $rate] ?? 0);
                $efind = (float) ($byDateInt[$fecha][(string) $rate] ?? 0);
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

        return [$rows, $totals, $rates];
    }

    private function buildMonthly(): array
    {
        $year = (int) $this->selecano;

        // Capital colocado: credits por mes de desembolso + interes
        $agg = $this->applyFilters(
            DB::table('credits')->whereYear('fecha_actualizacion', $year)
        )
            ->selectRaw('MONTH(fecha_actualizacion) as mes, interes, SUM(importe) as total_importe')
            ->groupByRaw('MONTH(fecha_actualizacion), interes')
            ->get();

        $byMonth = [];
        foreach ($agg as $row) {
            $byMonth[(int) $row->mes][(string) (float) $row->interes] = (float) $row->total_importe;
        }

        // Interés según cronograma: cuotas que vencen en cada mes del año
        $aggInt = $this->applyFilters(
            DB::table('credit_installments as ci')
                ->join('credits as c', 'c.id', '=', 'ci.credit_id')
                ->whereYear('ci.fecha_vencimiento', $year),
            'c.'
        )
            ->selectRaw('MONTH(ci.fecha_vencimiento) as mes, c.interes, SUM(ci.importe_interes) as total_interes')
            ->groupByRaw('MONTH(ci.fecha_vencimiento), c.interes')
            ->get();

        $byMonthInt = [];
        foreach ($aggInt as $row) {
            $byMonthInt[(int) $row->mes][(string) (float) $row->interes] = (float) $row->total_interes;
        }

        // Tasas dinámicas: las presentes en capital o interés del año
        $rates = $agg->pluck('interes')
            ->merge($aggInt->pluck('interes'))
            ->map(fn ($v) => (float) $v)
            ->unique()->sort()->values()->all();

        // Egresos capital por mes
        $egresos = $this->applyFilters(
            DB::table('credits')->whereYear('fecha_actualizacion', $year)
        )
            ->selectRaw('MONTH(fecha_actualizacion) as mes, SUM(importe) as total')
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
            'rates_cap' => array_fill_keys(array_map(fn ($r) => (string) $r, $rates), 0),
            'rates_int' => array_fill_keys(array_map(fn ($r) => (string) $r, $rates), 0),
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
            foreach ($rates as $rate) {
                $im = (float) ($byMonth[$m][(string) $rate] ?? 0);
                $efind = (float) ($byMonthInt[$m][(string) $rate] ?? 0);
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

        return [$rows, $totals, $rates];
    }
}
