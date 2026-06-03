<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Services\CajaDailyService;
use Carbon\Carbon;
use Livewire\Component;

class CashGeneral1 extends Component
{
    public $selemes;

    public $selecano;

    public $seletipl = '0000';

    public function mount()
    {
        $this->selemes = date('m');
        $this->selecano = date('Y');
    }

    public function search() {}

    public function render()
    {
        $year = (int) $this->selecano;
        $month = (int) $this->selemes;
        $tipoFilter = ($this->seletipl !== '' && $this->seletipl !== '0000') ? (int) $this->seletipl : null;

        $startMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
        $endMonth = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');
        $endLimit = min($endMonth, $today);

        // ─── INGRESOS del mes (servicio compartido con cash-statistics) ─
        $ingresosPorDia = app(CajaDailyService::class)->ingresosPorDia($year, $month, $tipoFilter, $endLimit);

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

        // ─── PROCESAR DÍA POR DÍA EN MEMORIA ───────────────────────────
        $days = [];
        $Tcpi = 0;
        $Tcpi2 = 0;
        $Tint = 0;
        $Tmor4 = 0;
        $toff = 0;
        $toff2 = 0;

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d)->format('Y-m-d');
            if ($date > $today) {
                break;
            }

            // INGRESOS del día (calculados por el servicio compartido)
            $ingresos = $ingresosPorDia[$date] ?? [];

            // EGRESOS del día
            $dayCredits = $creditsByDate->get($date, collect());
            $egresos = [];
            foreach ($dayCredits as $credit) {
                $cli = $credit->client;
                $cliName = $cli ? trim($cli->apellido_pat.' '.$cli->apellido_mat.' '.$cli->nombre) : 'N/A';
                $asesor = $cli?->asesor?->username ?? $cli?->asesor?->name ?? '';

                $interesMonto = in_array((int) $credit->tipo_planilla, [1, 4])
                    ? round(($credit->importe * $credit->interes) / 100, 2)
                    : round(($credit->importe * $credit->interes) / 100, 2) * $credit->cuotas;

                $egresos[] = [
                    'credit_id' => $credit->id,
                    'cliente' => $cliName,
                    'cod_rem' => $credit->cod_rem,
                    'monto' => (float) $credit->importe,
                    'interes_pct' => (float) $credit->interes,
                    'interes_monto' => $interesMonto,
                    'usuario' => $credit->user?->username ?? $credit->user?->name ?? '',
                    'asesor' => $asesor,
                    'tipo_planilla' => (int) $credit->tipo_planilla,
                ];
            }

            if (count($ingresos) === 0 && count($egresos) === 0) {
                continue;
            }

            $subIng = collect($ingresos)->sum('total');
            $subCap = collect($ingresos)->sum('capital');
            $subInt = collect($ingresos)->sum('interes');
            $subMora = collect($ingresos)->sum('mora');
            $subEgr = collect($egresos)->sum('monto');
            $subEgrInt = collect($egresos)->sum('interes_monto');

            $days[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'sub_ingresos' => $subIng,
                'sub_capital' => $subCap,
                'sub_interes' => $subInt,
                'sub_mora' => $subMora,
                'sub_egresos' => $subEgr,
                'sub_egresos_interes' => $subEgrInt,
            ];

            $Tcpi += $subIng;
            $Tcpi2 += $subCap;
            $Tint += $subInt;
            $Tmor4 += $subMora;
            $toff += $subEgr;
            $toff2 += $subEgrInt;
        }

        return view('livewire.reports.cash-general-1', [
            'days' => $days,
            'Tcpi' => $Tcpi,
            'Tcpi2' => $Tcpi2,
            'Tint' => $Tint,
            'Tmor4' => $Tmor4,
            'toff' => $toff,
            'toff2' => $toff2,
            'toff1' => $Tcpi + $Tmor4,
        ]);
    }
}
