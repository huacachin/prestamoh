<?php

namespace App\Services;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica diaria de caja compartida entre los reportes cash-general-1 y
 * cash-statistics, para garantizar que ambos cuadren con el legacy (reporte1a).
 */
class CajaDailyService
{
    /**
     * Ingresos del mes agrupados por día y crédito, con la lógica del legacy
     * reporte1a (incluye la rama de refinanciamiento cancelado el mismo día).
     *
     * @return array<string, array<int, array>> 'Y-m-d' => [ ingreso, ... ]
     */
    public function ingresosPorDia(int $year, int $month, ?int $tipoFilter, string $endLimit): array
    {
        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');

        $payQuery = Payment::query()
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->with(['credit:id,client_id,tipo_planilla,refinanciado,fecha_cancelacion,importe,interes,cuotas,cod_rem',
                'credit.client:id,nombre,apellido_pat,apellido_mat,asesor_id',
                'credit.client.asesor:id,name,username']);

        if ($tipoFilter !== null) {
            $payQuery->whereHas('credit', fn ($q) => $q->where('tipo_planilla', $tipoFilter));
        }

        $allPayments = $payQuery->get();
        $paymentsByDate = $allPayments->groupBy(fn ($p) => $p->fecha->format('Y-m-d'));

        // Pagos previos para refis cancelados dentro del mes (settlement).
        $refiCancelIds = $allPayments->pluck('credit')
            ->filter(fn ($c) => $c && $c->refinanciado &&
                $c->fecha_cancelacion?->format('Y-m-d') >= $startMonth &&
                $c->fecha_cancelacion?->format('Y-m-d') <= $endLimit)
            ->pluck('id')->unique()->values();

        $pagosPreviosPorCredito = [];
        if ($refiCancelIds->isNotEmpty()) {
            $rows = DB::table('payments as p')
                ->join('credits as c', 'p.credit_id', '=', 'c.id')
                ->whereIn('p.credit_id', $refiCancelIds)
                ->whereColumn('p.fecha', '<', 'c.fecha_cancelacion')
                ->groupBy('p.credit_id')
                ->select('p.credit_id', DB::raw('SUM(p.monto) as total'))
                ->get();
            foreach ($rows as $r) {
                $pagosPreviosPorCredito[$r->credit_id] = (float) $r->total;
            }
        }

        $tcMarks = [1 => 'S.', 3 => 'M.', 4 => 'D.'];
        $result = [];

        foreach ($paymentsByDate as $date => $dayPayments) {
            $ingresos = [];
            foreach ($dayPayments->groupBy('credit_id') as $cid => $pays) {
                $credit = $pays->first()->credit;
                if (! $credit) {
                    continue;
                }

                $tipoplani = (int) $credit->tipo_planilla;
                $isRefi = (bool) $credit->refinanciado;
                $fechaCan = $credit->fecha_cancelacion?->format('Y-m-d');

                $totalSinMora = (float) $pays->whereIn('tipo', ['CAPITAL', 'INTERES'])->sum('monto');
                $totalConMora = (float) $pays->sum('monto');
                $sumInteresPagado = (float) $pays->where('tipo', 'INTERES')->sum('monto');
                $sumCapitalPagado = (float) $pays->where('tipo', 'CAPITAL')->sum('monto');
                $mora = (float) $pays->where('tipo', 'MORA')->sum('monto');

                if ($isRefi && $fechaCan === $date) {
                    $interesTotal = in_array($tipoplani, [1, 4])
                        ? round(($credit->importe * $credit->interes) / 100, 2)
                        : round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;

                    $total = (float) $credit->importe + $interesTotal;
                    $pagosPrevios = $pagosPreviosPorCredito[$cid] ?? 0.0;

                    if ($pagosPrevios > 0) {
                        $capital = (float) $credit->importe + $interesTotal - $pagosPrevios - $totalConMora;
                        $total -= $pagosPrevios;
                        $interes = max(0, $interesTotal - $pagosPrevios);
                    } else {
                        $interes = $interesTotal;
                        $capital = (float) $credit->importe;
                    }
                } elseif ($tipoplani === 4) {
                    $total = $totalSinMora;
                    $interes = $sumInteresPagado;
                    $capital = $totalSinMora - $sumInteresPagado;
                } else {
                    $total = $totalSinMora;
                    $interes = $sumInteresPagado;
                    $capital = $sumCapitalPagado;
                }

                $cli = $credit->client;
                $cliName = $cli ? trim($cli->apellido_pat.' '.$cli->apellido_mat.' '.$cli->nombre) : 'N/A';
                $asesor = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';

                $marcador = $tcMarks[$tipoplani] ?? '';
                if ($fechaCan === $date) {
                    $marcador .= ($credit->cod_rem ?? '').'.CANCEL';
                }

                $ingresos[] = [
                    'credit_id' => $cid,
                    'cliente' => $cliName,
                    'detalle' => $marcador,
                    'nro_cuotas' => $pays->sortBy('id')->first()?->detalle ?? '',
                    'total' => $total,
                    'capital' => $capital,
                    'interes' => $interes,
                    'mora' => $mora,
                    'asesor' => $asesor,
                    'tipo_planilla' => $tipoplani,
                ];
            }
            $result[$date] = $ingresos;
        }

        return $result;
    }
}
