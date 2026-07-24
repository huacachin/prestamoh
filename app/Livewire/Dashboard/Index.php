<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Reports\Portfolio;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\DesembolsosService;
use App\Services\MorosidadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

        // ── CAPITAL PRESTADO: créditos activados en el período ─────────
        // Fuente única compartida con /reports/desembolsos (los chips del
        // héroe enlazan allá): DesembolsosService. OJO: el Egreso de Caja
        // Estadística equivale solo a los NUEVOS (los refinanciados no
        // mueven caja nueva). [Refinanciados incluidos a pedido, 24/07.]
        $prestado = app(DesembolsosService::class)->resumen($desde, $hasta);

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

        // ── CURVA DEL MES: capital prestado día a día ──────────────────
        // Siempre del MES completo (aunque el filtro esté en un día: la
        // curva da el contexto y el día filtrado se resalta en el chart).
        $mesIni = Carbon::create($year, $month, 1)->format('Y-m-d');
        $mesFin = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $porDia = Credit::query()
            ->whereBetween('fecha_actualizacion', [$mesIni, $mesFin])
            ->selectRaw("
                DAY(fecha_actualizacion) as d,
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN 0 ELSE importe END), 0) as nuevo,
                COALESCE(SUM(CASE WHEN cod_rem = 'REF' THEN importe ELSE 0 END), 0) as refi
            ")
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $serie = [];
        $serieMax = 0.0;
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $fila = $porDia->get($d);
            $nuevo = (float) ($fila->nuevo ?? 0);
            $refi = (float) ($fila->refi ?? 0);
            $serie[] = ['d' => $d, 'nuevo' => $nuevo, 'refi' => $refi, 'total' => $nuevo + $refi];
            $serieMax = max($serieMax, $nuevo + $refi);
        }

        // Años con actividad para el filtro (del primer crédito al actual)
        $minFecha = Credit::min('fecha_prestamo');
        $primerAnio = $minFecha ? (int) Carbon::parse($minFecha)->year : (int) now()->year;

        // ── CARTERA VIVA (snapshot de HOY, independiente del filtro) ───
        // Matriz: /reports/portfolio (situacion <> Cancelado). Se reusa el
        // MISMO componente para que el dashboard cuadre al céntimo con el
        // reporte de Cartera — una sola fuente de cálculo.
        $cartera = (new Portfolio)->render()->getData();

        // ── MOROSIDAD: aging (hoy) + evolución del mes ─────────────────
        // Aging: créditos en mora (venc final pasada + saldo>0, fórmula
        // exacta de Portfolio) clasificados por días desde su vencimiento.
        $hoy = Carbon::today()->format('Y-m-d');
        $aging = DB::select("
            SELECT
                CASE
                    WHEN DATEDIFF(?, t.venc) <= 7  THEN '1-7'
                    WHEN DATEDIFF(?, t.venc) <= 15 THEN '8-15'
                    WHEN DATEDIFF(?, t.venc) <= 30 THEN '16-30'
                    WHEN DATEDIFF(?, t.venc) <= 60 THEN '31-60'
                    WHEN DATEDIFF(?, t.venc) <= 90 THEN '61-90'
                    ELSE '+90'
                END AS bucket,
                COUNT(*) AS n,
                COALESCE(SUM(t.saldo), 0) AS saldo
            FROM (
                SELECT c.fecha_vencimiento AS venc,
                       (c.importe + c.importe * c.interes / 100)
                     - (SELECT COALESCE(SUM(ci.importe_aplicado + ci.interes_aplicado), 0)
                          FROM credit_installments ci WHERE ci.credit_id = c.id) AS saldo
                FROM credits c
                WHERE c.situacion NOT IN ('Eliminado', 'Cancelado')
                  AND c.fecha_vencimiento < ?
            ) t
            WHERE t.saldo > 0.005
            GROUP BY bucket
        ", [$hoy, $hoy, $hoy, $hoy, $hoy, $hoy]);
        $agingMap = collect($aging)->keyBy('bucket');
        $agingBuckets = [];
        $agingMax = 0.0;
        foreach (['1-7', '8-15', '16-30', '31-60', '61-90', '+90'] as $b) {
            $fila = $agingMap->get($b);
            $agingBuckets[] = [
                'bucket' => $b,
                'n' => (int) ($fila->n ?? 0),
                'saldo' => round((float) ($fila->saldo ?? 0), 2),
            ];
            $agingMax = max($agingMax, (float) ($fila->saldo ?? 0));
        }

        // Evolución: snapshots diarios del mes filtrado (self-healing:
        // calcula los días que falten; validado contra Portfolio).
        app(MorosidadService::class)->completarRango($mesIni, $mesFin);
        $serieMoro = DB::table('cache_morosidad_diaria')
            ->whereBetween('fecha', [$mesIni, $mesFin])
            ->orderBy('fecha')
            ->get(['fecha', 'saldo_mora', 'n_creditos_mora', 'pct'])
            ->map(fn ($r) => [
                'd' => (int) Carbon::parse($r->fecha)->day,
                'pct' => (float) $r->pct,
                'saldo' => (float) $r->saldo_mora,
                'n' => (int) $r->n_creditos_mora,
            ])
            ->all();

        return view('livewire.dashboard.index', [
            'etiqueta' => $etiqueta,
            'esDia' => (bool) $day,
            'diasDelMes' => $diasDelMes,
            'anios' => range((int) now()->year, $primerAnio),
            'capitalPrestado' => $prestado['nuevos']['total'] + $prestado['refis']['total'],
            'nCreditos' => $prestado['nuevos']['n'] + $prestado['refis']['n'],
            'nuevos' => $prestado['nuevos'],
            'refis' => $prestado['refis'],
            'serie' => $serie,
            'serieMax' => $serieMax,
            'diaFiltrado' => $day,
            'interesCobrado' => (float) $cobrado->interes,
            'nInteres' => (int) $cobrado->n_interes,
            'moraCobrada' => (float) $cobrado->mora,
            'nMora' => (int) $cobrado->n_mora,
            // Cartera (Portfolio)
            'carteraTotals' => $cartera['totals'],
            'carteraVigentes' => $cartera['vignt'],
            'carteraVencidos' => $cartera['venc'],
            'morosidad' => $cartera['morisidad'],
            'tipoTotals' => $cartera['tipoTotals'],
            'agingBuckets' => $agingBuckets,
            'agingMax' => $agingMax,
            'serieMoro' => $serieMoro,
        ]);
    }
}
