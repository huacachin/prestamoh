<?php

namespace App\Livewire\Reports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Advisor extends Component
{
    public $selemes;
    public $selecano;
    public $ejecutivo = 'Todos';

    public function mount()
    {
        $this->selemes  = date('m');
        $this->selecano = date('Y');
    }

    public function search() {}

    private function applyAdvisorFilter($query, string $alias = 'credits')
    {
        if ($this->ejecutivo !== 'Todos' && $this->ejecutivo !== '') {
            $query->whereExists(function ($q) use ($alias) {
                $q->from('clients')
                  ->whereColumn('clients.id', $alias . '.client_id')
                  ->where('clients.asesor_id', $this->ejecutivo);
            });
        }
        return $query;
    }

    public function render()
    {
        $year  = (int) $this->selecano;
        $month = (int) $this->selemes;
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate   = $startDate->copy()->endOfMonth();
        $daysInMonth = $endDate->day;

        // ─── 1) Créditos creados en el mes (NUEVO + REN./REF.) ────────────
        $creditsCreated = DB::table('credits')
            ->whereBetween('fecha_prestamo', [$startDate, $endDate])
            ->where('situacion', '<>', 'Eliminado');
        $this->applyAdvisorFilter($creditsCreated, 'credits');
        $creditsCreated = $creditsCreated
            ->select('id', 'client_id', 'fecha_prestamo', 'importe', 'refinanciado', 'cod_rem', 'situacion')
            ->get();

        $byDayNuevo = [];
        $byDayRenov = [];
        $byDayCapital = [];
        $byDayCount  = [];   // count credits issued (TOTAL clientes)
        foreach ($creditsCreated as $c) {
            $d = (int) Carbon::parse($c->fecha_prestamo)->format('d');
            $isRefi = ($c->refinanciado == 1) || ($c->cod_rem === 'REF');
            if ($isRefi) {
                $byDayRenov[$d] = ($byDayRenov[$d] ?? 0) + 1;
            } else {
                $byDayNuevo[$d] = ($byDayNuevo[$d] ?? 0) + 1;
            }
            $byDayCount[$d] = ($byDayCount[$d] ?? 0) + 1;
            $byDayCapital[$d] = ($byDayCapital[$d] ?? 0) + (float) $c->importe;
        }

        // ─── 2) Créditos cancelados en el mes ─────────────────────────────
        $creditsCancel = DB::table('credits')
            ->whereBetween('fecha_cancelacion', [$startDate, $endDate]);
        $this->applyAdvisorFilter($creditsCancel, 'credits');
        $creditsCancel = $creditsCancel
            ->selectRaw('DAY(fecha_cancelacion) as d, COUNT(*) as c')
            ->groupBy(DB::raw('DAY(fecha_cancelacion)'))
            ->pluck('c', 'd')
            ->toArray();

        // ─── 3) Cuotas esperadas en el mes (IMP. A COBRAR) ────────────────
        $insExpected = DB::table('credit_installments')
            ->join('credits', 'credits.id', '=', 'credit_installments.credit_id')
            ->whereBetween('credit_installments.fecha_pago', [$startDate, $endDate])
            ->where('credits.situacion', 'Activo');
        if ($this->ejecutivo !== 'Todos' && $this->ejecutivo !== '') {
            $insExpected->join('clients', 'clients.id', '=', 'credits.client_id')
                ->where('clients.asesor_id', $this->ejecutivo);
        }
        $insExpected = $insExpected
            ->selectRaw('DAY(credit_installments.fecha_pago) as d, SUM(credit_installments.importe_cuota + credit_installments.importe_interes) as imp')
            ->groupBy(DB::raw('DAY(credit_installments.fecha_pago)'))
            ->pluck('imp', 'd')
            ->toArray();

        // ─── 4) Pagos cobrados en el mes ──────────────────────────────────
        $paysCollected = DB::table('payments')
            ->join('credits', 'credits.id', '=', 'payments.credit_id')
            ->whereBetween('payments.fecha', [$startDate, $endDate])
            ->where('payments.documento', '<>', 'MORA')
            ->whereRaw("(payments.detalle IS NULL OR RIGHT(payments.detalle, 3) <> 'Gat')");
        if ($this->ejecutivo !== 'Todos' && $this->ejecutivo !== '') {
            $paysCollected->join('clients', 'clients.id', '=', 'credits.client_id')
                ->where('clients.asesor_id', $this->ejecutivo);
        }
        $paysCollected = $paysCollected
            ->selectRaw('DAY(payments.fecha) as d, payments.credit_id, SUM(payments.monto) as monto')
            ->groupBy(DB::raw('DAY(payments.fecha)'), 'payments.credit_id')
            ->get();

        $byDayCobImp = [];
        $byDayCobCnt = [];
        foreach ($paysCollected as $p) {
            $byDayCobImp[$p->d] = ($byDayCobImp[$p->d] ?? 0) + (float) $p->monto;
            if ((float) $p->monto > 0) {
                $byDayCobCnt[$p->d] = ($byDayCobCnt[$p->d] ?? 0) + 1;
            }
        }

        // ─── Construccion de filas diarias ────────────────────────────────
        $rows = [];
        $tot = [
            'nuevo' => 0, 'renov' => 0, 'canc' => 0, 'total' => 0,
            'capital' => 0, 'imp_cobrar' => 0, 'cob_cnt' => 0, 'cob_imp' => 0,
            'noc_cnt' => 0, 'noc_imp' => 0,
        ];
        $diasTot = 0; // dias con cobranza > 0 (legacy contador para promedios)

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d);
            $dow = $date->dayOfWeek;
            $color = '';
            if ($dow === Carbon::SUNDAY) $color = 'red';
            elseif ($dow === Carbon::SATURDAY) $color = 'green';

            $nuevo  = $byDayNuevo[$d]  ?? 0;
            $renov  = $byDayRenov[$d]  ?? 0;
            $canc   = $creditsCancel[$d] ?? 0;
            $total  = $byDayCount[$d]  ?? 0;
            $capital = $byDayCapital[$d] ?? 0;
            $impCobrar = (float) ($insExpected[$d] ?? 0);
            $cobCnt = $byDayCobCnt[$d] ?? 0;
            $cobImp = $byDayCobImp[$d] ?? 0;
            $nocCnt = max(0, $total - $cobCnt);
            $nocImp = $impCobrar - $cobImp;

            $rows[] = [
                'd'          => $d,
                'fecha'      => $date->format('Y-m-d'),
                'color'      => $color,
                'nuevo'      => $nuevo,
                'renov'      => $renov,
                'canc'       => $canc,
                'total'      => $total,
                'capital'    => $capital,
                'imp_cobrar' => $impCobrar,
                'cob_cnt'    => $cobCnt,
                'cob_imp'    => $cobImp,
                'noc_cnt'    => $nocCnt,
                'noc_imp'    => $nocImp,
            ];

            $tot['nuevo']      += $nuevo;
            $tot['renov']      += $renov;
            $tot['canc']       += $canc;
            $tot['total']      += $total;
            $tot['capital']    += $capital;
            $tot['imp_cobrar'] += $impCobrar;
            $tot['cob_cnt']    += $cobCnt;
            $tot['cob_imp']    += $cobImp;
            $tot['noc_cnt']    += $nocCnt;
            $tot['noc_imp']    += $nocImp;

            if ($cobCnt >= 0.01 || $cobImp >= 0.01) {
                $diasTot++;
            }
        }
        $diasTot = max(1, $diasTot);
        $avg = [];
        foreach ($tot as $k => $v) {
            $avg[$k] = $v / $diasTot;
        }

        // ─── Tabla mensual del año seleccionado ───────────────────────────
        $monthlyHistory = $this->buildMonthlyHistory($year);

        // ─── Asesores para el dropdown ────────────────────────────────────
        $asesores = User::role('asesor')->orderBy('name')->get(['id', 'name']);

        $months = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        return view('livewire.reports.advisor', [
            'rows'           => $rows,
            'tot'            => $tot,
            'avg'            => $avg,
            'monthlyHistory' => $monthlyHistory,
            'asesores'       => $asesores,
            'months'         => $months,
        ]);
    }

    private function buildMonthlyHistory(int $year): array
    {
        // Legacy usa cache pre-computada `huaca_reptotales` (sin filtro por ejecutivo)
        // Replicamos exactamente: leer de cache_advisor_monthly por año.
        $cached = DB::table('cache_advisor_monthly')
            ->where('ano', $year)
            ->orderBy('mesmes')
            ->get()
            ->keyBy(fn ($r) => (int) $r->mesmes);

        $monthLabels = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $rows = [];
        $tot = ['nuevo'=>0,'renov'=>0,'canc'=>0,'total'=>0,'capital'=>0,'imp_cobrar'=>0,'cob_cnt'=>0,'cob_imp'=>0,'noc_cnt'=>0,'noc_imp'=>0];
        $cnt = 0;

        for ($m = 1; $m <= 12; $m++) {
            $r = $cached[$m] ?? null;

            $rows[] = [
                'n'          => $m,
                'mes'        => $monthLabels[$m - 1],
                'nuevo'      => (float) ($r->xcn ?? 0),
                'renov'      => (float) ($r->xrc ?? 0),
                'canc'       => (float) ($r->canc ?? 0),
                'total'      => (float) ($r->total ?? 0),
                'capital'    => (float) ($r->capital ?? 0),
                'imp_cobrar' => (float) ($r->impacobra ?? 0),
                'cob_cnt'    => (float) ($r->cobcnt ?? 0),
                'cob_imp'    => (float) ($r->cobimp ?? 0),
                'noc_cnt'    => (float) ($r->nocobcnt ?? 0),
                'noc_imp'    => (float) ($r->nocobimp ?? 0),
            ];

            foreach (['nuevo','renov','canc','total','capital','imp_cobrar','cob_cnt','cob_imp','noc_cnt','noc_imp'] as $k) {
                $tot[$k] += end($rows)[$k];
            }
            $cnt++;
        }
        $cnt = max(1, $cnt);
        $avg = [];
        foreach ($tot as $k => $v) $avg[$k] = $v / $cnt;

        return ['rows' => $rows, 'tot' => $tot, 'avg' => $avg];
    }
}
