<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Credit;
use App\Support\MorosidadClientes;
use App\Support\XlsResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index()
    {
        return view('clients.index');
    }

    public function create()
    {
        return view('clients.create');
    }

    public function edit(int $id)
    {
        return view('clients.edit', compact('id'));
    }

    public function show(int $id)
    {
        return view('clients.show', compact('id'));
    }

    public function gallery(int $id)
    {
        return view('clients.gallery', compact('id'));
    }

    public function aval(int $id)
    {
        return view('clients.aval', compact('id'));
    }

    public function ceased()
    {
        return view('clients.ceased');
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $status = (string) $request->query('status', 'active');
        $nexpediente = (string) $request->query('nexpediente', '');
        $documento = (string) $request->query('documento', '');
        $nombre = (string) $request->query('nombre', '');
        $ruta = (string) $request->query('ruta', '');
        $giro = (string) $request->query('giro', '');
        $ejecutivo = (string) $request->query('ejecutivo', '');
        $scopePropio = $user?->can('clientes.scope-propio') ?? false;

        $query = Client::query()
            ->where('status', $status)
            ->with(['asesor:id,name,username']);

        if ($scopePropio && $user?->id) {
            $query->where('asesor_id', $user->id);
        }
        if (trim($documento) !== '') {
            $query->where('documento', trim($documento));
        }
        if (trim($nombre) !== '') {
            $t = trim($nombre);
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'like', "%{$t}%")
                    ->orWhere('apellido_pat', 'like', "%{$t}%")
                    ->orWhere('apellido_mat', 'like', "%{$t}%");
            });
        }
        if (trim($nexpediente) !== '') {
            $query->where('expediente', trim($nexpediente));
        }
        if (trim($ejecutivo) !== '') {
            if ($ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $query->where('asesor_id', $ejecutivo);
            }
        }
        if (trim($ruta) !== '') {
            $query->where('zona', 'like', '%'.trim($ruta).'%');
        }
        if (trim($giro) !== '') {
            $query->where('giro', 'like', '%'.trim($giro).'%');
        }

        // Filtro de morosidad de los chips (al día / 2 / 3 / 4+ / ejecución):
        // mismo cálculo compartido que la pantalla (MorosidadClientes), así el
        // Excel refleja exactamente lo que se está viendo al exportar.
        $estado = (string) $request->query('estado', '');
        if ($estado !== '') {
            $ids = (clone $query)->pluck('clients.id')->toArray();
            ['morosidad' => $morosidad, 'enEjecucion' => $enEjecucion] = MorosidadClientes::calcular($ids);
            $query->whereIn('clients.id', MorosidadClientes::idsDelNivel($ids, $estado, $morosidad, $enEjecucion));
        }

        $clients = $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->get();

        $esCesado = $status === 'inactive';
        $filename = $esCesado ? 'Clientes Cesados.xls' : 'Clientes.xls';

        // IDs de clientes con crédito vigente (para colorear filas, homologa pantalla)
        $clientIds = $clients->pluck('id')->toArray();
        $clientsWithCredit = [];
        if (! empty($clientIds)) {
            $clientsWithCredit = Credit::whereIn('client_id', $clientIds)
                ->where('situacion', 'Activo')
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();
        }

        return XlsResponse::make('exports.clients', [
            'clients' => $clients,
            'esCesado' => $esCesado,
            'clientsWithCredit' => $clientsWithCredit,
        ], $filename);
    }

    public function exportHistory(int $id)
    {
        $client = Client::find($id);

        // Analista (scope-propio): solo el historial de SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) ($client?->asesor_id) !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );

        $credits = Credit::where('client_id', $id)
            ->orderBy('fecha_prestamo', 'asc')->get();

        $ids = $credits->pluck('id')->all();
        $paySinMora = [];
        $payInteres = [];   // interés realmente pagado (pagos documento=INTERES)
        $payMora = [];
        $maxFecha = [];
        $idcanRefSet = [];   // créditos referenciados como idcan (refinanciados) → revierten color
        if (! empty($ids)) {
            $refs = DB::table('credits')->whereIn('idcan', $ids)
                ->pluck('idcan')->unique()->all();
            $idcanRefSet = array_flip($refs);
        }
        if (! empty($ids)) {
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->where(function ($q) {
                    $q->whereNull('documento')->orWhere('documento', 'NOT LIKE', 'MORA%');
                })
                ->selectRaw('credit_id, SUM(monto) t')->groupBy('credit_id')->get() as $r) {
                $paySinMora[$r->credit_id] = (float) $r->t;
            }
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->where('documento', 'LIKE', 'MORA%')
                ->selectRaw('credit_id, SUM(monto) t')->groupBy('credit_id')->get() as $r) {
                $payMora[$r->credit_id] = (float) $r->t;
            }
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->where('documento', 'INTERES')
                ->selectRaw('credit_id, SUM(monto) t')->groupBy('credit_id')->get() as $r) {
                $payInteres[$r->credit_id] = (float) $r->t;
            }
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->selectRaw('credit_id, MAX(fecha) m')->groupBy('credit_id')->get() as $r) {
                $maxFecha[$r->credit_id] = $r->m;
            }
        }

        $rows = [];
        $totom = 0;
        $tdm = 0;
        $total1 = 0;
        $mmay = 0;
        $imintc = 0;
        $moramora = 0;
        $total2 = 0;
        $total3 = 0;
        $montomorxdia = 0;

        foreach ($credits as $i => $c) {
            $importe = (float) $c->importe;
            $intPct = (float) $c->interes;
            $tintere = round($importe * $intPct / 100, 2);
            $apSinMora = $paySinMora[$c->id] ?? 0;
            $mora = $payMora[$c->id] ?? 0;

            // Interés G. = interés realmente pagado (no un solo período); Capital R.
            // = resto de lo pagado no-mora. Igual que la pantalla Historial.
            $iminte = min((float) ($payInteres[$c->id] ?? 0), (float) $apSinMora);
            $rftq = $apSinMora - $iminte;

            $totalCred = $importe + $tintere;
            $totalGan = $rftq + $iminte + $mora;
            $saldo2 = $importe + $tintere - $rftq - $iminte;

            // Colores condicionales por fila (homologa pantalla Historial / legacy cliente_viewexcel)
            $bg = '';
            $color = 'black';
            if (round($saldo2, 2) > 0) {
                $bg = 'red';
                $color = 'white';
                if (isset($idcanRefSet[$c->id])) {  // refinanciado → revierte
                    $bg = '';
                    $color = 'black';
                }
            }
            if ((int) $c->estado === 1) {           // desactivado → amarillo/rojo (override)
                $bg = 'yellow';
                $color = 'red';
            }

            $newdias = 0;
            if ($c->fecha_cancelacion && $c->fecha_vencimiento) {
                $newdias = (int) $c->fecha_vencimiento->diffInDays($c->fecha_cancelacion, false);
            }
            $intediasdias = round($tintere / 30, 2);
            $sCol = $newdias > 0 ? round($newdias * $intediasdias, 2) : '';
            $mxd = $newdias > 0 ? $intediasdias : '';
            $dias = $newdias > 0 ? $newdias : '';

            $totom += $importe;
            $tdm += (float) $c->interes_total;
            $total1 += round($totalCred, 2);
            $mmay += $rftq;
            $imintc += $iminte;
            $moramora += $mora;
            $total2 += $totalGan;
            $total3 += $saldo2;
            $montomorxdia += $newdias > 0 ? round($newdias * $intediasdias, 2) : 0;

            $rows[] = [
                'n' => $i + 1,
                'usuario' => $c->usuario,
                'codigo' => $c->id,
                'cod_ant' => $c->idcan,
                'f_credito' => $c->fecha_prestamo?->format('Y-m-d'),
                'f_vcto' => $c->fecha_vencimiento?->format('Y-m-d'),
                'f_pago' => $maxFecha[$c->id] ?? '',
                'f_cancelado' => $c->fecha_cancelacion?->format('Y-m-d'),
                'capital' => $importe,
                'pct' => round($intPct, 2),
                'interes' => (float) $c->interes_total,
                'cuotas' => (int) $c->cuotas,
                'total' => round($totalCred, 2),
                'capital_r' => round($rftq, 2),
                'interes_g' => round($iminte, 2),
                'mora' => $mora,
                'total_gan' => round($totalGan, 2),
                'saldo' => round($saldo2, 2),
                's' => $sCol === '' ? '' : number_format($sCol, 2),
                'mxd' => $mxd === '' ? '' : number_format($mxd, 2),
                'dias' => $dias,
                'gat' => $c->gat,
                'asesor' => $c->asesor ?? '',
                'bg' => $bg,
                'color' => $color,
            ];
        }

        return XlsResponse::make('exports.client-history', [
            'client' => $client,
            'clientId' => $id,
            'rows' => $rows,
            'totom' => $totom,
            'tdm' => $tdm,
            'total1' => $total1,
            'mmay' => $mmay,
            'imintc' => $imintc,
            'moramora' => $moramora,
            'total2' => $total2,
            'total3' => $total3,
            'montomorxdia' => $montomorxdia,
        ], 'Historial.xls');
    }
}
