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
    public function massDeleteEdit(int $id) { return view('credits.mass-delete-edit', compact('id')); }
    public function export(Request $request)
    {
        $export = new \App\Exports\CreditsExport(
            nombre:    (string) $request->query('nombre', ''),
            codigo:    (string) $request->query('codigo', ''),
            ejecutivo: (string) $request->query('ejecutivo', ''),
            seletipl:  (string) $request->query('seletipl', ''),
        );
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'creditos-' . now()->format('Ymd-His') . '.xlsx');
    }
}
