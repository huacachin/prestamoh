<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Monthly extends Component
{
    public $ejecutivo = 'Todos';
    public $eestado = 'Vigente'; // Vigente | Cancelado | Vencida
    public $codio1 = '';

    public function search() {}

    public function render()
    {
        $today = now()->format('Y-m-d');

        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id,imagen'])
            ->where('tipo_planilla', 3); // Mensual

        // Filtro estado: Vigente legacy → Activo nueva BD
        if ($this->eestado === 'Vigente') {
            $query->where('situacion', 'Activo');
        } elseif ($this->eestado === 'Cancelado') {
            $query->where('situacion', 'Cancelado');
        } elseif ($this->eestado === 'Vencida') {
            $query->where('situacion', 'Activo')
                  ->where('fecha_vencimiento', '<', $today);
        } else {
            // Default cuando vacio (legacy): situacion!=Cancelado y estado=1
            $query->where('situacion', '<>', 'Cancelado')->where('estado', 1);
        }

        if ($this->ejecutivo !== 'Todos' && $this->ejecutivo !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }

        if ($this->codio1 !== '') {
            $query->where('id', $this->codio1);
        }

        $credits = $query->orderBy('fecha_prestamo')->get();
        $creditIds = $credits->pluck('id')->all();

        // Pre-cargar TODAS las cuotas + datos relacionados
        $unpaidByCredit = [];     // [credit_id] = [installments where pagado=0 limit 12]
        $montoSaldoByCredit = []; // [credit_id] = sum(importe_aplicado + interes_aplicado) all
        $moraByCredit = [];       // [credit_id] = sum monto MORA from payments
        $minInstByCredit = [];    // [credit_id] = min fecha_pago de installments (igual al legacy)
        $otrosByCredit = [];      // [credit_id] = pagos before m1 (no-MORA, no-Gat)

        if (!empty($creditIds)) {
            $allIns = DB::table('credit_installments')
                ->whereIn('credit_id', $creditIds)
                ->orderBy('credit_id')->orderBy('num_cuota')
                ->get();
            foreach ($allIns as $ins) {
                $montoSaldoByCredit[$ins->credit_id] = ($montoSaldoByCredit[$ins->credit_id] ?? 0)
                    + (float) $ins->importe_aplicado + (float) $ins->interes_aplicado;
                if (!$ins->pagado) {
                    if (!isset($unpaidByCredit[$ins->credit_id]) || count($unpaidByCredit[$ins->credit_id]) < 12) {
                        $unpaidByCredit[$ins->credit_id][] = $ins;
                    }
                }
                // min fecha_pago de installments (legacy: SELECT min(fechapago) FROM det_cuentacorriente)
                if ($ins->fecha_pago) {
                    $f = Carbon::parse($ins->fecha_pago)->format('Y-m-d');
                    $minInstByCredit[$ins->credit_id] = isset($minInstByCredit[$ins->credit_id])
                        ? min($minInstByCredit[$ins->credit_id], $f)
                        : $f;
                }
            }

            $allPays = DB::table('payments')
                ->whereIn('credit_id', $creditIds)
                ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
                ->select('credit_id', 'fecha', 'monto', 'documento')
                ->get();

            $paysByCredit = [];
            foreach ($allPays as $p) {
                $isMora = ($p->documento === 'MORA');
                if ($isMora) {
                    $moraByCredit[$p->credit_id] = ($moraByCredit[$p->credit_id] ?? 0) + (float) $p->monto;
                } else {
                    $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : null;
                    if ($f) {
                        $paysByCredit[$p->credit_id][] = ['fecha' => $f, 'monto' => (float) $p->monto];
                    }
                }
            }

            // OTROS = pagos antes del primer fecha_pago de installments
            // (legacy: WHERE fechaentrada < min(huaca_det_cuentacorriente.fechapago))
            foreach ($paysByCredit as $cid => $pays) {
                $minF = $minInstByCredit[$cid] ?? null;
                $sum = 0;
                foreach ($pays as $pp) {
                    if ($pp['fecha'] >= '2019-01-01' && $minF && $pp['fecha'] < $minF) {
                        $sum += $pp['monto'];
                    }
                }
                $otrosByCredit[$cid] = $sum;
            }
        }

        // Construccion de filas
        $rows = [];
        $tot = [
            'capital' => 0, 'interes' => 0, 'apagar' => 0, 'cuota' => 0,
            'pagado' => 0, 'mora' => 0, 'otros' => 0, 'saldo' => 0,
        ];
        $sub = [
            'mora'   => ['n' => 0, 'capital' => 0, 'interes' => 0, 'apagar' => 0, 'cuota' => 0, 'pagado' => 0, 'mora' => 0, 'otros' => 0, 'saldo' => 0],
            'activo' => ['n' => 0, 'capital' => 0, 'interes' => 0, 'apagar' => 0, 'cuota' => 0, 'pagado' => 0, 'mora' => 0, 'otros' => 0, 'saldo' => 0],
        ];

        $n = 0;
        foreach ($credits as $c) {
            $n++;
            $importe = (float) $c->importe;
            $interesPct = (float) $c->interes;
            $unpaid = $unpaidByCredit[$c->id] ?? [];
            $xcount = count($unpaid);

            // Legacy: interes_total = round(importe * interes / 100, 2) * xcount (cuotas pendientes)
            $interTotal = round(($importe * $interesPct) / 100, 2) * $xcount;
            $aPagar = $importe + $interTotal;
            $cuotaCob = $xcount > 0 ? round($aPagar / $xcount, 2) : 0;

            $montoSaldo = (float) ($montoSaldoByCredit[$c->id] ?? 0);
            $mora = (float) ($moraByCredit[$c->id] ?? 0);
            $otros = (float) ($otrosByCredit[$c->id] ?? 0);
            $saldo = round($aPagar - $montoSaldo - $otros, 2);

            // 12 columnas de cuotas pendientes
            $cuotaCols = [];
            foreach ($unpaid as $ins) {
                $fechaPago = $ins->fecha_pago ? Carbon::parse($ins->fecha_pago)->format('Y-m-d') : '';
                $aplicadoTotal = (float) $ins->importe_aplicado + (float) $ins->interes_aplicado;
                $mcuotas = (float) $ins->importe_cuota + (float) $ins->importe_aplicado;

                $bg = '';
                $color = '';
                if ($fechaPago && $fechaPago < $today && (float) $ins->importe_aplicado < $mcuotas) {
                    $bg = 'red'; $color = 'white';
                } elseif ($fechaPago === $today) {
                    $bg = 'yellow'; $color = 'black';
                }

                $cuotaCols[] = [
                    'fecha'  => $fechaPago,
                    'monto'  => $aplicadoTotal,
                    'bg'     => $bg,
                    'color'  => $color,
                ];
            }
            while (count($cuotaCols) < 12) {
                $cuotaCols[] = ['fecha' => '', 'monto' => null, 'bg' => '', 'color' => ''];
            }

            $cli = $c->client;
            $rows[] = [
                'n'           => $n,
                'fecha_pres'  => $c->fecha_prestamo?->format('Y-m-d'),
                'fecha_venc'  => $c->fecha_vencimiento?->format('Y-m-d'),
                'expediente'  => $cli?->expediente,
                'has_imagen'  => !empty($cli?->imagen),
                'codigo'      => $c->id,
                'cuotas'      => $c->cuotas,
                'dni'         => $cli?->documento,
                'cliente'     => trim(($cli?->apellido_pat ?? '') . ' ' . ($cli?->apellido_mat ?? '') . ' ' . ($cli?->nombre ?? '')),
                'capital'     => $importe,
                'interes_pct' => round($interesPct, 0),
                'interes'     => $interTotal,
                'apagar'      => $aPagar,
                'cuota'       => $cuotaCob,
                'cuotas_cols' => $cuotaCols,
                'pagado'      => $montoSaldo,
                'mora'        => $mora,
                'otros'       => $otros,
                'saldo'       => $saldo,
            ];

            $tot['capital'] += $importe;
            $tot['interes'] += $interTotal;
            $tot['apagar']  += $aPagar;
            $tot['cuota']   += $cuotaCob;
            $tot['pagado']  += $montoSaldo;
            $tot['mora']    += $mora;
            $tot['otros']   += $otros;
            $tot['saldo']   += $saldo;

            // Subtotal MORA si fecha_vencimiento < hoy y saldo > 0
            $vencido = $c->fecha_vencimiento?->format('Y-m-d');
            $bucket = ($vencido && $vencido < $today && $saldo > 0) ? 'mora' : 'activo';
            $sub[$bucket]['n']++;
            $sub[$bucket]['capital'] += $importe;
            $sub[$bucket]['interes'] += $interTotal;
            $sub[$bucket]['apagar']  += $aPagar;
            $sub[$bucket]['cuota']   += $cuotaCob;
            $sub[$bucket]['pagado']  += $montoSaldo;
            $sub[$bucket]['mora']    += $mora;
            $sub[$bucket]['otros']   += $otros;
            $sub[$bucket]['saldo']   += $saldo;
        }

        $morosidadPct = $tot['saldo'] > 0 ? ($sub['mora']['saldo'] * 100) / $tot['saldo'] : 0;
        $activosPct   = $tot['saldo'] > 0 ? ($sub['activo']['saldo'] * 100) / $tot['saldo'] : 0;

        $asesores = User::orderBy('name')->get(['id', 'name']);

        return view('livewire.payments.monthly', [
            'rows'         => $rows,
            'tot'          => $tot,
            'sub'          => $sub,
            'morosidadPct' => $morosidadPct,
            'activosPct'   => $activosPct,
            'asesores'     => $asesores,
            'today'        => $today,
        ]);
    }
}
