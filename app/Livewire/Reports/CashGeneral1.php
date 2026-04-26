<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CashGeneral1 extends Component
{
    public $selemes;
    public $selecano;
    public $seletipl = '0000';

    public function mount()
    {
        $this->selemes  = date('m');
        $this->selecano = date('Y');
    }

    public function search() {}

    public function render()
    {
        $year   = (int) $this->selecano;
        $month  = (int) $this->selemes;
        $tipoFilter = ($this->seletipl !== '' && $this->seletipl !== '0000') ? (int) $this->seletipl : null;

        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
        $endMonth   = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today      = Carbon::today()->format('Y-m-d');
        $endLimit   = min($endMonth, $today);

        // ─── PRECARGAR TODOS LOS PAGOS DEL MES (1 query) ───────────────
        $payQuery = Payment::query()
            ->where('fecha', '>=', $startMonth)
            ->where('fecha', '<=', $endLimit)
            ->with(['credit:id,client_id,tipo_planilla,refinanciado,fecha_cancelacion,importe,interes,cuotas',
                    'credit.client:id,nombre,apellido_pat,apellido_mat,asesor_id',
                    'credit.client.asesor:id,name,username']);

        if ($tipoFilter !== null) {
            $payQuery->whereHas('credit', fn ($q) => $q->where('tipo_planilla', $tipoFilter));
        }

        $allPayments = $payQuery->get();
        $paymentsByDate = $allPayments->groupBy(fn ($p) => $p->fecha->format('Y-m-d'));

        // ─── PRECARGAR TODOS LOS CREDITOS DEL MES (egresos, 1 query) ───
        $credQuery = Credit::query()
            ->where('fecha_actualizacion', '>=', $startMonth)
            ->where('fecha_actualizacion', '<=', $endLimit)
            ->with(['client:id,nombre,apellido_pat,apellido_mat,asesor_id',
                    'client.asesor:id,name,username',
                    'user:id,name,username']);

        if ($tipoFilter !== null) {
            $credQuery->where('tipo_planilla', $tipoFilter);
        }

        $allCredits = $credQuery->get();
        $creditsByDate = $allCredits->groupBy(fn ($c) => $c->fecha_actualizacion->format('Y-m-d'));

        // ─── PRECALCULAR PAGOS PREVIOS PARA REFI CANCELADOS DEL MES ────
        // Solo necesitamos para créditos refinanciados que se cancelan en este mes
        $refiCancelIds = $allPayments
            ->pluck('credit')
            ->filter(fn ($c) => $c && $c->refinanciado &&
                $c->fecha_cancelacion?->format('Y-m-d') >= $startMonth &&
                $c->fecha_cancelacion?->format('Y-m-d') <= $endLimit)
            ->pluck('id')
            ->unique()
            ->values();

        $pagosPreviosPorCredito = [];
        if ($refiCancelIds->isNotEmpty()) {
            // Una sola query: sum payments por credit_id antes de la fecha_cancelacion
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

        // ─── PROCESAR DÍA POR DÍA EN MEMORIA ───────────────────────────
        $days = [];
        $Tcpi = 0; $Tcpi2 = 0; $Tint = 0; $Tmor4 = 0; $toff = 0; $toff2 = 0;

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $today) break;

            // INGRESOS del día
            $dayPayments = $paymentsByDate->get($date, collect());
            $byCredit = $dayPayments->groupBy('credit_id');

            $ingresos = [];
            foreach ($byCredit as $cid => $pays) {
                $credit = $pays->first()->credit;
                if (!$credit) continue;

                $tipoplani = (int) $credit->tipo_planilla;
                $isRefi = (bool) $credit->refinanciado;
                $fechaCan = $credit->fecha_cancelacion?->format('Y-m-d');

                $totalSinMora     = (float) $pays->whereIn('tipo', ['CAPITAL', 'INTERES'])->sum('monto');
                $totalConMora     = (float) $pays->sum('monto');
                $sumInteresPagado = (float) $pays->where('tipo', 'INTERES')->sum('monto');
                $sumCapitalPagado = (float) $pays->where('tipo', 'CAPITAL')->sum('monto');
                $mora             = (float) $pays->where('tipo', 'MORA')->sum('monto');

                if ($isRefi && $fechaCan === $date) {
                    // RAMA REFI cancelado este mismo día (legacy)
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
                    $total   = $totalSinMora;
                    $interes = $sumInteresPagado;
                    $capital = $totalSinMora - $sumInteresPagado;
                } else {
                    $total   = $totalSinMora;
                    $interes = $sumInteresPagado;
                    $capital = $sumCapitalPagado;
                }

                $cli = $credit->client;
                $cliName = $cli ? trim($cli->apellido_pat . ' ' . $cli->apellido_mat . ' ' . $cli->nombre) : 'N/A';
                $asesor  = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';
                $nroCuotas = $pays->pluck('installment_id')->filter()->unique()->count();
                $detalle = $pays->first()?->detalle ?? '';

                $ingresos[] = [
                    'credit_id'     => $cid,
                    'cliente'       => $cliName,
                    'detalle'       => $detalle,
                    'nro_cuotas'    => $nroCuotas,
                    'total'         => $total,
                    'capital'       => $capital,
                    'interes'       => $interes,
                    'mora'          => $mora,
                    'asesor'        => $asesor,
                    'tipo_planilla' => $tipoplani,
                ];
            }

            // EGRESOS del día
            $dayCredits = $creditsByDate->get($date, collect());
            $egresos = [];
            foreach ($dayCredits as $credit) {
                $cli = $credit->client;
                $cliName = $cli ? trim($cli->apellido_pat . ' ' . $cli->apellido_mat . ' ' . $cli->nombre) : 'N/A';
                $asesor  = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';

                $interesMonto = in_array((int) $credit->tipo_planilla, [1, 4])
                    ? round(($credit->importe * $credit->interes) / 100, 2)
                    : round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;

                $egresos[] = [
                    'credit_id'     => $credit->id,
                    'cliente'       => $cliName,
                    'monto'         => (float) $credit->importe,
                    'interes_pct'   => (float) $credit->interes,
                    'interes_monto' => $interesMonto,
                    'usuario'       => $credit->user?->username ?? $credit->user?->name ?? '',
                    'asesor'        => $asesor,
                    'tipo_planilla' => (int) $credit->tipo_planilla,
                ];
            }

            if (count($ingresos) === 0 && count($egresos) === 0) continue;

            $subIng    = collect($ingresos)->sum('total');
            $subCap    = collect($ingresos)->sum('capital');
            $subInt    = collect($ingresos)->sum('interes');
            $subMora   = collect($ingresos)->sum('mora');
            $subEgr    = collect($egresos)->sum('monto');
            $subEgrInt = collect($egresos)->sum('interes_monto');

            $days[] = [
                'date'                => $date,
                'date_label'          => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                'ingresos'            => $ingresos,
                'egresos'             => $egresos,
                'sub_ingresos'        => $subIng,
                'sub_capital'         => $subCap,
                'sub_interes'         => $subInt,
                'sub_mora'            => $subMora,
                'sub_egresos'         => $subEgr,
                'sub_egresos_interes' => $subEgrInt,
            ];

            $Tcpi  += $subIng;
            $Tcpi2 += $subCap;
            $Tint  += $subInt;
            $Tmor4 += $subMora;
            $toff  += $subEgr;
            $toff2 += $subEgrInt;
        }

        return view('livewire.reports.cash-general-1', [
            'days'  => $days,
            'Tcpi'  => $Tcpi,
            'Tcpi2' => $Tcpi2,
            'Tint'  => $Tint,
            'Tmor4' => $Tmor4,
            'toff'  => $toff,
            'toff2' => $toff2,
            'toff1' => $Tcpi + $Tmor4,
        ]);
    }
}
