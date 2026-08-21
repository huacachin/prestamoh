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
        $c->todos = true; // el Excel exporta el conjunto completo, sin paginar
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

    // Excel de /credits/mass-delete. Ruta dedicada (no acción Livewire):
    // Livewire ignora respuestas que no sean streamDownload y el botón
    // wire:click no descargaba nada.
    public function exportMassDeletions(Request $request)
    {
        $c = new \App\Livewire\Credits\MassDelete();
        $c->tipo = (string) $request->query('tipo', '1');
        $c->compra = (string) $request->query('buscar', '');
        $c->fei = (string) $request->query('desde', now()->format('Y-m-d'));
        $c->fef = (string) $request->query('hasta', now()->format('Y-m-d'));

        return $c->exportExcel();
    }

    public function exportSchedule(int $id)
    {
        // Fuente de verdad: el mismo componente que pinta la pantalla del cronograma,
        // para que el Excel muestre exactamente los mismos datos (periodo=fecha_pago,
        // pagado/mora desde la tabla payments, OTROS para TODOS los tipos de planilla).
        $c = new \App\Livewire\Credits\Schedule();
        $c->mount($id);
        $d = $c->render()->getData();

        // Homologado 21/08: el Excel es espejo de la pantalla (mismas columnas
        // Cuota..Fecha Pago, numeración de vencidas/pendientes, Mora unificada
        // y las filas del pie), así que recibe la data del componente tal cual.
        return \App\Support\XlsResponse::make('exports.schedule', [
            'credit' => $c->credit,
            'rows' => $d['rows'],
            'otrosRows' => $d['otrosRows'],
            'totals' => $d['totals'],
            'sumOtros' => $d['sumOtros'],
            'sumOtrosMora' => $d['sumOtrosMora'],
            'sumOtrosExon' => $d['sumOtrosExon'],
            'sumOtrosExonDias' => $d['sumOtrosExonDias'],
            'saldo' => $d['saldo'],
            'totalGeneral' => $d['totalGeneral'],
            'capPendienteTotal' => $d['capPendienteTotal'],
        ], 'Cronograma.xls');
    }
}
