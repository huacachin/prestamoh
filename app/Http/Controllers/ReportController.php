<?php

namespace App\Http\Controllers;

use App\Livewire\Reports\Advisor;
use App\Livewire\Reports\Cancelled;
use App\Livewire\Reports\CashGeneral1;
use App\Livewire\Reports\CashGeneral2;
use App\Livewire\Reports\CashGeneral3;
use App\Livewire\Reports\CashStatistics;
use App\Livewire\Reports\Delinquent;
use App\Livewire\Reports\Payments;
use App\Livewire\Reports\Portfolio;
use App\Support\XlsResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Drill-down de los chips Nuevos/Refinanciados del dashboard. */
    public function desembolsos()
    {
        return view('reports.desembolsos');
    }

    public function portfolio()
    {
        return view('reports.portfolio');
    }

    public function payments()
    {
        return view('reports.payments');
    }

    public function delinquent()
    {
        return view('reports.delinquent');
    }

    public function cash()
    {
        return view('reports.cash');
    }

    public function simulator()
    {
        return view('reports.simulator');
    }

    public function advisor()
    {
        return view('reports.advisor');
    }

    public function cashStatistics()
    {
        return view('reports.cash-statistics');
    }

    public function creditStatistics()
    {
        return view('reports.credit-statistics');
    }

    public function cashGeneral1()
    {
        return view('reports.cash-general-1');
    }

    public function cashGeneral2()
    {
        return view('reports.cash-general-2');
    }

    public function cashGeneral3()
    {
        return view('reports.cash-general-3');
    }

    public function cancelled()
    {
        return view('reports.cancelled');
    }

    public function exportPortfolio(Request $request)
    {
        $c = new Portfolio;
        $c->selemes0 = (string) $request->query('selemes0', '');
        $c->selecano0 = (string) $request->query('selecano0', '');
        $c->seletipl0 = (string) $request->query('seletipl0', '');
        $c->exp = (string) $request->query('exp', '');
        $c->codigo = (string) $request->query('codigo', '');
        $c->cdni = (string) $request->query('cdni', '');
        $c->cnombre = (string) $request->query('cnombre', '');
        $c->casesor = (string) $request->query('casesor', '');
        $c->fechai = (string) $request->query('fechai', '');
        $c->fechaf = (string) $request->query('fechaf', '');
        // Drill-down por tasa: el Excel sale acotado igual que la pantalla.
        $c->fInteres = (string) $request->query('fInteres', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.portfolio', [
            'rows' => $d['rows'],
            'totals' => $d['totals'],
            'morisidad' => $d['morisidad'],
            'tipoTotals' => $d['tipoTotals'],
            'byInteres' => $d['byInteres'],
            'vignt' => $d['vignt'],
            'venc' => $d['venc'],
            'tc' => $d['tc'],
        ], 'Resumen De Creditos.xls');
    }

    public function exportDelinquent(Request $request)
    {
        $c = new Delinquent;
        $c->selemes0 = (string) $request->query('selemes0', '');
        $c->selecano0 = (string) $request->query('selecano0', '');
        $c->seletipl0 = (string) $request->query('seletipl0', '');
        $c->exp = (string) $request->query('exp', '');
        $c->codigo = (string) $request->query('codigo', '');
        $c->cdni = (string) $request->query('cdni', '');
        $c->cnombre = (string) $request->query('cnombre', '');
        $c->casesor = (string) $request->query('casesor', '');
        $c->fechai = (string) $request->query('fechai', '');
        $c->fechaf = (string) $request->query('fechaf', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.delinquent', [
            'rows' => $d['rows'],
            'totals' => $d['totals'],
            'tc' => $d['tc'],
            'tipoTotals' => $d['tipoTotals'],
            'vignt' => $d['vignt'],
            'venc' => $d['venc'],
        ], 'Pendientes Por Cobrar.xls');
    }

    public function exportCancelled(Request $request)
    {
        $c = new Cancelled;
        $c->selemes = $request->query('selemes', date('m'));
        $c->selecano = $request->query('selecano', date('Y'));
        $c->seletipl = (string) $request->query('seletipl', '');
        $c->exp = (string) $request->query('exp', '');
        $c->codigo = (string) $request->query('codigo', '');
        $c->cdni = (string) $request->query('cdni', '');
        $c->cnombre = (string) $request->query('cnombre', '');
        $c->casesor = (string) $request->query('casesor', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.cancelled', [
            'rows' => $d['rows'],
            'totals' => $d['totals'],
            'distribution' => $d['distribution'],
        ], 'Cancelado.xls');
    }

    public function exportPayments(Request $request)
    {
        $c = new Payments;
        $c->tipo = (string) $request->query('tipo', '1');
        $c->compra = (string) $request->query('compra', '');
        $c->fei = (string) $request->query('fei', now()->format('Y-m-d'));
        $c->fef = (string) $request->query('fef', now()->format('Y-m-d'));

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.payments', [
            'rows' => $d['rows'],
            'totals' => $d['totals'],
        ], 'Reporte De Pago.xls');
    }

    public function exportCashGeneral1(Request $request)
    {
        $c = new CashGeneral1;
        $c->selemes = (string) $request->query('selemes', date('m'));
        $c->selecano = (string) $request->query('selecano', date('Y'));
        $c->seletipl = (string) $request->query('seletipl', '0000');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.cash-general-1', [
            'days' => $d['days'],
            'Tcpi' => $d['Tcpi'],
            'Tcpi2' => $d['Tcpi2'],
            'Tint' => $d['Tint'],
            'Tmor4' => $d['Tmor4'],
            'toff' => $d['toff'],
            'toff2' => $d['toff2'],
            'toff1' => $d['toff1'],
        ], 'Reporte General Caja 1.xls');
    }

    public function exportCashGeneral2(Request $request)
    {
        $c = new CashGeneral2;
        $c->month = (int) $request->query('month', now()->month);
        $c->year = (int) $request->query('year', now()->year);

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.cash-general-2', [
            'report' => $d['report'],
        ], 'Reporte General Caja 2.xls');
    }

    public function exportCashGeneral3(Request $request)
    {
        $c = new CashGeneral3;
        $c->month = (int) $request->query('month', now()->month);
        $c->year = (int) $request->query('year', now()->year);

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.cash-general-3', [
            'report' => $d['report'],
        ], 'Reporte General Caja 3.xls');
    }

    // Equivalente del legacy caja-estadisticae1.php: el Excel espejo de
    // /reports/cash-statistics (diario del mes + detalles + distribucion +
    // resumen mensual y anual), con los mismos datos que pinta la pantalla.
    public function exportCashStatistics(Request $request)
    {
        $c = new CashStatistics;
        $c->month = (int) $request->query('month', now()->month);
        $c->year = (int) $request->query('year', now()->year);

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.cash-statistics', [
            'rows' => $d['rows'],
            'totals' => $d['totals'],
            'detalleSummary' => $d['detalleSummary'],
            'distribution' => $d['distribution'],
            'months' => $d['months'],
            'monthRowsData' => $d['monthRowsData'],
            'monthTotals' => $d['monthTotals'],
            'monthsCount' => $d['monthsCount'],
            'detalleSummaryMonth' => $d['detalleSummaryMonth'],
            'distributionMonth' => $d['distributionMonth'],
            'yearRowsData' => $d['yearRowsData'],
            'yearTotals' => $d['yearTotals'],
            'month' => $c->month,
            'year' => $c->year,
        ], 'Reporte Estadistico De Caja.xls');
    }

    public function exportAdvisor(Request $request)
    {
        $c = new Advisor;
        $c->selemes = (string) $request->query('selemes', str_pad((string) now()->month, 2, '0', STR_PAD_LEFT));
        $c->selecano = (string) $request->query('selecano', now()->year);
        $c->ejecutivo = (string) $request->query('ejecutivo', 'Todos');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.reports.advisor', [
            'rows' => $d['rows'],
            'tot' => $d['tot'],
            'avg' => $d['avg'],
            'monthlyHistory' => $d['monthlyHistory'],
        ], 'Reporte Asesor.xls');
    }

    public function exportSimulator(Request $request)
    {
        return XlsResponse::make('exports.reports.simulator', [
            'capital' => (float) $request->query('capital', 0),
            'interes' => (float) $request->query('tasa', 0),
            'nombre' => (string) $request->query('nombre', ''),
            'meses' => (int) $request->query('meses', 60),
        ], 'Simulacro.xls');
    }
}
