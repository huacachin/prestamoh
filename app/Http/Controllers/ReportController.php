<?php

namespace App\Http\Controllers;

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
}
