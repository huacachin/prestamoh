<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Support\MoraPagada;
use App\Support\RecibosCuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Schedule extends Component
{
    public Credit $credit;

    public function mount(int $id)
    {
        $this->credit = Credit::with(['client.asesor:id,name'])->findOrFail($id);
        // Analista (scope-propio): solo SUS créditos
        if ((auth()->user()?->can('clientes.scope-propio') ?? false)
            && $this->credit?->client?->asesor_id !== auth()->id()) {
            abort(403, 'Solo puedes ver tus propios créditos.');
        }

    }

    public function render()
    {
        $creditId = $this->credit->id;

        // Cuotas del cronograma
        $installments = DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->orderBy('num_cuota')
            ->get()->values();

        // Pagos reales en orden cronológico (excluye 'Gat' como el legacy).
        // No se cruza por fecha contra installments.fecha_pago: ese campo
        // guarda el VENCIMIENTO (todas las cuotas pagadas lo tienen igual a
        // fecha_vencimiento), así que solo coincide cuando el cliente pagó
        // puntual y el resto caía mal clasificado como pago "OTROS".
        $pays = DB::table('payments')
            ->where('credit_id', $creditId)
            ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
            ->select('fecha', 'hora', 'monto', 'documento')
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get();

        // Pagos capital/interés agrupados por EVENTO (misma fecha+hora = un
        // pago registrado en ventanilla); la mora se agrupa por fecha.
        $eventos = []; // ['fecha','hora','monto'] en orden cronológico
        $payMora = []; // [Y-m-d] = sum pagos MORA
        foreach ($pays as $p) {
            $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : '';
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA') {
                if ($f) {
                    $payMora[$f] = ($payMora[$f] ?? 0) + (float) $p->monto;
                }
            } else {
                $last = count($eventos) - 1;
                if ($last >= 0 && $eventos[$last]['fecha'] === $f && $eventos[$last]['hora'] === $p->hora) {
                    $eventos[$last]['monto'] += (float) $p->monto;
                } else {
                    $eventos[] = ['fecha' => $f, 'hora' => $p->hora, 'monto' => (float) $p->monto];
                }
            }
        }

        // ─── Distribución FIFO: eventos → cuotas en orden (mismo criterio que
        // el motor de pagos: cuota más antigua primero hasta cubrir cap+int).
        // Lo que sobra tras llenar todo el cronograma es pago "OTROS".
        $alloc = [];      // idx cuota => ['monto', 'fecha', 'hora'] (fecha/hora del último pago que la tocó)
        $moraCuota = [];  // idx cuota => fechas de pago asociadas (para colgar la mora del día)
        $restos = [];     // [Y-m-d] => ['monto', 'hora'] sobras fuera del cronograma
        $n = $installments->count();
        $idx = 0;
        $capacidad = $n > 0
            ? (float) $installments[0]->importe_cuota + (float) $installments[0]->importe_interes + (float) $installments[0]->importe_excedente
            : 0.0;

        foreach ($eventos as $p) {
            $rem = $p['monto'];
            while ($rem > 0.005 && $idx < $n) {
                $take = min($rem, $capacidad);
                if ($take > 0) {
                    if (! isset($alloc[$idx])) {
                        $alloc[$idx] = ['monto' => 0.0, 'fecha' => null, 'hora' => null];
                    }
                    $alloc[$idx]['monto'] += $take;
                    $alloc[$idx]['fecha'] = $p['fecha'];
                    $alloc[$idx]['hora'] = $p['hora'];
                    if ($p['fecha']) {
                        $moraCuota[$idx][$p['fecha']] = true;
                    }
                    $rem -= $take;
                    $capacidad -= $take;
                }
                if ($capacidad <= 0.005) {
                    $idx++;
                    $capacidad = $idx < $n
                        ? (float) $installments[$idx]->importe_cuota + (float) $installments[$idx]->importe_interes + (float) $installments[$idx]->importe_excedente
                        : 0.0;
                }
            }
            if ($rem > 0.005) {
                $f = $p['fecha'];
                if (! isset($restos[$f])) {
                    $restos[$f] = ['monto' => 0.0, 'hora' => null];
                }
                $restos[$f]['monto'] += $rem;
                $restos[$f]['hora'] = $p['hora'];
            }
        }

        // Mora diaria: 5% de la cuota ÷7 semanal / ÷30 mensual (diarios: mora1
        // histórico) — regla centralizada en Credit::moraDiaria().

        // ─── Filas del cronograma ────────────────────────────────────────
        $rows = [];
        $totals = ['capital' => 0, 'interes' => 0, 'excedente' => 0, 'total' => 0, 'mora' => 0, 'pagado' => 0, 'mora_exon' => 0, 'mora_exon_dias' => 0];
        $moraUsada = []; // fechas de mora ya colgadas a una cuota
        $tt = 0;

        foreach ($installments as $k => $ins) {
            $tt++;
            $a = $alloc[$k] ?? null;
            $pagado = $a ? round($a['monto'], 2) : 0.0;
            $fechaPago = ($a && $pagado >= 0.01) ? ($a['fecha'] ?? '') : '';
            $hora = $fechaPago !== '' ? $a['hora'] : null;

            // Mora pagada en los días en que esta cuota recibió pagos
            $mora = 0.0;
            foreach (array_keys($moraCuota[$k] ?? []) as $f) {
                if (! isset($moraUsada[$f]) && isset($payMora[$f])) {
                    $mora += (float) $payMora[$f];
                    $moraUsada[$f] = true;
                }
            }

            $dow = $fechaPago !== '' ? Carbon::parse($fechaPago)->dayOfWeek : null;
            $color = '';
            if ($dow === Carbon::SUNDAY) {
                $color = 'red';
            } elseif ($dow === Carbon::SATURDAY) {
                $color = 'green';
            }

            $cap = (float) $ins->importe_cuota;
            $int = (float) $ins->importe_interes;
            $exc = (float) $ins->importe_excedente;

            // Mora exonerada teórica POR CUOTA: días calendario de atraso
            // entre su vencimiento y su fecha de pago real (todos los tipos,
            // regla única desde 02/09) × tarifa, menos la mora que sí se
            // cobró en la cuota. Informativa: no afecta los totales.
            $moraExon = 0.0;
            $moraExonDias = 0;
            $moraRate = $this->credit->moraDiaria((float) $ins->importe_cuota + (float) $ins->importe_interes + (float) $ins->importe_excedente);
            if ($fechaPago !== '' && $ins->fecha_vencimiento && $moraRate > 0) {
                $vencC = Carbon::parse($ins->fecha_vencimiento);
                $diff = (int) floor($vencC->diffInDays(Carbon::parse($fechaPago), false));
                if ($diff > 0) {
                    $moraExonDias = $diff;
                    $moraExon = round(max(0, $moraExonDias * $moraRate - $mora), 2);
                    if ($moraExon <= 0) {
                        $moraExonDias = 0;
                    }
                }
            }

            $totals['capital'] += $cap;
            $totals['interes'] += $int;
            $totals['excedente'] += $exc;
            $totals['total'] += $cap + $int + $exc;
            $totals['mora'] += $mora;
            $totals['pagado'] += $pagado;
            $totals['mora_exon'] += $moraExon;
            $totals['mora_exon_dias'] += $moraExonDias;

            $venc = $ins->fecha_vencimiento ? Carbon::parse($ins->fecha_vencimiento)->format('Y-m-d') : '';

            $rows[] = [
                'tipo' => 'cuota',
                'installment_id' => (int) $ins->id,
                'n' => $tt,
                'periodo' => $venc,
                'capital' => $cap,
                'interes' => $int,
                'excedente' => $exc,
                'total' => $cap + $int + $exc,
                'mora' => $mora,
                'mora_exon' => $moraExon,
                'mora_exon_dias' => $moraExonDias,
                'pagado' => $pagado,
                // Vista cronograma (homologada con /payments/create): lo
                // aplicado por tipo, el saldo y el estado real de la cuota.
                'pagado_cap' => (float) $ins->importe_aplicado,
                'pagado_int' => (float) $ins->interes_aplicado,
                'pagado_exc' => (float) $ins->excedente_aplicado,
                'saldo' => round($cap + $int + $exc
                    - (float) $ins->importe_aplicado - (float) $ins->interes_aplicado - (float) $ins->excedente_aplicado, 2),
                'flag_pagado' => (bool) $ins->pagado,
                'hora' => $hora,
                'fecha_pago' => $fechaPago,
                'color' => $color,
                'tarde' => $fechaPago !== '' && $venc !== '' && $fechaPago > $venc,
            ];
        }

        // ─── Pagos OTROS: sobras del FIFO + mora suelta sin cuota ────────
        $otrosRows = [];
        $sumOtros = 0;
        $sumOtrosMora = 0;

        foreach ($payMora as $f => $monto) {
            if (! isset($moraUsada[$f]) && ! isset($restos[$f])) {
                $restos[$f] = ['monto' => 0.0, 'hora' => null];
            }
        }
        $sumOtrosExon = 0;
        $sumOtrosExonDias = 0;
        ksort($restos);
        foreach ($restos as $f => $info) {
            $tt++;
            $mora = isset($moraUsada[$f]) ? 0.0 : (float) ($payMora[$f] ?? 0);
            $moraUsada[$f] = true;
            // Sin cuota de referencia no hay vencimiento contra el cual medir atraso
            $moraExon = 0.0;
            $moraExonDias = 0;
            $sumOtros += (float) $info['monto'];
            $sumOtrosMora += $mora;
            $sumOtrosExon += $moraExon;
            $sumOtrosExonDias += $moraExonDias;
            $otrosRows[] = [
                'tipo' => 'otro',
                'n' => $tt,
                'periodo' => '',
                'capital' => 0,
                'interes' => 0,
                'excedente' => 0,
                'total' => 0,
                'mora' => $mora,
                'mora_exon' => $moraExon,
                'mora_exon_dias' => $moraExonDias,
                'pagado' => (float) $info['monto'],
                'pagado_cap' => 0.0,
                'pagado_int' => 0.0,
                'pagado_exc' => 0.0,
                'saldo' => 0.0,
                'flag_pagado' => null,
                'hora' => $info['hora'],
                'fecha_pago' => $f,
                'color' => '',
            ];
        }

        // Saldo final
        $saldo = $totals['capital'] + $totals['interes'] + $totals['excedente'] - $totals['pagado'] - $sumOtros;
        $totalGeneral = $totals['pagado'] + $sumOtros + $totals['mora'] + $sumOtrosMora;

        // Capital pendiente total: misma fórmula que /payments/create
        // (SUM importe_cuota − SUM importe_aplicado)
        $capPendienteTotal = round($installments->sum('importe_cuota') - $installments->sum('importe_aplicado'), 2);

        return view('livewire.credits.schedule', [
            'rows' => $rows,
            'otrosRows' => $otrosRows,
            'totals' => $totals,
            'sumOtros' => $sumOtros,
            'sumOtrosMora' => $sumOtrosMora,
            'sumOtrosExon' => $sumOtrosExon,
            'sumOtrosExonDias' => $sumOtrosExonDias,
            'saldo' => $saldo,
            'totalGeneral' => $totalGeneral,
            'capPendienteTotal' => $capPendienteTotal,
            // Homologado con /payments/create: recibo por cuota (modal) y
            // desglose de la mora pagada para los tooltips de la columna Mora
            'recibos' => RecibosCuota::porCuota($this->credit),
            'moraPagadaCuotas' => MoraPagada::porCuota($this->credit),
        ]);
    }
}
