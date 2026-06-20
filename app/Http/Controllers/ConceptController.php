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
        $tipo    = (string) $request->query('tipo', '2');
        $compra  = (string) $request->query('compra', '');
        $estados = (string) $request->query('estados', 'Activo');

        $query = \App\Models\Concept::query();

        if (trim($compra) !== '') {
            $t = trim($compra);
            if ($tipo === '1') {
                $query->where('code', 'like', "%{$t}%");
            } else {
                $query->where('name', 'like', "%{$t}%");
            }
        }

        $query->where('status', $estados === 'Cesado' ? 'inactive' : 'active');

        $concepts = $query->orderBy('code', 'asc')->get();

        return \App\Support\XlsResponse::make('exports.concepts', [
            'concepts' => $concepts,
        ], 'Conceptos Fijos.xls');
    }
}
