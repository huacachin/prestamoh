<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Daily extends Component
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
            ->where('tipo_planilla', 4)
            ->where('importe', '>', 0);

        // Filtro estado: Vigente legacy → Activo nueva BD
        if ($this->eestado === 'Vigente') {
            $query->where('situacion', 'Activo');
        } elseif ($this->eestado === 'Cancelado') {
            $query->where('situacion', 'Cancelado');
        } elseif ($this->eestado === 'Vencida') {
            $query->where('situacion', 'Activo')
                  ->where('fecha_vencimiento', '<', $today);
        }

        if ($this->ejecutivo !== 'Todos' && $this->ejecutivo !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }

        if ($this->codio1 !== '') {
            $query->where('id', $this->codio1);
        }

        $credits = $query->orderBy('fecha_prestamo')->get();
        $creditIds = $credits->pluck('id')->all();

        // Pre-cargar TODOS los pagos relevantes (no-MORA, no-Gat) en una sola query
        // luego procesamos en PHP para evitar N+1 queries por crédito.
        $paymentsByCredit = [];   // [credit_id][] = ['fecha' => ..., 'monto' => ...]
        $paymentsByCreditDate = []; // [credit_id][YYYY-MM-DD] = monto sum
        $moraByCredit = [];
        $minDateByCredit = [];
        $maxDateByCredit = [];

        if (!empty($creditIds)) {
            $allPays = DB::table('payments')
                ->whereIn('credit_id', $creditIds)
                ->whereNotNull('fecha')
                ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
                ->select('credit_id', 'fecha', 'monto', 'documento')
                ->get();

            foreach ($allPays as $p) {
                $f = Carbon::parse($p->fecha)->format('Y-m-d');
                $isMora = ($p->documento === 'MORA');
                if ($isMora) {
                    $moraByCredit[$p->credit_id] = ($moraByCredit[$p->credit_id] ?? 0) + (float) $p->monto;
                } else {
                    $paymentsByCredit[$p->credit_id][] = ['fecha' => $f, 'monto' => (float) $p->monto];
                    $paymentsByCreditDate[$p->credit_id][$f] = ($paymentsByCreditDate[$p->credit_id][$f] ?? 0) + (float) $p->monto;
                    $minDateByCredit[$p->credit_id] = isset($minDateByCredit[$p->credit_id])
                        ? min($minDateByCredit[$p->credit_id], $f)
                        : $f;
                    $maxDateByCredit[$p->credit_id] = isset($maxDateByCredit[$p->credit_id])
                        ? max($maxDateByCredit[$p->credit_id], $f)
                        : $f;
                }
            }
        }

        // Construccion de filas
        $rows = [];
        $tot = [
            'capital' => 0, 'interes' => 0, 'apagar' => 0, 'cuota' => 0,
            'pagado' => 0, 'mora' => 0, 'otros' => 0, 'saldo' => 0,
        ];

        $n = 0;
        foreach ($credits as $c) {
            $n++;
            $importe = (float) $c->importe;
            $interesPct = (float) $c->interes;
            $interTotal = round(($importe * $interesPct) / 100, 2);
            $aPagar = $importe + $interTotal;
            $cuotaCob = round($aPagar / 22, 2);

            $fechaPrestamo = $c->fecha_prestamo?->format('Y-m-d');
            $minF = $minDateByCredit[$c->id] ?? null;
            $maxF = $maxDateByCredit[$c->id] ?? null;

            // 32 dias desde fecha_prestamo + 1
            $days = [];
            $sumDias = 0;
            for ($d = 1; $d <= 32; $d++) {
                $fd = Carbon::parse($fechaPrestamo)->addDays($d)->format('Y-m-d');
                $monto = (float) ($paymentsByCreditDate[$c->id][$fd] ?? 0);
                $sumDias += $monto;

                $dow = Carbon::parse($fd)->dayOfWeek;
                $bg = '';
                $color = '';
                $weight = '';
                if ($maxF && $fd > $maxF) {
                    $bg = '#999'; $color = '#e7e7e7';
                } elseif ($dow === Carbon::SUNDAY) {
                    $color = 'red';
                } elseif ($dow === Carbon::SATURDAY) {
                    $color = 'green'; $weight = '600';
                } elseif ($fd === $today) {
                    $bg = 'yellow';
                } elseif ($fd < $today && $monto == 0) {
                    $bg = 'red';
                }

                $days[] = [
                    'fecha'  => $fd,
                    'monto'  => $monto,
                    'bg'     => $bg,
                    'color'  => $color,
                    'weight' => $weight,
                ];
            }

            $mora = $moraByCredit[$c->id] ?? 0;

            // OTROS = pagos antes del primer pago + despues del dia 32 (calculado en PHP, sin queries adicionales)
            $otros = 0;
            $lastDay = end($days)['fecha'];
            reset($days);
            foreach ($paymentsByCredit[$c->id] ?? [] as $pp) {
                if ($pp['fecha'] >= '2019-01-01' && $minF && $pp['fecha'] < $minF) {
                    $otros += $pp['monto'];
                } elseif ($pp['fecha'] > $lastDay && $pp['fecha'] <= '3000-12-31') {
                    $otros += $pp['monto'];
                }
            }

            $saldo = round($aPagar - $sumDias - $otros, 2);

            $cli = $c->client;
            $row = [
                'n'           => $n,
                'fecha'       => $fechaPrestamo,
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
                'days'        => $days,
                'pagado'      => $sumDias,
                'mora'        => (float) $mora,
                'otros'       => $otros,
                'saldo'       => $saldo,
            ];
            $rows[] = $row;

            $tot['capital'] += $importe;
            $tot['interes'] += $interTotal;
            $tot['apagar']  += $aPagar;
            $tot['cuota']   += $cuotaCob;
            $tot['pagado']  += $sumDias;
            $tot['mora']    += $mora;
            $tot['otros']   += $otros;
            $tot['saldo']   += $saldo;
        }

        $asesores = User::orderBy('name')->get(['id', 'name']);

        return view('livewire.payments.daily', [
            'rows'     => $rows,
            'tot'      => $tot,
            'asesores' => $asesores,
            'today'    => $today,
        ]);
    }
}
