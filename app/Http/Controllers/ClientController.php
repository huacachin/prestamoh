<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index() { return view('clients.index'); }
    public function create() { return view('clients.create'); }
    public function edit(int $id) { return view('clients.edit', compact('id')); }
    public function show(int $id) { return view('clients.show', compact('id')); }
    public function gallery(int $id) { return view('clients.gallery', compact('id')); }
    public function aval(int $id) { return view('clients.aval', compact('id')); }
    public function ceased() { return view('clients.ceased'); }
    public function export(Request $request)
    {
        $user = auth()->user();
        $export = new \App\Exports\ClientsExport(
            status:      (string) $request->query('status', 'active'),
            nexpediente: (string) $request->query('nexpediente', ''),
            documento:   (string) $request->query('documento', ''),
            nombre:      (string) $request->query('nombre', ''),
            ruta:        (string) $request->query('ruta', ''),
            ejecutivo:   (string) $request->query('ejecutivo', ''),
            userId:      $user?->id,
            scopePropio: $user?->can('clientes.scope-propio') ?? false,
        );
        $prefix = $request->query('status') === 'inactive' ? 'clientes-cesados' : 'clientes';
        return \Maatwebsite\Excel\Facades\Excel::download($export, $prefix . '-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportHistory(int $id)
    {
        $export = new \App\Exports\ClientHistoryExport($id);
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'historial-cliente-' . $id . '.xlsx');
    }
}
