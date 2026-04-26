<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Portfolio extends Component
{
    public $selemes0 = '';
    public $selecano0 = '';
    public $seletipl0 = '';
    public $exp = '';
    public $codigo = '';
    public $cdni = '';
    public $cnombre = '';
    public $casesor = '';
    public $fechai = '';
    public $fechaf = '';

    public function search() {}

    public function render()
    {
        // ─── QUERY BASE: situacion <> Cancelado + filtros ──────────────
        $query = Credit::query()
            ->with([
                'client:id,nombre,apellido_pat,apellido_mat,documento,celular1,expediente,asesor_id',
                'client.asesor:id,name,username',
            ])
            ->where('situacion', '<>', 'Cancelado');

        if ($this->selemes0 !== '' && $this->selecano0 !== '') {
            $query->whereYear('fecha_actualizacion', $this->selecano0)
                  ->whereMonth('fecha_actualizacion', $this->selemes0);
        }

        if ($this->exp !== '') {
            $query->whereHas('client', fn ($c) => $c->where('expediente', $this->exp));
        }
        if ($this->codigo !== '') {
            $query->where('id', $this->codigo);
        }
        if ($this->cdni !== '') {
            $query->whereHas('client', fn ($c) => $c->where('documento', $this->cdni));
        }
        if ($this->cnombre !== '') {
            $term = $this->cnombre;
            $query->whereHas('client', function ($c) use ($term) {
                $c->where('nombre', 'like', "%{$term}%")
                  ->orWhere('apellido_pat', 'like', "%{$term}%")
                  ->orWhere('apellido_mat', 'like', "%{$term}%");
            });
        }
        if ($this->casesor !== '') {
            $term = $this->casesor;
            $query->whereHas('client.asesor', fn ($u) =>
                $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
            );
        }
        if ($this->fechai !== '' && $this->fechaf !== '') {
            $query->where('fecha_vencimiento', '>=', $this->fechai)
                  ->where('fecha_vencimiento', '<=', $this->fechaf);
        }

        $credits = $query->orderBy('fecha_vencimiento', 'asc')->get();

        // ─── PRECARGAR PAGOS POR CRÉDITO (1 query) ─────────────────────
        $creditIds = $credits->pluck('id')->toArray();

        $pagosMap = [];
        $ultPagoMap = [];
        if (!empty($creditIds)) {
            // Sum payments by credit_id
            $sumRows = DB::table('credit_installments')
                ->whereIn('credit_id', $creditIds)
                ->groupBy('credit_id')
                ->select('credit_id',
                    DB::raw('SUM(importe_aplicado) as iapli'),
                    DB::raw('SUM(interes_aplicado) as aplido'))
                ->get();
            foreach ($sumRows as $r) {
                $pagosMap[$r->credit_id] = [
                    'iapli'  => (float) $r->iapli,
                    'aplido' => (float) $r->aplido,
                ];
            }

            // Última fecha de pago por crédito
            $ultRows = DB::table('payments')
                ->whereIn('credit_id', $creditIds)
                ->groupBy('credit_id')
                ->select('credit_id', DB::raw('MAX(fecha) as max_fecha'))
                ->get();
            foreach ($ultRows as $r) {
                $ultPagoMap[$r->credit_id] = $r->max_fecha;
            }
        }

        // ─── PROCESAR CADA CRÉDITO ─────────────────────────────────────
        $today = Carbon::today();
        $rows = [];

        $totals = [
            'capital'  => 0,
            'interes'  => 0,
            'total'    => 0,
            'pago'     => 0,
            'saldo'    => 0,
        ];

        // Por interés (legacy: huaca_tmp)
        $byInteres = []; // [pct => ['ncount' => N, 'capital' => x, 'interes' => x, 'pago' => x, 'total' => x]]

        // Por tipo planilla
        $sempo = $mempo = $dempo = 0;
        $totsem = $totmen = $totdia = 0;
        $totintesem = $totintemen = $totintdiario = 0;

        // Vigente / Vencida count
        $vignt = $venc = 0;

        // Morosidad
        $morisidad = 0; $persomoro = 0;
        $impo_2 = 0; $impo_inte = 0; $mes_mes = 0;
        $morisidadin = 0; $persomoroin = 0;
        $impo_2in = 0; $impo_intein = 0; $mes_mesin = 0;

        $rrrr = 0;
        foreach ($credits as $credit) {
            $rrrr++;
            $cli = $credit->client;
            $importe = (float) $credit->importe;
            $interesPct = (float) $credit->interes;
            $iapli = $pagosMap[$credit->id]['iapli'] ?? 0;
            $aplido = $pagosMap[$credit->id]['aplido'] ?? 0;
            $int = $importe * $interesPct / 100;
            $pagapaga = $iapli + $aplido;
            $total = $importe + $int;
            $saldo = $importe + $int - $iapli - $aplido;

            $tipo = (int) $credit->tipo_planilla;
            $tcLabel = match ($tipo) {
                1 => 'S', 3 => 'M', 4 => 'D', default => '',
            };

            // Acumular por tipo
            if ($tipo === 1) {
                $sempo++; $totsem += $importe; $totintesem += $int;
            } elseif ($tipo === 3) {
                $mempo++; $totmen += $importe; $totintemen += $int;
            } elseif ($tipo === 4) {
                $dempo++; $totdia += $importe; $totintdiario += $int;
            }

            // Por interés %
            $key = (string) $interesPct;
            if (!isset($byInteres[$key])) {
                $byInteres[$key] = ['porce' => $key, 'ncount' => 0, 'capital' => 0, 'interes' => 0, 'pago' => 0, 'total' => 0];
            }
            $byInteres[$key]['ncount']++;
            $byInteres[$key]['capital'] += $importe;
            $byInteres[$key]['interes'] += $int;
            $byInteres[$key]['pago'] += $pagapaga;
            $byInteres[$key]['total'] += $total - $pagapaga;

            // Vigente / Vencida
            $fechaFin = $credit->fecha_vencimiento;
            $estd = ($fechaFin && $fechaFin->gt($today)) ? 'Vigente' : 'Vencida';
            if ($estd === 'Vigente') $vignt++; else $venc++;

            // Morosidad
            $saldoActual = round($saldo, 2);
            $isMora = $fechaFin && $fechaFin->lt($today) && $saldoActual > 0;
            if ($isMora) {
                $morisidad += $saldoActual;
                $persomoro++;
                $impo_2 += $importe;
                $impo_inte += $int;
                $mes_mes += $saldoActual;
            } else {
                $morisidadin += $saldoActual;
                $persomoroin++;
                $impo_2in += $importe;
                $impo_intein += $int;
                $mes_mesin += $saldoActual;
            }

            // Tiempo restante
            $tiempo = '';
            if ($fechaFin) {
                $diff = $today->diff($fechaFin);
                $parts = [];
                if ($diff->y > 0) $parts[] = $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
                if ($diff->m > 0) $parts[] = $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
                if ($diff->d > 0) $parts[] = $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
                $tiempo = implode(', ', $parts);
            }

            $rows[] = [
                'n'              => $rrrr,
                'exp'            => $cli?->expediente,
                'codigo'         => $credit->id,
                'dni'            => $cli?->documento,
                'cliente'        => $cli ? trim(($cli->apellido_pat ?? '') . ' ' . ($cli->apellido_mat ?? '') . ' ' . ($cli->nombre ?? '')) : 'N/A',
                'cod_rem'        => $credit->cod_rem ?? '',
                'is_refi'        => ($credit->cod_rem ?? '') === 'REF',
                'capital'        => $importe,
                'tc_label'       => $tcLabel,
                'tipo_planilla'  => $tipo,
                'interes_pct'    => $interesPct,
                'interes_monto'  => $int,
                'cuotas'         => $credit->cuotas,
                'total'          => $total,
                'pago'           => $pagapaga,
                'saldo'          => $saldo,
                'fecha_cred'     => $credit->fecha_actualizacion?->format('Y-m-d'),
                'fecha_venc'     => $fechaFin?->format('Y-m-d'),
                'fecha_ult_pago' => $ultPagoMap[$credit->id] ?? null,
                'celular'        => $cli?->celular1,
                'estado'         => $estd,
                'tiempo'         => $tiempo,
                'asesor'         => $cli?->asesor?->username ?? $cli?->asesor?->name ?? '',
            ];

            $totals['capital'] += $importe;
            $totals['interes'] += $int;
            $totals['total']   += $total;
            $totals['pago']    += $pagapaga;
            $totals['saldo']   += $saldo;
        }

        // Ordenar byInteres por porce numérico
        ksort($byInteres, SORT_NATURAL);

        // Tipo de cambio para conversión a Dólares
        $tc = (float) (DB::table('exchange_rates')->orderByDesc('fecha')->value('compra') ?? 1);
        if ($tc <= 0) $tc = 1;

        $totSaldo = $totals['saldo'] > 0 ? $totals['saldo'] : 1;

        return view('livewire.reports.portfolio', [
            'rows'    => $rows,
            'totals'  => $totals,
            'tc'      => $tc,
            'byInteres' => array_values($byInteres),
            'tipoTotals' => [
                'sempo' => $sempo, 'mempo' => $mempo, 'dempo' => $dempo,
                'totsem' => $totsem, 'totmen' => $totmen, 'totdia' => $totdia,
                'totintesem' => $totintesem, 'totintemen' => $totintemen, 'totintdiario' => $totintdiario,
            ],
            'vignt' => $vignt,
            'venc'  => $venc,
            'morisidad' => [
                'mora_pct'        => round(($morisidad * 100) / $totSaldo, 2),
                'mora_count'      => $persomoro,
                'mora_capital'    => $impo_2,
                'mora_interes'    => $impo_inte,
                'mora_total'      => $impo_2 + $impo_inte,
                'mora_saldo'      => $mes_mes,
                'activos_pct'     => round(($morisidadin * 100) / $totSaldo, 2),
                'activos_count'   => $persomoroin,
                'activos_capital' => $impo_2in,
                'activos_interes' => $impo_intein,
                'activos_total'   => $impo_2in + $impo_intein,
                'activos_saldo'   => $mes_mesin,
                'total_count'     => $persomoro + $persomoroin,
                'total_capital'   => $impo_2 + $impo_2in,
                'total_interes'   => $impo_inte + $impo_intein,
                'total_total'     => $impo_2 + $impo_2in + $impo_inte + $impo_intein,
                'total_saldo'     => $mes_mes + $mes_mesin,
            ],
        ]);
    }
}
