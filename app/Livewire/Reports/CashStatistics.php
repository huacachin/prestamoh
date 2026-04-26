<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CashStatistics extends Component
{
    public $month;
    public $year;

    public function mount(): void
    {
        $this->month = (int) now()->month;
        $this->year  = (int) now()->year;
    }

    public function search(): void {}

    public function render()
    {
        $year  = (int) $this->year;
        $month = (int) $this->month;

        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
        $endMonth   = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today      = Carbon::today()->format('Y-m-d');
        $endLimit   = min($endMonth, $today);

        // ─── PRECARGAR DATOS DEL MES (cacheados, igual al legacy) ──────
        // Capital T. (legacy: huaca_capineto)
        $capitalNetoByDate = DB::table('capital_neto')
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->pluck('importe', 'fecha')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->format('Y-m-d') => (float) $v]);

        // Capital cobrado (legacy: huaca_totcaj1a)
        $capitalCobradoByDate = DB::table('cache_capital_cobrado')
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->pluck('importe', 'fecha')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->format('Y-m-d') => (float) $v]);

        // Totales por tipo (legacy: huaca_totcaj3)
        $totcaj3ByDate = DB::table('cache_credit_totals')
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->fecha)->format('Y-m-d'));

        // Mora por tipo (calculado de payments)
        $moraByTypeByDate = Payment::query()
            ->join('credits', 'payments.credit_id', '=', 'credits.id')
            ->where('payments.fecha', '>=', $startMonth)
            ->where('payments.fecha', '<=', $endLimit)
            ->where('payments.tipo', 'MORA')
            ->selectRaw('DATE(payments.fecha) as f, credits.tipo_planilla, SUM(payments.monto) as total')
            ->groupBy('f', 'credits.tipo_planilla')
            ->get()
            ->groupBy('f');

        // Incomes Caja 1 por día y modo
        $incomes1 = Income::query()
            ->where('caja', 1)
            ->whereIn('modo', ['Fijos', 'Otros'])
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->selectRaw('DATE(date) as f, modo, SUM(total) as total')
            ->groupBy('f', 'modo')
            ->get()
            ->groupBy('f');

        // Expenses Caja 1 por día y modo
        $expenses1 = Expense::query()
            ->where('caja', 1)
            ->whereIn('modo', ['Fijos', 'Otros'])
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->selectRaw('DATE(date) as f, modo, SUM(total) as total')
            ->groupBy('f', 'modo')
            ->get()
            ->groupBy('f');

        // Incomes Caja 3 por día (legacy: huaca_ingreso3)
        $incomes3 = Income::query()
            ->where('caja', 3)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->selectRaw('DATE(date) as f, SUM(total) as total')
            ->groupBy('f')
            ->pluck('total', 'f');

        // Expenses Caja 3 por día (legacy: huaca_entrada3)
        $expenses3 = Expense::query()
            ->where('caja', 3)
            ->where('date', '>=', $startMonth)
            ->where('date', '<=', $endLimit)
            ->selectRaw('DATE(date) as f, SUM(total) as total')
            ->groupBy('f')
            ->pluck('total', 'f');

        // ─── PROCESAR DÍA POR DÍA ──────────────────────────────────────
        $rows = [];
        $totals = [
            'capital_t'       => 0, // capital desembolsado
            'capital_cobrado' => 0, // sum CAPITAL payments
            'mensual_n'       => 0, 'mensual_s'  => 0, 'mensual_mora' => 0,
            'semanal_n'       => 0, 'semanal_s'  => 0, 'semanal_mora' => 0,
            'diario_n'        => 0, 'diario_s'   => 0, 'diario_mora'  => 0,
            'total_credito'   => 0, // sum interés + mora todos
            'otros_ing'       => 0, // ingreso3
            'otros_egr'       => 0, // entrada3
            'utilidad_caja3'  => 0,
            'ing_fijos'       => 0, 'ing_otros' => 0, 'ing_total' => 0,
            'egr_fijos'       => 0, 'egr_otros' => 0, 'egr_total' => 0,
        ];

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $today) break;

            // Capital T. (legacy: capineto)
            $capitalT = $capitalNetoByDate[$date] ?? 0;

            // Capital cobrado (legacy: totcaj1a)
            $capitalCobrado = $capitalCobradoByDate[$date] ?? 0;

            // Totales por tipo planilla (legacy: totcaj3)
            $cache3 = $totcaj3ByDate->get($date);
            $mensualN = (int) ($cache3->n_mensual ?? 0);
            $mensualS = (float) ($cache3->mensual ?? 0);
            $semanalN = (int) ($cache3->n_semanal ?? 0);
            $semanalS = (float) ($cache3->semanal ?? 0);
            $diarioN  = (int) ($cache3->n_diario ?? 0);
            $diarioS  = (float) ($cache3->diario ?? 0);

            // Mora por tipo (calculado de payments)
            $moraDay = $moraByTypeByDate->get($date, collect())->keyBy('tipo_planilla');
            $mensualMora = (float) ($moraDay->get(3)->total ?? 0);
            $semanalMora = (float) ($moraDay->get(1)->total ?? 0);
            $diarioMora  = (float) ($moraDay->get(4)->total ?? 0);

            $totalCredito = $mensualS + $mensualMora + $semanalS + $semanalMora + $diarioS + $diarioMora;

            // Otros movimientos
            $otrosIng = (float) ($incomes3[$date] ?? 0);
            $otrosEgr = (float) ($expenses3[$date] ?? 0);
            $utilidadCaja3 = $totalCredito + $otrosIng - $otrosEgr;

            // Ingreso Fijos/Otros (Caja 1)
            $incModos = $incomes1->get($date, collect())->keyBy('modo');
            $ingFijos = (float) ($incModos->get('Fijos')->total ?? 0);
            $ingOtros = (float) ($incModos->get('Otros')->total ?? 0);

            // Egreso Fijos/Otros (Caja 1)
            $expModos = $expenses1->get($date, collect())->keyBy('modo');
            $egrFijos = (float) ($expModos->get('Fijos')->total ?? 0);
            $egrOtros = (float) ($expModos->get('Otros')->total ?? 0);

            $row = [
                'fecha'           => $date,
                'day'             => $d,
                'is_sunday'       => Carbon::parse($date)->dayOfWeek === 0,
                'capital_t'       => $capitalT,
                'capital_cobrado' => $capitalCobrado,
                'mensual_n'       => $mensualN,
                'mensual_s'       => $mensualS,
                'mensual_mora'    => $mensualMora,
                'semanal_n'       => $semanalN,
                'semanal_s'       => $semanalS,
                'semanal_mora'    => $semanalMora,
                'diario_n'        => $diarioN,
                'diario_s'        => $diarioS,
                'diario_mora'     => $diarioMora,
                'total_credito'   => $totalCredito,
                'otros_ing'       => $otrosIng,
                'otros_egr'       => $otrosEgr,
                'utilidad_caja3'  => $utilidadCaja3,
                'ing_fijos'       => $ingFijos,
                'ing_otros'       => $ingOtros,
                'ing_total'       => $ingFijos + $ingOtros,
                'egr_fijos'       => $egrFijos,
                'egr_otros'       => $egrOtros,
                'egr_total'       => $egrFijos + $egrOtros,
            ];

            $rows[] = $row;

            foreach ($totals as $k => &$v) {
                $v += $row[$k] ?? 0;
            }
        }

        // ─── DETALLES MS / D / OTROS ───────────────────────────────────
        // INGRESO total por categoría
        $ingMS = $totals['mensual_s'] + $totals['mensual_mora']
               + $totals['semanal_s'] + $totals['semanal_mora'];
        $ingD  = $totals['diario_s'] + $totals['diario_mora'];
        $ingOtrosCat = $totals['otros_ing'];
        $ingTotal = $ingMS + $ingD + $ingOtrosCat;

        // EGRESO por aa (Diario, Mensual, D.M)
        $egrDiario  = (float) Expense::where('caja', 1)
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->where('reason', 'Diario')->sum('total');
        $egrMensual = (float) Expense::where('caja', 1)
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->where('reason', 'Mensual')->sum('total');
        $egrDM      = (float) Expense::where('caja', 1)
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->where('reason', 'D.M')->sum('total');

        $newvalor2 = round($egrDM / 2, 2);
        $egrMS = $egrMensual + $newvalor2;
        $egrD  = $egrDiario + $newvalor2;
        $egrTotalCat = $egrMS + $egrD;

        // TOTAL = INGRESO - EGRESO
        $totMS = $ingMS - $egrMS;
        $totD  = $ingD - $egrD;
        $totOtros = $ingOtrosCat;
        $totTotal = $totMS + $totD + $totOtros;

        // Porcentajes
        $pctMS = $ingMS > 0 ? round(($totMS * 100) / $ingMS, 2) : 0;
        $pctD  = $ingD > 0 ? round(($totD * 100) / $ingD, 2) : 0;
        // Legacy: denominador del % total NO incluye 'otros' (solo MS + D)
        $denomTotal = $ingMS + $ingD;
        $pctTotal = $denomTotal > 0 ? round(($totTotal * 100) / $denomTotal, 2) : 0;

        $detalleSummary = [
            'ing_ms' => $ingMS, 'ing_d' => $ingD, 'ing_otros' => $ingOtrosCat, 'ing_total' => $ingTotal,
            'egr_ms' => $egrMS, 'egr_d' => $egrD, 'egr_total' => $egrTotalCat,
            'tot_ms' => $totMS, 'tot_d' => $totD, 'tot_otros' => $totOtros, 'tot_total' => $totTotal,
            'pct_ms' => $pctMS, 'pct_d' => $pctD, 'pct_total' => $pctTotal,
        ];

        // ─── DISTRIBUCIÓN UTILIDAD (legacy usa INGRESO BRUTO, NO el total después de egresos)
        $msDiv2 = $ingMS > 0 ? $ingMS / 2 : 0;
        $msDiv6 = $msDiv2 > 0 ? $msDiv2 / 3 : 0;
        $dDiv2  = $ingD > 0 ? $ingD / 2 : 0;
        $dDiv6  = $dDiv2 > 0 ? $dDiv2 / 3 : 0;

        $distribution = [
            ['label' => 'Egreso',      'pct' => '16.67%', 'ms' => $msDiv6, 'd' => $dDiv6, 'total' => $msDiv6 + $dDiv6],
            ['label' => 'Sueldo',      'pct' => '16.67%', 'ms' => $msDiv6, 'd' => $dDiv6, 'total' => $msDiv6 + $dDiv6],
            ['label' => 'Provisiones', 'pct' => '16.67%', 'ms' => $msDiv6, 'd' => $dDiv6, 'total' => $msDiv6 + $dDiv6],
            ['label' => 'Utilidad',    'pct' => '50.00%', 'ms' => $msDiv2, 'd' => $dDiv2, 'total' => $msDiv2 + $dDiv2],
            ['label' => 'Total',       'pct' => '100.00%', 'ms' => $ingMS, 'd' => $ingD, 'total' => $ingMS + $ingD],
        ];

        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        // ─── TABLA 2: RESUMEN MENSUAL (Enero..mes seleccionado) ────────
        $resumenMensual = DB::table('cache_resumen_mensual')
            ->where('idano', $year)
            ->where('idmes', '<=', $month)
            ->orderBy('idmes')
            ->get();

        // Capital Neto por mes (sum capital_neto por mes)
        $capNetoByMes = DB::table('capital_neto')
            ->whereYear('fecha', $year)
            ->where(DB::raw('MONTH(fecha)'), '<=', $month)
            ->selectRaw('MONTH(fecha) as m, SUM(importe) as total')
            ->groupBy('m')
            ->pluck('total', 'm');

        // Egresos del mes por aa (Diario, Mensual, D.M)
        $egresosMonthly = DB::table('expenses')
            ->where('caja', 1)
            ->whereYear('date', $year)
            ->where(DB::raw('MONTH(date)'), '<=', $month)
            ->whereIn('reason', ['Diario', 'Mensual', 'D.M'])
            ->selectRaw('MONTH(date) as m, reason, SUM(total) as total')
            ->groupBy('m', 'reason')
            ->get()
            ->groupBy('m');

        $monthRowsData = [];
        $monthTotals = [
            'capital'=>0,'recucapi'=>0,'n1'=>0,'mensual'=>0,'mora3'=>0,
            'n2'=>0,'semanal'=>0,'mora1'=>0,'n3'=>0,'diario'=>0,'mora4'=>0,
            'total'=>0,'otros2'=>0,'egresov'=>0,'utilidad2'=>0,
            'fijoi'=>0,'otrosi'=>0,'fijoe'=>0,'otrose'=>0,
        ];
        foreach ($resumenMensual as $r) {
            $row = [
                'idmes'    => $r->idmes,
                'mes_nombre' => $months[$r->idmes] ?? '',
                'capineto' => (float) ($capNetoByMes[$r->idmes] ?? 0),
                'capital'  => (float) $r->capital,    // capital2 (capital cobrado)
                'recucapi' => (float) $r->recucapi,
                'n1'       => (int) $r->n1,
                'mensual'  => (float) $r->mensual,
                'mora3'    => (float) $r->mora3,
                'n2'       => (int) $r->n2,
                'semanal'  => (float) $r->semanal,
                'mora1'    => (float) $r->mora1,
                'n3'       => (int) $r->n3,
                'diario'   => (float) $r->diario,
                'mora4'    => (float) $r->mora4,
                'total'    => (float) $r->total,
                'otros2'   => (float) $r->otros2,
                'egresov'  => (float) $r->egresov,
                'utilidad2'=> (float) $r->utilidad2,
                'fijoi'    => (float) $r->fijoi,
                'otrosi'   => (float) $r->otrosi,
                'ingT'     => (float) ($r->fijoi + $r->otrosi),
                'fijoe'    => (float) $r->fijoe,
                'otrose'   => (float) $r->otrose,
                'egrT'     => (float) ($r->fijoe + $r->otrose),
            ];
            $monthRowsData[] = $row;
            foreach (['capital','recucapi','n1','mensual','mora3','n2','semanal','mora1','n3','diario','mora4','total','otros2','egresov','utilidad2','fijoi','otrosi','fijoe','otrose'] as $k) {
                $monthTotals[$k] += $row[$k] ?? 0;
            }
        }
        $monthTotals['capineto_sum'] = array_sum($capNetoByMes->all());

        // ─── TABLA 3: RESUMEN ANUAL (todos los años) ───────────────────
        $resumenAnual = DB::table('cache_resumen_mensual')
            ->select(
                'idano',
                DB::raw('SUM(capital) as capital'),
                DB::raw('SUM(recucapi) as recucapi'),
                DB::raw('SUM(n1) as n1'), DB::raw('SUM(mensual) as mensual'), DB::raw('SUM(mora3) as mora3'),
                DB::raw('SUM(n2) as n2'), DB::raw('SUM(semanal) as semanal'), DB::raw('SUM(mora1) as mora1'),
                DB::raw('SUM(n3) as n3'), DB::raw('SUM(diario) as diario'), DB::raw('SUM(mora4) as mora4'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(otros2) as otros2'), DB::raw('SUM(egresov) as egresov'), DB::raw('SUM(utilidad2) as utilidad2'),
                DB::raw('SUM(fijoi) as fijoi'), DB::raw('SUM(otrosi) as otrosi'),
                DB::raw('SUM(fijoe) as fijoe'), DB::raw('SUM(otrose) as otrose')
            )
            ->groupBy('idano')->orderBy('idano')->get();

        $capNetoByYear = DB::table('capital_neto')
            ->selectRaw('YEAR(fecha) as y, SUM(importe) as total')
            ->groupBy('y')->pluck('total', 'y');

        $yearRowsData = [];
        $yearTotals = [
            'capineto'=>0,'capital'=>0,'n1'=>0,'mensual'=>0,'mora3'=>0,
            'n2'=>0,'semanal'=>0,'mora1'=>0,'n3'=>0,'diario'=>0,'mora4'=>0,
            'total'=>0,'otros2'=>0,'egresov'=>0,'utilidad2'=>0,
            'fijoi'=>0,'otrosi'=>0,'fijoe'=>0,'otrose'=>0,
        ];
        foreach ($resumenAnual as $r) {
            $row = [
                'idano'    => $r->idano,
                'capineto' => (float) ($capNetoByYear[$r->idano] ?? 0),
                'capital'  => (float) $r->capital,
                'n1'       => (int) $r->n1,
                'mensual'  => (float) $r->mensual,
                'mora3'    => (float) $r->mora3,
                'n2'       => (int) $r->n2,
                'semanal'  => (float) $r->semanal,
                'mora1'    => (float) $r->mora1,
                'n3'       => (int) $r->n3,
                'diario'   => (float) $r->diario,
                'mora4'    => (float) $r->mora4,
                'total'    => (float) $r->total,
                'otros2'   => (float) $r->otros2,
                'egresov'  => (float) $r->egresov,
                'utilidad2'=> (float) $r->utilidad2,
                'fijoi'    => (float) $r->fijoi,
                'otrosi'   => (float) $r->otrosi,
                'ingT'     => (float) ($r->fijoi + $r->otrosi),
                'fijoe'    => (float) $r->fijoe,
                'otrose'   => (float) $r->otrose,
                'egrT'     => (float) ($r->fijoe + $r->otrose),
            ];
            $yearRowsData[] = $row;
            foreach ($yearTotals as $k => $v) {
                $yearTotals[$k] += $row[$k] ?? 0;
            }
        }

        $monthsCount = max(1, $month);

        // ─── DETALLES & DISTRIBUCIÓN del MENSUAL ACUMULADO ─────────────
        $ingMS_M = $monthTotals['mensual'] + $monthTotals['mora3']
                 + $monthTotals['semanal'] + $monthTotals['mora1'];
        $ingD_M  = $monthTotals['diario'] + $monthTotals['mora4'];
        $ingOtros_M = $monthTotals['otros2'];
        $ingTotal_M = $ingMS_M + $ingD_M + $ingOtros_M;

        // Egresos del año seleccionado (todos los meses hasta el seleccionado) por aa
        $egrDiarioY  = (float) DB::table('expenses')->where('caja',1)->whereYear('date',$year)->where(DB::raw('MONTH(date)'),'<=',$month)->where('reason','Diario')->sum('total');
        $egrMensualY = (float) DB::table('expenses')->where('caja',1)->whereYear('date',$year)->where(DB::raw('MONTH(date)'),'<=',$month)->where('reason','Mensual')->sum('total');
        $egrDMY      = (float) DB::table('expenses')->where('caja',1)->whereYear('date',$year)->where(DB::raw('MONTH(date)'),'<=',$month)->where('reason','D.M')->sum('total');

        $newvalor2_M = round($egrDMY / 2, 2);
        $egrMS_M = $egrMensualY + $newvalor2_M;
        $egrD_M  = $egrDiarioY + $newvalor2_M;
        $egrTotal_M = $egrMS_M + $egrD_M;

        $totMS_M = $ingMS_M - $egrMS_M;
        $totD_M  = $ingD_M - $egrD_M;
        $totTotal_M = $totMS_M + $totD_M + $ingOtros_M;

        $pctMS_M    = $ingMS_M > 0 ? round(($totMS_M * 100) / $ingMS_M, 2) : 0;
        $pctD_M     = $ingD_M  > 0 ? round(($totD_M * 100) / $ingD_M, 2) : 0;
        // Legacy: denominador del % total NO incluye 'otros' (solo MS + D)
        $denomTotal_M = $ingMS_M + $ingD_M;
        $pctTotal_M = $denomTotal_M > 0 ? round(($totTotal_M * 100) / $denomTotal_M, 2) : 0;

        $detalleSummaryMonth = [
            'ing_ms' => $ingMS_M, 'ing_d' => $ingD_M, 'ing_otros' => $ingOtros_M, 'ing_total' => $ingTotal_M,
            'egr_ms' => $egrMS_M, 'egr_d' => $egrD_M, 'egr_total' => $egrTotal_M,
            'tot_ms' => $totMS_M, 'tot_d' => $totD_M, 'tot_otros' => $ingOtros_M, 'tot_total' => $totTotal_M,
            'pct_ms' => $pctMS_M, 'pct_d' => $pctD_M, 'pct_total' => $pctTotal_M,
        ];

        // Distribución del mensual acumulado (usa INGRESO BRUTO igual al legacy)
        $msDiv2_M = $ingMS_M > 0 ? $ingMS_M / 2 : 0;
        $msDiv6_M = $msDiv2_M > 0 ? $msDiv2_M / 3 : 0;
        $dDiv2_M  = $ingD_M  > 0 ? $ingD_M / 2 : 0;
        $dDiv6_M  = $dDiv2_M > 0 ? $dDiv2_M / 3 : 0;

        $distributionMonth = [
            ['label' => 'Egreso',      'pct' => '16.67%', 'ms' => $msDiv6_M, 'd' => $dDiv6_M, 'total' => $msDiv6_M + $dDiv6_M],
            ['label' => 'Sueldo',      'pct' => '16.67%', 'ms' => $msDiv6_M, 'd' => $dDiv6_M, 'total' => $msDiv6_M + $dDiv6_M],
            ['label' => 'Provisiones', 'pct' => '16.67%', 'ms' => $msDiv6_M, 'd' => $dDiv6_M, 'total' => $msDiv6_M + $dDiv6_M],
            ['label' => 'Utilidad',    'pct' => '50.00%', 'ms' => $msDiv2_M, 'd' => $dDiv2_M, 'total' => $msDiv2_M + $dDiv2_M],
            ['label' => 'Total',       'pct' => '100.00%','ms' => $ingMS_M,  'd' => $ingD_M,  'total' => $ingMS_M + $ingD_M],
        ];

        return view('livewire.reports.cash-statistics', [
            'rows'                => $rows,
            'totals'              => $totals,
            'detalleSummary'      => $detalleSummary,
            'distribution'        => $distribution,
            'months'              => $months,
            'monthRowsData'       => $monthRowsData,
            'monthTotals'         => $monthTotals,
            'monthsCount'         => $monthsCount,
            'yearRowsData'        => $yearRowsData,
            'yearTotals'          => $yearTotals,
            'detalleSummaryMonth' => $detalleSummaryMonth,
            'distributionMonth'   => $distributionMonth,
        ]);
    }
}
