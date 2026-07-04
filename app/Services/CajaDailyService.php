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
                // Mora acumulada cobrada al cancelar (documento 'MORA ACUM.'):
                // desglose para Caja 1. 'mora' sigue siendo el TOTAL (vigente + acum).
                $moraAcum = (float) $pays->where('tipo', 'MORA')->where('documento', 'MORA ACUM.')->sum('monto');

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
                    'mora_acum' => $moraAcum,
                    'asesor' => $asesor,
                    'tipo_planilla' => $tipoplani,
                ];
            }
            $result[$date] = $ingresos;
        }

        return $result;
    }

    /**
     * Tasas de interés cableadas en el legacy data_capineto.php. El Capital Neto
     * solo cuenta créditos cuya tasa esté en este set (las demás se excluyen).
     *
     * @var list<string>
     */
    private const CAPITAL_NETO_RATES = [
        '0.00', '0.01', '3.00', '4.00', '5.00', '5.20', '6.00', '6.50', '7.00',
        '8.00', '9.00', '10.00', '12.00', '13.00', '14.00', '15.00', '16.00',
        '17.00', '18.00', '19.00', '20.00', '24.00', '30.00', '36.00', '54.00', '72.00',
    ];

    /**
     * Capital Neto (cartera) por día del mes — réplica del legacy data_capineto:
     * suma de importe de créditos con tasa en CAPITAL_NETO_RATES, activos a cada
     * fecha (entraron y NO estaban cancelados ese día). Una sola lectura de credits
     * y se evalúa cada día en memoria (eficiente para el reporte mensual).
     *
     * @return array<string,float> 'Y-m-d' => importe
     */
    public function capitalNetoPorDia(int $year, int $month, string $endLimit): array
    {
        $relevant = $this->loadCapitalNetoCredits();

        $result = [];
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $endLimit) {
                break;
            }
            $result[$date] = $this->sumCapitalNetoAt($relevant, $date);
        }

        return $result;
    }

    /** Capital Neto de una sola fecha (usado por el comando reports:snapshot-capital-neto). */
    public function capitalNetoEnFecha(string $date): float
    {
        return $this->sumCapitalNetoAt($this->loadCapitalNetoCredits(), $date);
    }

    /**
     * Carga (1 query) los créditos con tasa válida, ya pre-procesados para evaluar
     * su estado "a la fecha".
     *
     * @return list<array{importe:float, created:?string, cancelled:bool, canDate:?string}>
     */
    private function loadCapitalNetoCredits(): array
    {
        $rates = array_flip(self::CAPITAL_NETO_RATES);
        $relevant = [];

        DB::table('credits')
            ->select('importe', 'interes', 'situacion', 'fecha_cancelacion', 'fecha_prestamo', 'fecha_actualizacion')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use ($rates, &$relevant) {
                foreach ($rows as $r) {
                    $rate = number_format((float) $r->interes, 2, '.', '');
                    if (! isset($rates[$rate])) {
                        continue;
                    }
                    $relevant[] = [
                        'importe' => (float) $r->importe,
                        'created' => $this->validCapitalDate($r->fecha_actualizacion) ?? $this->validCapitalDate($r->fecha_prestamo),
                        'cancelled' => $r->situacion === 'Cancelado',
                        'canDate' => $r->situacion === 'Cancelado' ? $this->validCapitalDate($r->fecha_cancelacion) : null,
                    ];
                }
            });

        return $relevant;
    }

    /** @param  list<array{importe:float, created:?string, cancelled:bool, canDate:?string}>  $credits */
    private function sumCapitalNetoAt(array $credits, string $date): float
    {
        $sum = 0.0;
        foreach ($credits as $c) {
            // No existía aún en la fecha.
            if ($c['created'] !== null && $c['created'] > $date) {
                continue;
            }
            // Cancelado en la fecha (o cancelación antigua sin fecha válida = ya inactivo).
            if ($c['cancelled'] && ($c['canDate'] === null || $c['canDate'] <= $date)) {
                continue;
            }
            $sum += $c['importe'];
        }

        return round($sum, 2);
    }

    private function validCapitalDate(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }
        $d = substr($value, 0, 10);

        return $d > '1900-01-01' ? $d : null;
    }
}
