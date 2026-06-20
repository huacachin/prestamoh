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
        $c = new \App\Livewire\Credits\Index();
        $c->nombre    = (string) $request->query('nombre', '');
        $c->codigo    = (string) $request->query('codigo', '');
        $c->ejecutivo = (string) $request->query('ejecutivo', '');
        $c->seletipl  = (string) $request->query('seletipl', '');

        $d = $c->render()->getData();

        return \App\Support\XlsResponse::make('exports.credits', [
            'credits'  => $d['credits'],
            'pagosMap' => $d['pagosMap'],
            'sumtotal' => $d['sumtotal'],
            'suminter' => $d['suminter'],
            'sumtotax' => $d['sumtotax'],
            'sumpagos' => $d['sumpagos'],
            'sumsaldo' => $d['sumsaldo'],
        ], 'Prestamos.xls');
    }

    public function exportSchedule(int $id)
    {
        // Fuente de verdad: el mismo componente que pinta la pantalla del cronograma,
        // para que el Excel muestre exactamente los mismos datos (periodo=fecha_pago,
        // pagado/mora desde la tabla payments, OTROS para TODOS los tipos de planilla).
        $c = new \App\Livewire\Credits\Schedule();
        $c->mount($id);
        $d = $c->render()->getData();

        $fmt = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '';

        // Cuotas regulares del cronograma
        $rows = collect($d['rows'])->map(fn ($r) => (object) [
            'num'        => $r['n'],
            'periodo'    => $fmt($r['periodo']),
            'capital'    => $r['capital'],
            'interes'    => $r['interes'],
            'total'      => $r['total'],
            'mora'       => $r['mora'],
            'pagado'     => $r['pagado'],
            'fecha_pago' => $fmt($r['fecha_pago']),
            'color'      => $r['color'],
        ]);

        // Pagos OTROS (fuera del cronograma) — la vista los pinta como "extras"
        $dailyExtras = collect($d['otrosRows'])->map(fn ($r) => [
            'num'    => $r['n'],
            'mora'   => $r['mora'],
            'pagado' => $r['pagado'],
            'fecha'  => $fmt($r['fecha_pago']),
        ])->all();

        return \App\Support\XlsResponse::make('exports.schedule', [
            'rows'         => $rows,
            'totCap'       => $d['totals']['capital'],
            'totInt'       => $d['totals']['interes'],
            'totMora'      => $d['totals']['mora'],
            'totPag'       => $d['totals']['pagado'],
            'dailyExtras'  => $dailyExtras,
            'sumMoraExtra' => $d['sumOtrosMora'],
            'sumImpoExtra' => $d['sumOtros'],
            'saldo'        => $d['saldo'],
        ], 'Cronograma.xls');
    }
}
