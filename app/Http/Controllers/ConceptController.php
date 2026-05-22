<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConceptController extends Controller
{
    public function index() { return view('concepts.index'); }
    public function create() { return view('concepts.create'); }
    public function edit(int $id) { return view('concepts.edit', ['conceptId' => $id]); }

    public function export(Request $request)
    {
        $export = new \App\Exports\ConceptsExport(
            tipo:    (string) $request->query('tipo', '2'),
            compra:  (string) $request->query('compra', ''),
            estados: (string) $request->query('estados', 'Activo'),
        );
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'conceptos-' . now()->format('Ymd-His') . '.xlsx');
    }
}
