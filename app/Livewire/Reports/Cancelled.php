<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Cancelled extends Component
{
    #[Url(as: 'mes')]
    public $selemes;

    #[Url(as: 'anio')]
    public $selecano;

    #[Url(as: 'tipo', except: '')]
    public $seletipl = '';

    #[Url(as: 'interes', except: '')]
    public $intereses = '';

    #[Url(as: 'expediente', except: '')]
    public $exp = '';

    #[Url(as: 'codigo', except: '')]
    public $codigo = '';

    #[Url(as: 'dni', except: '')]
    public $cdni = '';

    #[Url(as: 'nombre', except: '')]
    public $cnombre = '';

    #[Url(as: 'asesor', except: '')]
    public $casesor = '';

    public function mount()
    {
        if (! request()->has('mes')) {
            $this->selemes = date('m');
        }
        if (! request()->has('anio')) {
            $this->selecano = date('Y');
        }
    }

    public function search() {}

    public function render()
    {
        // ─── QUERY de créditos cancelados ──────────────────────────────
        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento'])
            ->where('situacion', 'Cancelado');

        if ($this->selemes !== '' && $this->selecano !== '' && $this->selemes !== '00') {
            $query->whereYear('fecha_cancelacion', $this->selecano)
                ->whereMonth('fecha_cancelacion', $this->selemes);
        }
        if ($this->seletipl !== '') {
            $query->where('tipo_planilla', $this->seletipl);
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
            $query->where('asesor', 'like', "%{$this->casesor}%");
        }

        $credits = $query->orderBy('fecha_cancelacion')->orderBy('id')->orderBy('client_id')->get();

        // Pre-cargar sums de payments por crédito (excluyendo Gat.)
        $creditIds = $credits->pluck('id')->toArray();
        $sumIngresoMap = [];
        if (! empty($creditIds)) {
            $sumRows = DB::table('payments')
                ->whereIn('credit_id', $creditIds)
                ->where(function ($q) {
                    $q->whereNull('detalle')->orWhere('detalle', 'NOT LIKE', 'Gat.%');
                })
                ->selectRaw('credit_id, SUM(monto) as total')
                ->groupBy('credit_id')
                ->get();
            foreach ($sumRows as $r) {
                $sumIngresoMap[$r->credit_id] = (float) $r->total;
            }
        }

        // Interés real devengado según el cronograma. En una cancelación
        // anticipada el cronograma condona el interés no devengado, así que este
        // valor (< interés pactado) refleja lo realmente cobrado.
        $interesCronMap = [];
        if (! empty($creditIds)) {
            $cronRows = DB::table('credit_installments')
                ->whereIn('credit_id', $creditIds)
                ->selectRaw('credit_id, SUM(importe_interes) as int_cron')
                ->groupBy('credit_id')
                ->get();
            foreach ($cronRows as $r) {
                $interesCronMap[$r->credit_id] = (float) $r->int_cron;
            }
        }

        // Detectar créditos referenciados como idcan (refinanciados)
        $refIds = DB::table('credits')->whereIn('idcan', $creditIds)->pluck('idcan')->unique()->toArray();
        $refSet = array_flip($refIds);

        // ─── PROCESAR cada crédito ─────────────────────────────────────
        $rows = [];
        $totals = [
            'cancecapi' => 0, // sum capital (importe)
            'canceinteg' => 0, // sum saldo_importe (capital recuperado)
            'todf1' => 0, // sum saldo_capit (capital pendiente)
            'canceinte' => 0, // sum new_interez
            'cancemora' => 0, // sum mora_real
            'totGP' => 0, // sum (saldo_importe + saldo_capit + new_interez)
            'montomorxdia' => 0, // sum MxD
            'total_gat' => 0, // sum gat
        ];

        $rrrr = 0;
        $contpd = 0;
        $tot2 = 0;
        $diaActual = '';

        foreach ($credits as $c) {
            $rrrr++;
            $importe = (float) $c->importe;
            $interesPct = (float) $c->interes;
            $sumIngreso = $sumIngresoMap[$c->id] ?? 0;
            $fechaCan = $c->fecha_cancelacion?->format('Y-m-d');

            // Counter por día
            if ($diaActual === '') {
                $diaActual = $fechaCan;
                $contpd++;
            } elseif ($diaActual !== $fechaCan) {
                $contpd++;
                $diaActual = $fechaCan;
                $tot2 = 0;
            }
            $tot2++;

            // Lógica legacy. El interés se toma del cronograma (devengado real:
            // al pagar antes se paga menos interés); fallback al pactado si el
            // crédito no tiene cronograma. Alimentar el algoritmo con el interés
            // real corrige además el falso saldo/rojo en cancelaciones anticipadas
            // y sincera la mora.
            $interezPactado = round(($importe * $interesPct) / 100, 2);
            $interTotal = isset($interesCronMap[$c->id])
                ? round($interesCronMap[$c->id], 2)
                : $interezPactado;
            $difeInteres = $sumIngreso - $interTotal;

            $newInterez = $interTotal;
            $saldoImporte = $sumIngreso - $newInterez;
            $saldoCapit = $importe - $saldoImporte;
            $moraReal = 0;

            if ($difeInteres > 0) {
                $salMora = $importe - $saldoImporte;
                if ($salMora < 0) {
                    $moraReal = abs($salMora);
                    $saldoImporte = $importe;
                    $saldoCapit = 0;
                }
            }

            // Días de mora = fechacan - fechafin (legacy restaFechas). Positivo cuando el
            // crédito venció antes de cancelarse; NEGATIVO cuando se canceló antes del
            // vencimiento (pago adelantado). El legacy muestra el valor crudo (incl.
            // negativos) en la columna "Dias", así que NO se recorta.
            $newdias = 0;
            if ($c->fecha_cancelacion && $c->fecha_vencimiento) {
                $newdias = (int) $c->fecha_vencimiento->diffInDays($c->fecha_cancelacion, false);
            }
            // Mora por día sobre el interés PACTADO (comportamiento legacy),
            // independiente de la condonación por pago adelantado.
            $intediasdias = round($interezPactado / 30, 2);
            $mxd = $newdias > 0 ? round($newdias * $intediasdias, 2) : 0;

            // Background color
            $bg = '';
            $colorTexto = 'black';
            if (round($saldoCapit, 2) > 0) {
                $bg = 'background-color:#ff6b6b;'; // red
                $colorTexto = 'white';
                if (isset($refSet[$c->id])) {
                    $bg = 'background-color:#b8d7b8;'; // green
                    $colorTexto = 'black';
                    if (round($saldoCapit, 0) > 0 && $saldoImporte == 0) {
                        $bg = 'background-color:yellow;';
                    }
                }
            } elseif (round($saldoCapit, 0) > 0 && $saldoImporte > 0) {
                $bg = 'background-color:#b8d7b8;';
            } elseif (round($saldoCapit, 0) > 0 && $saldoImporte == 0) {
                $bg = 'background-color:yellow;';
            }

            // Tipo planilla label
            $tipoplani = (int) $c->tipo_planilla;
            $tipoLabel = match ($tipoplani) {
                1 => '<font color="blue">Semanal</font>',
                3 => '<font color="red">Mensual</font>',
                4 => '<b>Diario</b>',
                default => '',
            };

            // Color del N° por día
            $stColor = ($contpd % 2 === 0) ? 'red' : 'black';

            $cli = $c->client;
            $rows[] = [
                'n' => $rrrr,
                'tot2' => $tot2,
                'st_color' => $stColor,
                'exp' => $cli?->expediente,
                'client_id' => $cli?->id,
                'codigo' => $c->id,
                'dni' => $cli?->documento,
                'nombre' => $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : 'N/A',
                'cod_rem' => round($saldoCapit, 0) != 0 ? ($c->cod_rem ?? '') : '',
                'capital' => $importe,
                'r_capital' => $saldoImporte,        // R./ Capital
                'capital_neto' => $saldoCapit,          // Capital Neto
                'detalles' => $tipoLabel,
                'interes_pct' => $interesPct,
                'interes_s' => $newInterez,
                'mora' => round($moraReal, 2),
                'total' => $saldoImporte + $saldoCapit + $newInterez,
                'mora_s' => $newdias > 0 ? round($intediasdias, 2) : 0,
                'mxd' => $mxd,
                'dias' => $newdias,
                'fec_cred' => $c->fecha_actualizacion?->format('Y-m-d'),
                'fec_venc' => $c->fecha_vencimiento?->format('Y-m-d'),
                'fec_cancel' => $fechaCan,
                'estado' => $c->situacion,
                'asesor' => $c->asesor ?? '',
                'bg' => $bg,
                'color_texto' => $colorTexto,
            ];

            $totals['cancecapi'] += $importe;
            $totals['canceinteg'] += $saldoImporte;
            $totals['canceinte'] += $newInterez;
            $totals['cancemora'] += $moraReal;
            $totals['todf1'] += $saldoCapit;
            $totals['totGP'] += round($saldoImporte + $saldoCapit + $newInterez, 2);
            $totals['montomorxdia'] += $mxd;
            $totals['total_gat'] += (float) $c->gat;
        }

        // ─── DISTRIBUCIÓN ──────────────────────────────────────────────
        $base = $totals['canceinte'] + $totals['cancemora'] + $totals['total_gat'];

        $datos1 = $base / 2;          // Utilidad 50%
        $datos1a = $datos1 / 3;        // 16.67%
        $datos2 = $base / 3;          // Utilidad 33%
        $datos2a = ($datos2 * 2) / 3;  // 22.33%
        $datos3 = $base / 4;          // Utilidad 25%
        $datos3a = $datos3;            // 25%

        $distribution = [
            ['label' => 'Gastos Operativos', 'pct1' => '16.67%', 'val1' => $datos1a, 'pct2' => '22.33%', 'val2' => $datos2a, 'pct3' => '25.00%', 'val3' => $datos3a],
            ['label' => 'Sueldo',            'pct1' => '16.67%', 'val1' => $datos1a, 'pct2' => '22.33%', 'val2' => $datos2a, 'pct3' => '25.00%', 'val3' => $datos3a],
            ['label' => 'Provisiones',       'pct1' => '16.67%', 'val1' => $datos1a, 'pct2' => '22.33%', 'val2' => $datos2a, 'pct3' => '25.00%', 'val3' => $datos3a],
            ['label' => 'Utilidad',          'pct1' => '50.00%', 'val1' => $datos1,  'pct2' => '33.00%', 'val2' => $datos2,  'pct3' => '25.00%', 'val3' => $datos3],
            ['label' => 'Total',             'pct1' => '100.00%', 'val1' => $datos1a * 3 + $datos1, 'pct2' => '100.00%', 'val2' => $datos2a * 3 + $datos2, 'pct3' => '100.00%', 'val3' => $datos3a * 3 + $datos3],
        ];

        $totals['distribution_base'] = $base;

        $months = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        return view('livewire.reports.cancelled', [
            'rows' => $rows,
            'totals' => $totals,
            'distribution' => $distribution,
            'months' => $months,
        ]);
    }
}
