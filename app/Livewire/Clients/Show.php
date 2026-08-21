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

        // Analista (scope-propio): solo SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
    }

    public function render()
    {
        // Cargar créditos del cliente ordenados por fecha
        $credits = DB::table('credits')
            ->where('client_id', $this->client->id)
            ->orderBy('fecha_prestamo')
            ->get();

        $creditIds = $credits->pluck('id')->all();

        // Saldo real del cronograma por crédito. La fórmula legacy de la fila
        // (importe + interés teórico − pagado) asume interés completo; en una
        // cancelación anticipada el interés no devengado se CONDONA en el
        // cronograma, así que quedaría un falso pendiente rojo. Si el
        // cronograma está saldado, no hay deuda real.
        $cronAgg = empty($creditIds) ? collect() : DB::table('credit_installments')
            ->whereIn('credit_id', $creditIds)
            ->selectRaw('credit_id, SUM(importe_cuota + importe_interes - importe_aplicado - interes_aplicado) s, SUM(importe_interes) int_cron')
            ->groupBy('credit_id')
            ->get()
            ->keyBy('credit_id');
        $saldoCronByCredit = $cronAgg->map(fn ($r) => (float) $r->s);
        // Interés realmente devengado según el cronograma. En una cancelación
        // anticipada el cronograma condona el interés no devengado, así que este
        // valor (< interés pactado) es el interés real cobrado.
        $interesCronByCredit = $cronAgg->map(fn ($r) => (float) $r->int_cron);

        // Pre-cargar pagos relevantes (no-Gat) por crédito
        $sumNoMoraByCredit = []; // ap = sum(totalgeneral) WHERE NOT MORA y NOT Gat
        $sumInteresByCredit = []; // interés realmente pagado (pagos documento=INTERES)
        $sumMoraByCredit = [];   // mora total
        $maxFechaByCredit = [];  // max fecha de pago (no Gat)
        $idcanRefSet = [];       // ids que han sido referenciados como idcan (para "verSt")

        if (! empty($creditIds)) {
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
                    if (strtoupper($p->documento ?? '') === 'INTERES') {
                        $sumInteresByCredit[$p->credit_id] = ($sumInteresByCredit[$p->credit_id] ?? 0) + (float) $p->monto;
                    }
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
            'capital' => 0,    // sum importe
            'interes_t' => 0,    // sum interes_total (legacy 'interestot')
            'total_a_pag' => 0,    // sum (importe + interes)
            'capital_r' => 0,    // sum rftq
            'interes_g' => 0,    // sum iminte
            'mora' => 0,    // sum resulMor
            'total_pag' => 0,    // sum (rftq + iminte + mora)
            'saldo_capital' => 0,    // sum saldo2
            'mora_x_dia' => 0,    // sum mora x dia
        ];
        $count = 0;

        foreach ($credits as $cr) {
            $count++;
            $importe = (float) $cr->importe;
            $interesPct = (float) $cr->interes;
            $apSum = (float) ($sumNoMoraByCredit[$cr->id] ?? 0); // suma pagos no-MORA
            $moraSum = (float) ($sumMoraByCredit[$cr->id] ?? 0);

            // Interés a mostrar/usar en el saldo: el devengado real del
            // cronograma (condona lo no devengado en cancelaciones anticipadas,
            // así paga menos interés). Fallback al pactado si no hay cronograma.
            $interezPactado = round($importe * ($interesPct / 100), 2);
            $totinteres = isset($interesCronByCredit[$cr->id])
                ? round((float) $interesCronByCredit[$cr->id], 2)
                : $interezPactado;
            $tintere = $totinteres;

            // Interés G. (ganado) = interés REALMENTE pagado (suma de pagos INTERES),
            // no un solo período. Corrige el bug legacy que solo mostraba importe×%
            // de una cuota en créditos de varias cuotas. Capital R. = resto de lo
            // pagado no-mora. Como rftq + iminte siguen sumando apSum, el Total
            // Pagado y el Saldo no cambian respecto al legacy.
            $iminte = min((float) ($sumInteresByCredit[$cr->id] ?? 0), $apSum);
            $rftq = $apSum - $iminte;

            $saldo2 = $importe + $tintere - $rftq - $iminte;

            // Cancelación anticipada: interés no devengado condonado en el
            // cronograma → si el cronograma está saldado, la deuda real es 0.
            if ($saldo2 > 0 && isset($saldoCronByCredit[$cr->id]) && (float) $saldoCronByCredit[$cr->id] <= 0.01) {
                $saldo2 = 0.0;
            }

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

            // Días: fechacan − fechafin (con signo). Positivo = días de mora
            // (canceló después de vencer); negativo = días de adelanto (canceló
            // antes de vencer, pago adelantado). Se muestra crudo, igual que el
            // reporte de cancelados. La mora (mora_s/mxd) sigue guardada solo
            // cuando días > 0, así que un adelanto no genera mora.
            $newdias = 0;
            if ($cr->fecha_cancelacion && $cr->fecha_vencimiento) {
                $newdias = (int) Carbon::parse($cr->fecha_vencimiento)->diffInDays(
                    Carbon::parse($cr->fecha_cancelacion), false
                );
            }
            // Mora por día sobre el interés PACTADO (comportamiento legacy),
            // independiente de la condonación por pago adelantado.
            $intediasdias = round($interezPactado / 30, 2);
            $mxd = $newdias > 0 ? round($newdias * $intediasdias, 2) : 0;

            // Estado label/imagen (legacy: estado=0 → Activado, estado=1 → Desactivado)
            $estadoActivado = (int) $cr->estado === 0;

            $rows[] = [
                'n' => $count,
                'usuario' => $cr->usuario ?? '',  // legacy 'user' field (cajero/usuario que registró)
                'codigo' => $cr->id,
                'estado_activado' => $estadoActivado,
                'cod_ant' => $cr->idcan,
                'f_credito' => $cr->fecha_prestamo ? Carbon::parse($cr->fecha_prestamo)->format('Y-m-d') : '',
                'f_vcto' => $cr->fecha_vencimiento ? Carbon::parse($cr->fecha_vencimiento)->format('Y-m-d') : '',
                'f_pago' => $maxFechaByCredit[$cr->id] ?? '',
                'f_cancelado' => $cr->fecha_cancelacion ? Carbon::parse($cr->fecha_cancelacion)->format('Y-m-d') : '',
                'capital' => $importe,
                'interes_pct' => round($interesPct, 2),
                'interes' => $totinteres,
                'cuotas' => $cr->cuotas,
                'total' => $importe + $totinteres,
                'capital_r' => $rftq,
                'interes_g' => $iminte,
                'mora' => $moraSum,
                'total_pag' => $rftq + $iminte + $moraSum,
                'saldo_capital' => $saldo2,
                'mora_s' => $mxd,                                    // S/ = newdias × intediasdias
                'mxd' => $newdias > 0 ? $intediasdias : 0,        // MxD = intediasdias
                'dias' => $newdias,
                'gat' => (float) $cr->gat,
                'asesor' => $cr->asesor ?? '',
                'bg' => $bg,
                'color' => $color,
            ];

            $totals['capital'] += $importe;
            $totals['interes_t'] += $totinteres;
            $totals['total_a_pag'] += round($importe + $totinteres, 2);
            $totals['capital_r'] += $rftq;
            $totals['interes_g'] += $iminte;
            $totals['mora'] += $moraSum;
            $totals['total_pag'] += $rftq + $iminte + $moraSum;
            $totals['saldo_capital'] += $saldo2;
            $totals['mora_x_dia'] += $mxd;
        }

        // ─── Línea de crédito (informativa): 25% del capital declarado ────
        // Usado = suma del capital (importe) de los créditos con situación Activo.
        $lineaCredito = $this->client->credito; // null si no tiene capital registrado
        $usadoCredito = $credits->where('situacion', 'Activo')->sum(fn ($c) => (float) $c->importe);
        $disponibleCredito = $lineaCredito !== null ? round($lineaCredito - $usadoCredito, 2) : null;

        return view('livewire.clients.show', [
            'rows' => $rows,
            'totals' => $totals,
            'lineaCredito' => $lineaCredito,
            'usadoCredito' => $usadoCredito,
            'disponibleCredito' => $disponibleCredito,
        ]);
    }
}
