<?php

namespace App\Livewire\Reports;

use App\Models\CreditInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Delinquent extends Component
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
        // ─── QUERY: cuotas pendientes (flpago=0) de créditos no cancelados ─
        $query = CreditInstallment::query()
            ->join('credits as c', 'credit_installments.credit_id', '=', 'c.id')
            ->join('clients as cl', 'c.client_id', '=', 'cl.id')
            ->leftJoin('users as u', 'cl.asesor_id', '=', 'u.id')
            ->where('c.situacion', '<>', 'Cancelado')
            ->where('credit_installments.pagado', false)
            ->select(
                'credit_installments.*',
                'c.id as credit_id',
                'c.importe as credit_importe',
                'c.interes as credit_interes',
                'c.cuotas as credit_cuotas',
                'c.tipo_planilla',
                'c.fecha_actualizacion',
                'c.cod_rem',
                'c.situacion',
                'cl.id as client_id',
                'cl.expediente',
                'cl.documento as cli_dni',
                'cl.nombre as cli_nombre',
                'cl.apellido_pat as cli_pat',
                'cl.apellido_mat as cli_mat',
                'cl.celular1 as cli_cel',
                'u.username as asesor_user',
                'u.name as asesor_name'
            );

        if ($this->selemes0 !== '' && $this->selecano0 !== '') {
            $query->whereYear('c.fecha_actualizacion', $this->selecano0)
                  ->whereMonth('c.fecha_actualizacion', $this->selemes0);
        }
        if ($this->exp !== '') {
            $query->where('cl.expediente', $this->exp);
        }
        if ($this->codigo !== '') {
            $query->where('c.id', $this->codigo);
        }
        if ($this->cdni !== '') {
            $query->where('cl.documento', $this->cdni);
        }
        if ($this->cnombre !== '') {
            $term = $this->cnombre;
            $query->where(function ($q) use ($term) {
                $q->where('cl.nombre', 'like', "%{$term}%")
                  ->orWhere('cl.apellido_pat', 'like', "%{$term}%")
                  ->orWhere('cl.apellido_mat', 'like', "%{$term}%");
            });
        }
        if ($this->casesor !== '') {
            $term = $this->casesor;
            $query->where(function ($q) use ($term) {
                $q->where('u.username', 'like', "%{$term}%")
                  ->orWhere('u.name', 'like', "%{$term}%");
            });
        }
        if ($this->fechai !== '' && $this->fechaf !== '') {
            $query->where('credit_installments.fecha_vencimiento', '>=', $this->fechai)
                  ->where('credit_installments.fecha_vencimiento', '<=', $this->fechaf);
        }

        $items = $query->orderBy('credit_installments.fecha_vencimiento', 'asc')->get();

        // ─── PROCESAR ──────────────────────────────────────────────────
        $today = Carbon::today();
        $rows = [];

        $totals = [
            'cuota'   => 0,
            'interes' => 0,
            'total'   => 0,
            'pago'    => 0,
            'saldo'   => 0,
        ];

        // Por tipo planilla (cuenta créditos únicos)
        $sempo = $mempo = $dempo = 0;
        $vignt = $venc = 0;
        $creditTipoSeen = [];

        $rrrr = 0;
        foreach ($items as $i) {
            $rrrr++;
            $importeCuota = (float) $i->importe_cuota;
            $importeInteres = (float) $i->importe_interes;
            $pagado = (float) $i->importe_aplicado + (float) $i->interes_aplicado;
            $totalCuota = $importeCuota + $importeInteres;
            $saldo = $totalCuota - $pagado;
            $interesPct = (float) $i->credit_interes;
            $tipo = (int) $i->tipo_planilla;

            // Conteo por tipo (1 vez por crédito)
            if (!isset($creditTipoSeen[$i->credit_id])) {
                $creditTipoSeen[$i->credit_id] = true;
                if ($tipo === 1) $sempo++;
                elseif ($tipo === 3) $mempo++;
                elseif ($tipo === 4) $dempo++;
            }

            $tcLabel = match ($tipo) {
                1 => 'S', 3 => 'M', 4 => 'D', default => '',
            };

            // Estado: Vencida si fecha_vencimiento <= hoy
            $fecVenc = $i->fecha_vencimiento ? Carbon::parse($i->fecha_vencimiento) : null;
            $estd = ($fecVenc && $fecVenc->gt($today)) ? 'Vigente' : 'Vencida';
            if ($estd === 'Vigente') $vignt++; else $venc++;

            // Tiempo
            $tiempo = '';
            if ($fecVenc) {
                $diff = $today->diff($fecVenc);
                $parts = [];
                if ($diff->y > 0) $parts[] = $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
                if ($diff->m > 0) $parts[] = $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
                if ($diff->d > 0) $parts[] = $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
                $tiempo = implode(', ', $parts);
            }

            // WhatsApp message (legacy)
            $waMsg = '';
            if ($i->cli_cel && $fecVenc) {
                $cliName = trim($i->cli_pat . ' ' . $i->cli_mat . ' ' . $i->cli_nombre);
                $waMsg = rawurlencode("Sr.(a) *{$cliName}*,\n*Huacachin* le recuerda que la cuota de su préstamo vence el " . $fecVenc->format('d/m/Y'));
            }

            $rows[] = [
                'n'             => $rrrr,
                'exp'           => $i->expediente,
                'codigo'        => $i->credit_id,
                'dni'           => $i->cli_dni,
                'cliente'       => trim(($i->cli_pat ?? '') . ' ' . ($i->cli_mat ?? '') . ' ' . ($i->cli_nombre ?? '')),
                'cod_rem'       => $i->cod_rem ?? '',
                'cuota'         => $importeCuota,
                'tc_label'      => $tcLabel,
                'tipo_planilla' => $tipo,
                'interes_pct'   => $interesPct,
                'interes_monto' => $importeInteres,
                'cuotas'        => $i->credit_cuotas,
                'pago'          => $pagado,
                'saldo'         => $saldo,
                'total'         => $totalCuota,
                'fecha_cred'    => $i->fecha_actualizacion ? Carbon::parse($i->fecha_actualizacion)->format('Y-m-d') : null,
                'fecha_venc'    => $fecVenc?->format('Y-m-d'),
                'celular'       => $i->cli_cel,
                'estado'        => $estd,
                'tiempo'        => $tiempo,
                'asesor'        => $i->asesor_user ?? $i->asesor_name ?? '',
                'wa_phone'      => $i->cli_cel,
                'wa_msg'        => $waMsg,
            ];

            $totals['cuota']   += $importeCuota;
            $totals['interes'] += $importeInteres;
            $totals['total']   += $totalCuota;
            $totals['pago']    += $pagado;
            $totals['saldo']   += $saldo;
        }

        $tc = (float) (DB::table('exchange_rates')->orderByDesc('fecha')->value('compra') ?? 1);
        if ($tc <= 0) $tc = 1;

        return view('livewire.reports.delinquent', [
            'rows'   => $rows,
            'totals' => $totals,
            'tc'     => $tc,
            'tipoTotals' => [
                'sempo' => $sempo, 'mempo' => $mempo, 'dempo' => $dempo,
            ],
            'vignt' => $vignt,
            'venc'  => $venc,
        ]);
    }
}
