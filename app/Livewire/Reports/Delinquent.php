<?php

namespace App\Livewire\Reports;

use App\Models\Credit;
use Carbon\Carbon;
use Livewire\Component;

class Delinquent extends Component
{
    public function render()
    {
        $today = Carbon::today();

        $credits = Credit::query()
            ->with(['client', 'installments', 'lateFees'])
            ->where('situacion', 'Activo')
            ->get();

        $data = $credits->map(function ($credit) use ($today) {
            $cuotasVencidas = $credit->installments
                ->where('pagado', false)
                ->filter(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->lt($today));

            if ($cuotasVencidas->isEmpty()) {
                return null;
            }

            $diasMora = $cuotasVencidas
                ->map(fn ($i) => $today->diffInDays($i->fecha_vencimiento))
                ->max();

            $montoMoraAcum = $credit->lateFees->sum('monto_mora');

            return (object) [
                'id'               => $credit->id,
                'cliente'          => $credit->client?->fullName(),
                'documento'        => $credit->client?->documento,
                'importe'          => $credit->importe,
                'cuotas_vencidas'  => $cuotasVencidas->count(),
                'dias_mora'        => $diasMora,
                'monto_mora_acum'  => $montoMoraAcum,
            ];
        })
        ->filter()
        ->sortByDesc('dias_mora')
        ->values();

        $totals = (object) [
            'importe'         => $data->sum('importe'),
            'monto_mora_acum' => $data->sum('monto_mora_acum'),
        ];

        return view('livewire.reports.delinquent', compact('data', 'totals'));
    }
}
