<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index() { return view('payments.index'); }
    public function create(?int $creditId = null) { return view('payments.create', compact('creditId')); }
    public function daily() { return view('payments.daily'); }
    public function weekly() { return view('payments.weekly'); }
    public function monthly() { return view('payments.monthly'); }
    public function export(Request $request) { }
}
