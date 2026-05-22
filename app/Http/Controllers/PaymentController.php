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
    public function refinance(int $creditId) { return view('payments.refinance', compact('creditId')); }
    public function export(Request $request)
    {
        $user = auth()->user();
        $export = new \App\Exports\PaymentsExport(
            nombre:  (string) $request->query('nombre', ''),
            nombre1: (string) $request->query('nombre1', ''),
            codigo:  (string) $request->query('codigo1', ''),
            userId:  $user?->id,
            scopePropio: $user?->can('clientes.scope-propio') ?? false,
        );
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'pagos-' . now()->format('Ymd-His') . '.xlsx');
    }
}
