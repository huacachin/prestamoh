<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class Portfolio extends Component
{
    public $filterAsesor = '';

    public function render()
    {
        $credits = Credit::query()
            ->with(['client', 'payments', 'installments'])
            ->where('situacion', 'Activo')
            ->when($this->filterAsesor !== '', fn ($q) =>
                $q->where('asesor', $this->filterAsesor)
            )
            ->orderByDesc('id')
            ->get();

        $today = Carbon::today();

        $data = $credits->map(function ($credit) use ($today) {
            $totalPagado = $credit->payments->sum('monto');
            $importeTotal = $credit->importe + $credit->interes_total;
            $saldoPendiente = $importeTotal - $totalPagado;

            // Dias mora: max days overdue from unpaid installments
            $diasMora = 0;
            $cuotasVencidas = $credit->installments
                ->where('pagado', false)
                ->filter(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->lt($today));

            if ($cuotasVencidas->isNotEmpty()) {
                $diasMora = $cuotasVencidas
                    ->map(fn ($i) => $today->diffInDays($i->fecha_vencimiento))
                    ->max();
            }

            return (object) [
                'id'              => $credit->id,
                'cliente'         => $credit->client?->fullName(),
                'documento'       => $credit->client?->documento,
                'fecha_prestamo'  => $credit->fecha_prestamo,
                'importe'         => $credit->importe,
                'cuotas'          => $credit->cuotas,
                'interes'         => $credit->interes,
                'total_pagado'    => $totalPagado,
                'saldo_pendiente' => max($saldoPendiente, 0),
                'dias_mora'       => $diasMora,
            ];
        });

        $totals = (object) [
            'importe'         => $data->sum('importe'),
            'total_pagado'    => $data->sum('total_pagado'),
            'saldo_pendiente' => $data->sum('saldo_pendiente'),
        ];

        $asesores = User::orderBy('name')->pluck('name', 'name');

        return view('livewire.reports.portfolio', compact('data', 'totals', 'asesores'));
    }
}
