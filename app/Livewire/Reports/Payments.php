<?php

namespace App\Livewire\Reports;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Payments extends Component
{
    public $tipo = '1';   // 1=A (cliente), 2=Motivo, 3=Asesor, 4=Usuario
    public $compra = '';
    public $fei;
    public $fef;

    public function mount()
    {
        $this->fei = Carbon::today()->format('Y-m-d');
        $this->fef = Carbon::today()->format('Y-m-d');
    }

    public function search() {}

    public function render()
    {
        // ─── Listado agrupado por nroentrada+fecha (legacy) ─────────────
        $base = Payment::query()
            ->with(['credit:id,client_id', 'credit.client:id,nombre,apellido_pat,apellido_mat'])
            ->where('modo', 'CREDITO');

        if ($this->fei) $base->where('fecha', '>=', $this->fei);
        if ($this->fef) $base->where('fecha', '<=', $this->fef);

        if ($this->compra !== '') {
            $term = $this->compra;
            switch ($this->tipo) {
                case '1':
                    $base->whereHas('credit.client', function ($q) use ($term) {
                        $q->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('apellido_mat', 'like', "%{$term}%");
                    });
                    break;
                case '2':
                    $base->where('detalle', 'like', "%{$term}%");
                    break;
                case '3':
                    $base->where('asesor', 'like', "%{$term}%");
                    break;
                case '4':
                    $base->where('usuario', 'like', "%{$term}%");
                    break;
            }
        }

        // Lista plana ordenada por fecha + hora (cada movimiento en una fila)
        $payments = (clone $base)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        // Agrupacion por (credit_id + fecha) para mostrar fila por agrupacion
        $rows = [];
        $contador = 0;
        foreach ($payments->groupBy(fn ($p) => $p->credit_id . '|' . $p->fecha->format('Y-m-d')) as $group) {
            $first = $group->first();
            $contador++;
            $rows[] = [
                'n'        => $contador,
                'fecha'    => $first->fecha->format('Y-m-d'),
                'hora'     => $first->hora,
                'usuario'  => $first->usuario,
                'asesor'   => $first->asesor,
                'cliente'  => $first->credit?->client
                    ? trim(($first->credit->client->apellido_pat ?? '') . ' ' . ($first->credit->client->apellido_mat ?? '') . ' ' . ($first->credit->client->nombre ?? ''))
                    : '',
                'detalle'  => $first->detalle,
                'monto'    => $group->sum('monto'),
                'moneda'   => $first->moneda,
                'latitud'  => $first->latitud,
                'longitud' => $first->longitud,
            ];
        }

        // ─── Totales (sobre los movimientos individuales, NO agrupados) ─
        $totalsAgg = (clone $base)
            ->selectRaw("
                SUM(monto) as total,
                SUM(CASE WHEN tipo='CAPITAL' THEN monto ELSE 0 END) as capital,
                SUM(CASE WHEN tipo='INTERES' THEN monto ELSE 0 END) as interes,
                SUM(CASE WHEN tipo='MORA' THEN monto ELSE 0 END) as mora
            ")
            ->first();

        $totals = [
            'total'   => (float) ($totalsAgg->total ?? 0),
            'capital' => (float) ($totalsAgg->capital ?? 0),
            'interes' => (float) ($totalsAgg->interes ?? 0),
            'mora'    => (float) ($totalsAgg->mora ?? 0),
            'fijos'   => 0, // legacy: modo='Fijos' (no aplica en payments / CREDITO)
            'otros'   => 0, // legacy: modo='Otros' (no aplica en payments / CREDITO)
        ];

        $isAdmin = auth()->user()?->hasAnyRole(['SuperUsuario','Director','Administrador','Gerente']) ?? false;

        return view('livewire.reports.payments', [
            'rows'    => $rows,
            'totals'  => $totals,
            'isAdmin' => $isAdmin,
        ]);
    }
}
