<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Schedule extends Component
{
    public Credit $credit;

    public function mount(int $id)
    {
        $this->credit = Credit::with(['client.asesor:id,name'])->findOrFail($id);
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
        $tipoPlanilla = (int) $this->credit->tipo_planilla;
        $n = $installments->count();
        $idx = 0;
        $capacidad = $n > 0
            ? (float) $installments[0]->importe_cuota + (float) $installments[0]->importe_interes
            : 0.0;

        foreach ($eventos as $e => $p) {
            // Atraso del evento contra la cuota impaga más antigua en ese
            // momento (mismas reglas del motor de mora: días calendario para
            // mensual, hábiles sin sáb/dom para el resto).
            $eventos[$e]['dias_atraso'] = 0;
            if ($idx < $n && $p['fecha'] !== '' && $installments[$idx]->fecha_vencimiento) {
                $venc = Carbon::parse($installments[$idx]->fecha_vencimiento);
                $fpago = Carbon::parse($p['fecha']);
                $diff = (int) floor($venc->diffInDays($fpago, false));
                if ($diff > 0) {
                    if ($tipoPlanilla === 3) {
                        $eventos[$e]['dias_atraso'] = $diff;
                    } else {
                        $cur = $venc->copy();
                        for ($i = 1; $i <= $diff; $i++) {
                            $cur->addDay();
                            if (! in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                                $eventos[$e]['dias_atraso']++;
                            }
                        }
                    }
                }
            }

            $rem = $p['monto'];
            $eventos[$e]['ultima_cuota'] = null; // idx de la última cuota que tocó
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
                    $eventos[$e]['ultima_cuota'] = $idx;
                    $rem -= $take;
                    $capacidad -= $take;
                }
                if ($capacidad <= 0.005) {
                    $idx++;
                    $capacidad = $idx < $n
                        ? (float) $installments[$idx]->importe_cuota + (float) $installments[$idx]->importe_interes
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

        // ─── Mora exonerada por evento ───────────────────────────────────
        // Mora generada por el atraso de cada pago pero NO cobrada (el switch
        // "Reserva Mora" la deja como deuda viva en mora_acumulada). Si las
        // filas registradas en dias_mora cuadran 1:1 con los eventos con
        // atraso, se usan sus días reales (con descuentos de gracia); si no,
        // se usan los días calculados. Informativa: NO afecta los totales.
        $moraRate = (float) ($tipoPlanilla === 1 ? $this->credit->mora2 : $this->credit->mora1);
        $diasMoraRows = DB::table('dias_mora')->where('credit_id', $creditId)
            ->orderBy('id')->get()->values();
        $eventosAtraso = array_values(array_filter($eventos, fn ($e) => $e['dias_atraso'] > 0));
        $usaRegistrado = $diasMoraRows->count() > 0 && $diasMoraRows->count() === count($eventosAtraso);

        $exonCuota = []; // idx cuota => ['monto', 'dias'] exonerado
        $exonResto = []; // [Y-m-d] => ['monto', 'dias'] (evento que cayó todo en OTROS)
        $moraPagadaLibre = $payMora; // se consume para restar lo que sí se cobró
        foreach ($eventosAtraso as $i => $e) {
            $dias = $usaRegistrado
                ? max(0, (int) $diasMoraRows[$i]->dias - (int) $diasMoraRows[$i]->dias_descontados)
                : $e['dias_atraso'];
            $exon = $dias * $moraRate;
            if ($exon <= 0) {
                continue;
            }
            // Restar la mora efectivamente cobrada ese día (una sola vez)
            if ($e['fecha'] !== '' && ($moraPagadaLibre[$e['fecha']] ?? 0) > 0) {
                $usado = min($exon, $moraPagadaLibre[$e['fecha']]);
                $exon -= $usado;
                $moraPagadaLibre[$e['fecha']] -= $usado;
            }
            if ($exon <= 0.005) {
                continue;
            }
            if ($e['ultima_cuota'] !== null) {
                $k = $e['ultima_cuota'];
                $exonCuota[$k]['monto'] = ($exonCuota[$k]['monto'] ?? 0) + $exon;
                $exonCuota[$k]['dias'] = ($exonCuota[$k]['dias'] ?? 0) + $dias;
            } elseif ($e['fecha'] !== '') {
                $exonResto[$e['fecha']]['monto'] = ($exonResto[$e['fecha']]['monto'] ?? 0) + $exon;
                $exonResto[$e['fecha']]['dias'] = ($exonResto[$e['fecha']]['dias'] ?? 0) + $dias;
            }
        }

        // ─── Filas del cronograma ────────────────────────────────────────
        $rows = [];
        $totals = ['capital' => 0, 'interes' => 0, 'total' => 0, 'mora' => 0, 'pagado' => 0, 'mora_exon' => 0, 'mora_exon_dias' => 0];
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

            $moraExon = round($exonCuota[$k]['monto'] ?? 0, 2);
            $moraExonDias = (int) ($exonCuota[$k]['dias'] ?? 0);

            $totals['capital'] += $cap;
            $totals['interes'] += $int;
            $totals['total'] += $cap + $int;
            $totals['mora'] += $mora;
            $totals['pagado'] += $pagado;
            $totals['mora_exon'] += $moraExon;
            $totals['mora_exon_dias'] += $moraExonDias;

            $venc = $ins->fecha_vencimiento ? Carbon::parse($ins->fecha_vencimiento)->format('Y-m-d') : '';

            $rows[] = [
                'tipo' => 'cuota',
                'n' => $tt,
                'periodo' => $venc,
                'capital' => $cap,
                'interes' => $int,
                'total' => $cap + $int,
                'mora' => $mora,
                'mora_exon' => $moraExon,
                'mora_exon_dias' => $moraExonDias,
                'pagado' => $pagado,
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
            $moraExon = round($exonResto[$f]['monto'] ?? 0, 2);
            $moraExonDias = (int) ($exonResto[$f]['dias'] ?? 0);
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
                'total' => 0,
                'mora' => $mora,
                'mora_exon' => $moraExon,
                'mora_exon_dias' => $moraExonDias,
                'pagado' => (float) $info['monto'],
                'hora' => $info['hora'],
                'fecha_pago' => $f,
                'color' => '',
            ];
        }

        // Saldo final
        $saldo = $totals['capital'] + $totals['interes'] - $totals['pagado'] - $sumOtros;
        $totalGeneral = $totals['pagado'] + $sumOtros + $totals['mora'] + $sumOtrosMora;

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
        ]);
    }
}
