<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index() { return view('credits.index'); }
    public function create(?int $clientId = null) { return view('credits.create', compact('clientId')); }
    public function show(int $id) { return view('credits.show', compact('id')); }
    public function schedule(int $id) { return view('credits.schedule', compact('id')); }
    public function edit(int $id) { return view('credits.edit', compact('id')); }
    public function activate() { return view('credits.activate'); }
    public function changeStatus() { return view('credits.change-status'); }
    public function massDelete() { return view('credits.mass-delete'); }
    public function export(Request $request) { }
}
