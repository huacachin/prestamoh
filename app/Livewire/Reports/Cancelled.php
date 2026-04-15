<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class Cancelled extends Component
{
    public $month;
    public $year;
    public $search = '';
    public $filterTipo = '';
    public $filterInteres = '';

    public function mount()
    {
        $this->month = now()->format('m');
        $this->year = now()->format('Y');
    }

    public function render()
    {
        $credits = Credit::query()
            ->with(['client', 'installments', 'payments'])
            ->where('situacion', 'Cancelado')
            ->when($this->month && $this->year, function ($q) {
                $q->whereMonth('fecha_cancelacion', $this->month)
                  ->whereYear('fecha_cancelacion', $this->year);
            })
            ->when($this->filterTipo !== '', fn ($q) =>
                $q->where('tipo_planilla', $this->filterTipo)
            )
            ->when($this->filterInteres !== '', fn ($q) =>
                $q->where('interes', $this->filterInteres)
            )
            ->when($this->search !== '', function ($q) {
                $search = $this->search;
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere('documento', 'like', "%{$search}%")
                      ->orWhere('asesor', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($q) use ($search) {
                          $q->where('documento', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%")
                            ->orWhere('apellido_pat', 'like', "%{$search}%")
                            ->orWhere('apellido_mat', 'like', "%{$search}%");
                      });
                });
            })
            ->orderByDesc('fecha_cancelacion')
            ->get();

        $data = $credits->map(function ($credit) {
            $capitalPagado = $credit->payments->where('tipo', 'CAPITAL')->sum('monto');
            $interesPagado = $credit->payments->where('tipo', 'INTERES')->sum('monto');
            $moraPagado = $credit->payments->where('tipo', 'MORA')->sum('monto');
            $totalPagado = $credit->payments->sum('monto');
            $capitalNeto = $credit->importe - $capitalPagado;

            return (object) [
                'id'                => $credit->id,
                'codigo'            => $credit->id,
                'cliente'           => $credit->client?->fullName(),
                'documento'         => $credit->client?->documento,
                'capital'           => $credit->importe,
                'capital_pagado'    => $capitalPagado,
                'capital_neto'      => max($capitalNeto, 0),
                'interes_pct'       => $credit->interes,
                'interes_monto'     => $interesPagado,
                'mora_monto'        => $moraPagado,
                'total_pagado'      => $totalPagado,
                'fecha_credito'     => $credit->fecha_prestamo,
                'fecha_cancelacion' => $credit->fecha_cancelacion,
                'asesor'            => $credit->asesor,
                'tipo_planilla'     => $credit->tipoPlanillaLabel(),
            ];
        });

        $totals = (object) [
            'capital'        => $data->sum('capital'),
            'capital_pagado' => $data->sum('capital_pagado'),
            'capital_neto'   => $data->sum('capital_neto'),
            'interes_monto'  => $data->sum('interes_monto'),
            'mora_monto'     => $data->sum('mora_monto'),
            'total_pagado'   => $data->sum('total_pagado'),
        ];

        $asesores = User::orderBy('name')->pluck('name', 'name');

        // Distinct interest rates for filter
        $interesRates = Credit::where('situacion', 'Cancelado')
            ->select('interes')
            ->distinct()
            ->orderBy('interes')
            ->pluck('interes');

        $months = collect([
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ]);

        $years = range(now()->year, now()->year - 10);

        return view('livewire.reports.cancelled', compact(
            'data', 'totals', 'asesores', 'interesRates', 'months', 'years'
        ));
    }
}
