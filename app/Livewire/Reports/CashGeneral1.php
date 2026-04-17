<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
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

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $today = Carbon::today()->format('Y-m-d');

        $days = [];
        $Tcpi = 0;   // total ingresos (montos)
        $Tcpi2 = 0;  // total capital
        $Tint = 0;   // total interés
        $Tmor4 = 0;  // total mora
        $toff = 0;   // total egresos importe
        $toff2 = 0;  // total egresos interés monto

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');

            // Stop si fecha es futura
            if ($date > $today) break;

            // INGRESOS: agrupados por crédito
            $payQuery = Payment::query()
                ->whereDate('fecha', $date)
                ->with(['credit.client:id,nombre,apellido_pat,apellido_mat,asesor_id', 'credit.client.asesor:id,name,username']);

            if ($tipoFilter !== null) {
                $payQuery->whereHas('credit', fn ($q) => $q->where('tipo_planilla', $tipoFilter));
            }

            $payments = $payQuery->get();
            $byCredit = $payments->groupBy('credit_id');

            $ingresos = [];
            foreach ($byCredit as $cid => $pays) {
                $credit = $pays->first()->credit;
                if (!$credit) continue;

                $tipoplani = (int) $credit->tipo_planilla;

                // Suma SIN mora (legacy: $montotX = $pt = sum totalgeneral excluyendo mora)
                $totalSinMora = (float) $pays->whereIn('tipo', ['CAPITAL', 'INTERES'])->sum('monto');

                $interes = (float) $pays->where('tipo', 'INTERES')->sum('monto');
                $mora    = (float) $pays->where('tipo', 'MORA')->sum('monto');

                // Lógica del legacy:
                // tipoplani=4 (Diario): capital = total - interés
                // tipoplani=1 o 3: capital = sum(documento='CAPITAL')
                if ($tipoplani === 4) {
                    $capital = $totalSinMora - $interes;
                } else {
                    $capital = (float) $pays->where('tipo', 'CAPITAL')->sum('monto');
                }

                // TOTAL en el legacy es $montotX (sum sin mora)
                $total = $totalSinMora;

                $cli = $credit->client;
                $cliName = $cli ? trim($cli->nombre . ' ' . $cli->apellido_pat . ' ' . $cli->apellido_mat) : 'N/A';
                $asesor  = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';
                $nroCuotas = $pays->pluck('installment_id')->filter()->unique()->count();

                $ingresos[] = [
                    'credit_id'     => $cid,
                    'cliente'       => $cliName,
                    'detalle'       => $credit->glosa ?? '',
                    'nro_cuotas'    => $nroCuotas,
                    'total'         => $total,
                    'capital'       => $capital,
                    'interes'       => $interes,
                    'mora'          => $mora,
                    'asesor'        => $asesor,
                    'tipo_planilla' => $tipoplani,
                ];
            }

            // EGRESOS: créditos desembolsados ese día
            $credQuery = Credit::query()
                ->whereDate('fecha_prestamo', $date)
                ->with(['client:id,nombre,apellido_pat,apellido_mat,asesor_id', 'client.asesor:id,name,username', 'user:id,name,username']);

            if ($tipoFilter !== null) {
                $credQuery->where('tipo_planilla', $tipoFilter);
            }

            $credits = $credQuery->get();

            $egresos = [];
            foreach ($credits as $credit) {
                $cli = $credit->client;
                $cliName = $cli ? trim($cli->nombre . ' ' . $cli->apellido_pat . ' ' . $cli->apellido_mat) : 'N/A';
                $asesor  = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';

                // Cálculo interés
                if (in_array((int) $credit->tipo_planilla, [1, 4])) {
                    $interesMonto = round(($credit->importe * $credit->interes) / 100, 2);
                } else {
                    $interesMonto = round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;
                }

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

            $subIng    = collect($ingresos)->sum('total');
            $subCap    = collect($ingresos)->sum('capital');
            $subInt    = collect($ingresos)->sum('interes');
            $subMora   = collect($ingresos)->sum('mora');
            $subEgr    = collect($egresos)->sum('monto');
            $subEgrInt = collect($egresos)->sum('interes_monto');

            if (count($ingresos) > 0 || count($egresos) > 0) {
                $days[] = [
                    'date'         => $date,
                    'date_label'   => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                    'ingresos'     => $ingresos,
                    'egresos'      => $egresos,
                    'sub_ingresos' => $subIng,
                    'sub_capital'  => $subCap,
                    'sub_interes'  => $subInt,
                    'sub_mora'     => $subMora,
                    'sub_egresos'  => $subEgr,
                    'sub_egresos_interes' => $subEgrInt,
                ];

                $Tcpi  += $subIng;
                $Tcpi2 += $subCap;
                $Tint  += $subInt;
                $Tmor4 += $subMora;
                $toff  += $subEgr;
                $toff2 += $subEgrInt;
            }
        }

        return view('livewire.reports.cash-general-1', [
            'days'   => $days,
            'Tcpi'   => $Tcpi,
            'Tcpi2'  => $Tcpi2,
            'Tint'   => $Tint,
            'Tmor4'  => $Tmor4,
            'toff'   => $toff,
            'toff2'  => $toff2,
            'toff1'  => $Tcpi + $Tmor4, // total general ingresos + mora
        ]);
    }
}
