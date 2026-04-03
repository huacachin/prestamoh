<?php
namespace App\Http\Controllers;
class ReportController extends Controller
{
    public function portfolio() { return view('reports.portfolio'); }
    public function payments() { return view('reports.payments'); }
    public function delinquent() { return view('reports.delinquent'); }
    public function cash() { return view('reports.cash'); }
    public function simulator() { return view('reports.simulator'); }
}
