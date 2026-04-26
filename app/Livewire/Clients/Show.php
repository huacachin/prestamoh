<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Show extends Component
{
    public Client $client;

    public function mount(int $id)
    {
        $this->client = Client::with(['asesor:id,name', 'headquarter:id,name'])
            ->findOrFail($id);
    }

    public function render()
    {
        // Cargar créditos del cliente ordenados por fecha
        $credits = DB::table('credits')
            ->where('client_id', $this->client->id)
            ->orderBy('fecha_prestamo')
            ->get();

        $creditIds = $credits->pluck('id')->all();

        // Pre-cargar pagos relevantes (no-Gat) por crédito
        $sumNoMoraByCredit = []; // ap = sum(totalgeneral) WHERE NOT MORA y NOT Gat
        $sumMoraByCredit = [];   // mora total
        $maxFechaByCredit = [];  // max fecha de pago (no Gat)
        $idcanRefSet = [];       // ids que han sido referenciados como idcan (para "verSt")

        if (!empty($creditIds)) {
            $allPays = DB::table('payments')
                ->whereIn('credit_id', $creditIds)
                ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
                ->select('credit_id', 'fecha', 'monto', 'documento')
                ->get();

            foreach ($allPays as $p) {
                $isMora = (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA');
                if ($isMora) {
                    $sumMoraByCredit[$p->credit_id] = ($sumMoraByCredit[$p->credit_id] ?? 0) + (float) $p->monto;
                } else {
                    $sumNoMoraByCredit[$p->credit_id] = ($sumNoMoraByCredit[$p->credit_id] ?? 0) + (float) $p->monto;
                }
                if ($p->fecha) {
                    $f = Carbon::parse($p->fecha)->format('Y-m-d');
                    $maxFechaByCredit[$p->credit_id] = isset($maxFechaByCredit[$p->credit_id])
                        ? max($maxFechaByCredit[$p->credit_id], $f)
                        : $f;
                }
            }

            // Créditos referenciados como idcan (refinanciados)
            $refs = DB::table('credits')
                ->whereIn('idcan', $creditIds)
                ->pluck('idcan')->unique()->all();
            $idcanRefSet = array_flip($refs);
        }

        // Procesar cada crédito
        $rows = [];
        $totals = [
            'capital'       => 0,    // sum importe
            'interes_t'     => 0,    // sum interes_total (legacy 'interestot')
            'total_a_pag'   => 0,    // sum (importe + interes)
            'capital_r'     => 0,    // sum rftq
            'interes_g'     => 0,    // sum iminte
            'mora'          => 0,    // sum resulMor
            'total_pag'     => 0,    // sum (rftq + iminte + mora)
            'saldo_capital' => 0,    // sum saldo2
            'mora_x_dia'    => 0,    // sum mora x dia
        ];
        $count = 0;

        foreach ($credits as $cr) {
            $count++;
            $importe = (float) $cr->importe;
            $interesPct = (float) $cr->interes;
            $apSum = (float) ($sumNoMoraByCredit[$cr->id] ?? 0); // suma pagos no-MORA
            $moraSum = (float) ($sumMoraByCredit[$cr->id] ?? 0);

            // Lógica legacy
            $totinteres = round($importe * ($interesPct / 100), 2);
            $difeinter  = $totinteres - $apSum;
            $tintere    = $totinteres;

            if ($difeinter <= 0) {
                $rftq   = abs($difeinter);
                $iminte = $totinteres;
            } else {
                $rftq   = 0;
                $iminte = $apSum;
            }

            $saldo2 = $importe + $tintere - $rftq - $iminte;

            // Background colors
            $bg = '';
            $color = 'black';
            if (round($saldo2, 2) > 0) {
                $bg = 'red';
                $color = 'white';
                if (isset($idcanRefSet[$cr->id])) {
                    $bg = '';
                    $color = 'black';
                }
            }
            if ((int) $cr->estado === 1) {
                $bg = 'yellow';
                $color = 'red';
            }

            // Días mora
            $newdias = 0;
            if ($cr->fecha_cancelacion && $cr->fecha_vencimiento) {
                $newdias = Carbon::parse($cr->fecha_vencimiento)->diffInDays(
                    Carbon::parse($cr->fecha_cancelacion), false
                );
                if ($newdias < 0) $newdias = 0;
            }
            $newInterez = round($importe * ($interesPct / 100), 2);
            $intediasdias = round($newInterez / 30, 2);
            $mxd = $newdias > 0 ? round($newdias * $intediasdias, 2) : 0;

            // Estado label/imagen (legacy: estado=0 → Activado, estado=1 → Desactivado)
            $estadoActivado = (int) $cr->estado === 0;

            $rows[] = [
                'n'                => $count,
                'usuario'          => $cr->usuario ?? '',  // legacy 'user' field (cajero/usuario que registró)
                'codigo'           => $cr->id,
                'estado_activado'  => $estadoActivado,
                'cod_ant'          => $cr->idcan,
                'f_credito'        => $cr->fecha_prestamo ? Carbon::parse($cr->fecha_prestamo)->format('Y-m-d') : '',
                'f_vcto'           => $cr->fecha_vencimiento ? Carbon::parse($cr->fecha_vencimiento)->format('Y-m-d') : '',
                'f_pago'           => $maxFechaByCredit[$cr->id] ?? '',
                'f_cancelado'      => $cr->fecha_cancelacion ? Carbon::parse($cr->fecha_cancelacion)->format('Y-m-d') : '',
                'capital'          => $importe,
                'interes_pct'      => round($interesPct, 2),
                'interes'          => $newInterez,
                'cuotas'           => $cr->cuotas,
                'total'            => $importe + $newInterez,
                'capital_r'        => $rftq,
                'interes_g'        => $iminte,
                'mora'             => $moraSum,
                'total_pag'        => $rftq + $iminte + $moraSum,
                'saldo_capital'    => $saldo2,
                'mora_s'           => $mxd,                                    // S/ = newdias × intediasdias
                'mxd'              => $newdias > 0 ? $intediasdias : 0,        // MxD = intediasdias
                'dias'             => $newdias,
                'gat'              => (float) $cr->gat,
                'asesor'           => $cr->asesor ?? '',
                'bg'               => $bg,
                'color'            => $color,
            ];

            $totals['capital']       += $importe;
            $totals['interes_t']     += (float) ($cr->interes_total ?? 0);
            $totals['total_a_pag']   += round($importe + $newInterez, 2);
            $totals['capital_r']     += $rftq;
            $totals['interes_g']     += $iminte;
            $totals['mora']          += $moraSum;
            $totals['total_pag']     += $rftq + $iminte + $moraSum;
            $totals['saldo_capital'] += $saldo2;
            $totals['mora_x_dia']    += $mxd;
        }

        return view('livewire.clients.show', [
            'rows'   => $rows,
            'totals' => $totals,
        ]);
    }
}
