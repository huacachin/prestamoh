<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Services\CajaDailyService;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class CashGeneral1 extends Component
{
    #[Url(as: 'mes')]
    public $selemes;

    #[Url(as: 'anio')]
    public $selecano;

    #[Url(as: 'tipo', except: '0000')]
    public $seletipl = '0000';

    /** 'detalle' = tabla completa homóloga al legacy (default); 'resumen' = una fila por día con totales. */
    #[Url(as: 'vista', except: 'detalle')]
    public $vista = 'detalle';

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

    /** Desde la vista resumen (tarjeta o columna del gráfico): cambia a detalle y desplaza hasta el día elegido. */
    #[On('caja1-ver-dia')]
    public function verDia(string $date): void
    {
        $this->vista = 'detalle';
        $this->dispatch('scroll-to-day', date: $date);
    }

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
        $Texc = 0;
        $Tmor4 = 0;
        $TmorAcum = 0;
        $toff = 0;
        $toff2 = 0;
        $toffRef = 0;
        $toffRefN = 0;

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
            $subExc = collect($ingresos)->sum('excedente');
            // Mora desglosada: vigente vs acumulada (documento 'MORA ACUM.')
            $subMoraAcum = collect($ingresos)->sum('mora_acum');
            $subMora = collect($ingresos)->sum('mora') - $subMoraAcum;
            $subEgr = collect($egresos)->sum('monto');
            $subEgrInt = collect($egresos)->sum('interes_monto');
            // Refinanciados (cod_rem=REF): no es plata que salió de caja, es
            // rollover de un crédito anterior. Se informa vía tooltip en el
            // subtotal de egresos, sin alterar el número mostrado.
            $egrRef = collect($egresos)->where('cod_rem', 'REF');
            $subEgrRef = $egrRef->sum('monto');
            $subEgrRefN = $egrRef->count();

            $days[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date)->translatedFormat('l d \\d\\e F Y'),
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'sub_ingresos' => $subIng,
                'sub_capital' => $subCap,
                'sub_interes' => $subInt,
                'sub_excedente' => $subExc,
                'sub_mora' => $subMora,
                'sub_mora_acum' => $subMoraAcum,
                'sub_egresos' => $subEgr,
                'sub_egresos_interes' => $subEgrInt,
                'sub_egresos_ref' => $subEgrRef,
                'sub_egresos_ref_n' => $subEgrRefN,
            ];

            $Tcpi += $subIng;
            $Tcpi2 += $subCap;
            $Tint += $subInt;
            $Texc += $subExc;
            $Tmor4 += $subMora;
            $TmorAcum += $subMoraAcum;
            $toff += $subEgr;
            $toff2 += $subEgrInt;
            $toffRef += $subEgrRef;
            $toffRefN += $subEgrRefN;
        }

        // Comparativa contra el mes anterior (solo la usa la vista resumen)
        $prevStats = $this->vista === 'resumen'
            ? $this->statsMesAnterior($year, $month, $tipoFilter, $today)
            : null;

        return view('livewire.reports.cash-general-1', [
            'days' => $days,
            'Tcpi' => $Tcpi,
            'Tcpi2' => $Tcpi2,
            'Tint' => $Tint,
            'Texc' => $Texc,
            'Tmor4' => $Tmor4,
            'TmorAcum' => $TmorAcum,
            'toff' => $toff,
            'toff2' => $toff2,
            'toff1' => $Tcpi + $Texc + $Tmor4 + $TmorAcum,
            'toffRef' => $toffRef,
            'toffRefN' => $toffRefN,
            'prevStats' => $prevStats,
        ]);
    }

    /**
     * Totales del mes anterior para los KPI del resumen: total caja,
     * interés, egresos y neto. Null-safe si el mes no tiene datos.
     *
     * @return array{total: float, interes: float, egresos: float, neto: float, label: string}
     */
    private function statsMesAnterior(int $year, int $month, ?int $tipoFilter, string $today): array
    {
        $prev = Carbon::create($year, $month, 1)->subMonth();
        $prevStart = $prev->format('Y-m-d');
        $prevEnd = min($prev->copy()->endOfMonth()->format('Y-m-d'), $today);

        $ingresos = collect(app(CajaDailyService::class)->ingresosPorDia($prev->year, $prev->month, $tipoFilter, $prevEnd))
            ->flatten(1);

        // Total de caja del día = total (cap+int) + excedente + mora (incluye acum.)
        $total = (float) $ingresos->sum(fn ($i) => $i['total'] + $i['excedente'] + $i['mora']);
        $interes = (float) $ingresos->sum('interes');

        $egrQuery = Credit::query()
            ->where('fecha_actualizacion', '>=', $prevStart)
            ->where('fecha_actualizacion', '<=', $prevEnd);
        if ($tipoFilter !== null) {
            $egrQuery->where('tipo_planilla', $tipoFilter);
        }
        $egresos = (float) $egrQuery->sum('importe');

        return [
            'total' => $total,
            'interes' => $interes,
            'egresos' => $egresos,
            'neto' => $total - $egresos,
            'label' => ucfirst($prev->translatedFormat('F Y')),
        ];
    }
}
