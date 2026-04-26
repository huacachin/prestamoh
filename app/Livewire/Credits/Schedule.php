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
        $tipoPlanilla = (int) $this->credit->tipo_planilla;

        // Cuotas del cronograma
        $installments = DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->orderBy('num_cuota')
            ->get();

        // Pre-cargar pagos no-MORA y MORA por fecha
        $payNoMora = []; // [Y-m-d] = ['monto' => sum, 'tipo' => max(hora)]
        $payMora   = []; // [Y-m-d] = sum
        $allPays   = DB::table('payments')
            ->where('credit_id', $creditId)
            ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
            ->select('fecha', 'hora', 'monto', 'documento')
            ->get();

        foreach ($allPays as $p) {
            if (!$p->fecha) continue;
            $f = Carbon::parse($p->fecha)->format('Y-m-d');
            $isMora = (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA');
            if ($isMora) {
                $payMora[$f] = ($payMora[$f] ?? 0) + (float) $p->monto;
            } else {
                if (!isset($payNoMora[$f])) {
                    $payNoMora[$f] = ['monto' => 0, 'hora' => null];
                }
                $payNoMora[$f]['monto'] += (float) $p->monto;
                if ($p->hora && (!$payNoMora[$f]['hora'] || $payNoMora[$f]['hora'] < $p->hora)) {
                    $payNoMora[$f]['hora'] = $p->hora;
                }
            }
        }

        // Procesar filas del cronograma
        $rows = [];
        $totals = ['capital' => 0, 'interes' => 0, 'total' => 0, 'mora' => 0, 'pagado' => 0];
        $tt = 0;
        $countCuotas = count($installments);

        foreach ($installments as $idx => $ins) {
            $tt++;
            $fechaPago = $ins->fecha_pago ? Carbon::parse($ins->fecha_pago)->format('Y-m-d') : '';
            $dow = $fechaPago ? Carbon::parse($fechaPago)->dayOfWeek : null;
            $color = '';
            if ($dow === Carbon::SUNDAY) $color = 'red';
            elseif ($dow === Carbon::SATURDAY) $color = 'green';

            $cap = (float) $ins->importe_cuota;
            $int = (float) $ins->importe_interes;
            $mora = (float) ($payMora[$fechaPago] ?? 0);
            $pagInfo = $payNoMora[$fechaPago] ?? ['monto' => 0, 'hora' => null];
            $pagado = (float) $pagInfo['monto'];
            $hora = $pagInfo['hora'];

            $totals['capital'] += $cap;
            $totals['interes'] += $int;
            $totals['total']   += $cap + $int;
            $totals['mora']    += $mora;
            $totals['pagado']  += $pagado;

            $rows[] = [
                'tipo'       => 'cuota',
                'n'          => $tt,
                'periodo'    => $fechaPago,
                'capital'    => $cap,
                'interes'    => $int,
                'total'      => $cap + $int,
                'mora'       => $mora,
                'pagado'     => $pagado,
                'hora'       => $hora,
                'fecha_pago' => $pagado >= 0.01 ? $fechaPago : '',
                'color'      => $color,
            ];
        }

        // ─── Pagos OTROS (fuera del cronograma) ──────────────────────────
        $otrosRows = [];
        $sumOtros = 0;
        $sumOtrosMora = 0;

        $minIns = $installments->pluck('fecha_pago')->filter()->min();
        $maxIns = $installments->pluck('fecha_pago')->filter()->max();
        $minInsF = $minIns ? Carbon::parse($minIns)->format('Y-m-d') : null;
        $maxInsF = $maxIns ? Carbon::parse($maxIns)->format('Y-m-d') : null;

        if ($tipoPlanilla === 4) {
            // Diario: pagos antes de m1 (>= 2019-01-01) y pagos después de m2
            $candidatos = [];
            foreach ($payNoMora as $f => $info) {
                if ($f >= '2019-01-01' && $minInsF && $f < $minInsF) {
                    $candidatos[$f] = $info;
                } elseif ($maxInsF && $f > $maxInsF && $f <= '3000-12-31') {
                    $candidatos[$f] = $info;
                }
            }
            ksort($candidatos);
            foreach ($candidatos as $f => $info) {
                $tt++;
                $mora = (float) ($payMora[$f] ?? 0);
                $sumOtros += (float) $info['monto'];
                $sumOtrosMora += $mora;
                $otrosRows[] = [
                    'tipo'       => 'otro',
                    'n'          => $tt,
                    'periodo'    => '',
                    'capital'    => 0,
                    'interes'    => 0,
                    'total'      => 0,
                    'mora'       => $mora,
                    'pagado'     => (float) $info['monto'],
                    'hora'       => $info['hora'],
                    'fecha_pago' => $f,
                    'color'      => '',
                ];
            }
        } else {
            // Semanal/Mensual: cualquier pago en fecha NO en cronograma
            $insDates = [];
            foreach ($installments as $ins) {
                if ($ins->fecha_pago) {
                    $insDates[Carbon::parse($ins->fecha_pago)->format('Y-m-d')] = true;
                }
            }
            $candidatos = [];
            foreach ($payNoMora as $f => $info) {
                if (!isset($insDates[$f])) {
                    $candidatos[$f] = $info;
                }
            }
            ksort($candidatos);
            foreach ($candidatos as $f => $info) {
                $tt++;
                $mora = (float) ($payMora[$f] ?? 0);
                $sumOtros += (float) $info['monto'];
                $sumOtrosMora += $mora;
                $otrosRows[] = [
                    'tipo'       => 'otro',
                    'n'          => $tt,
                    'periodo'    => '',
                    'capital'    => 0,
                    'interes'    => 0,
                    'total'      => 0,
                    'mora'       => $mora,
                    'pagado'     => (float) $info['monto'],
                    'hora'       => $info['hora'],
                    'fecha_pago' => $f,
                    'color'      => '',
                ];
            }
        }

        // Saldo final
        $saldo = $totals['capital'] + $totals['interes'] - $totals['pagado'] - $sumOtros;
        $totalGeneral = $totals['pagado'] + $sumOtros + $totals['mora'] + $sumOtrosMora;

        return view('livewire.credits.schedule', [
            'rows'         => $rows,
            'otrosRows'    => $otrosRows,
            'totals'       => $totals,
            'sumOtros'     => $sumOtros,
            'sumOtrosMora' => $sumOtrosMora,
            'saldo'        => $saldo,
            'totalGeneral' => $totalGeneral,
        ]);
    }
}
