<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function portfolio() { return view('reports.portfolio'); }
    public function payments() { return view('reports.payments'); }
    public function delinquent() { return view('reports.delinquent'); }
    public function cash() { return view('reports.cash'); }
    public function simulator() { return view('reports.simulator'); }
    public function advisor() { return view('reports.advisor'); }
    public function cashStatistics() { return view('reports.cash-statistics'); }
    public function creditStatistics() { return view('reports.credit-statistics'); }
    public function cashGeneral1() { return view('reports.cash-general-1'); }
    public function cashGeneral2() { return view('reports.cash-general-2'); }
    public function cashGeneral3() { return view('reports.cash-general-3'); }
    public function cancelled() { return view('reports.cancelled'); }

    public function exportCancelled(Request $request)
    {
        $export = new \App\Exports\CancelledExport(filters: $request->query());
        return Excel::download($export, 'cancelados-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportPayments(Request $request)
    {
        $export = new \App\Exports\PaymentsReportExport(filters: $request->query());
        return Excel::download($export, 'reporte-pagos-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportCashGeneral2(Request $request)
    {
        $export = new \App\Exports\CashGeneral2Export(filters: $request->query());
        return Excel::download($export, 'caja-general-2-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportAdvisor(Request $request)
    {
        $export = new \App\Exports\AdvisorExport(filters: $request->query());
        return Excel::download($export, 'reporte-asesor-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportSimulator(Request $request)
    {
        $export = new \App\Exports\SimulatorExport(
            capital: (float) $request->query('capital', 0),
            interes: (float) $request->query('tasa', 0),
            nombre:  (string) $request->query('nombre', ''),
            meses:   (int) $request->query('meses', 60),
        );
        return Excel::download($export, 'simulacion-credito-' . now()->format('Ymd-His') . '.xlsx');
    }
}
