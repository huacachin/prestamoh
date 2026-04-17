<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use App\Models\Payment;
use Livewire\Component;

class Incomes extends Component
{
    public string $tipo = '1';   // 1=A, 2=Motivo, 3=Asesor, 4=Usuario
    public string $compra = '';
    public string $fei = '';
    public string $fef = '';

    public function mount(): void
    {
        $this->fei = now()->format('Y-m-d');
        $this->fef = now()->format('Y-m-d');
    }

    public function render()
    {
        $user = auth()->user();
        $term = trim($this->compra);

        $query = Income::query()
            ->where('headquarter_id', $user->headquarter_id ?? 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        // Filtros por rol (legacy)
        if ($user->hasAnyRole(['asesor', 'cobranza'])) {
            $query->where('user_id', $user->id);
            // No agregamos aa='Diario' porque ya no existe ese campo del legacy
        }

        // Lógica fechas + búsqueda (estilo legacy)
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda
        } elseif ($term !== '' && $this->fei !== '' && $this->fef !== '') {
            $query->whereDate('date', '>=', $this->fei)
                  ->whereDate('date', '<=', $this->fef);
        } elseif ($term === '' && $this->fei !== '' && $this->fef !== '') {
            $query->whereDate('date', '>=', $this->fei)
                  ->whereDate('date', '<=', $this->fef);
        } else {
            $query->whereDate('date', now()->format('Y-m-d'));
        }

        // Filtro búsqueda
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->where('asesor', 'like', "%{$term}%"),
                '4' => $query->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%")
                ),
                default => null,
            };
        }

        $incomes = $query->orderBy('id', 'asc')->get();

        // Subtotales Fijos/Otros desde incomes
        $tofijo = 0; $totros = 0; $totalGeneral = 0;
        foreach ($incomes as $r) {
            $totalGeneral += (float) $r->total;
            if ($r->modo === 'Fijos') $tofijo += $r->total;
            elseif ($r->modo === 'Otros') $totros += $r->total;
        }

        // Subtotales Capital/Interés/Mora desde payments (mismo rango de fechas)
        $payQuery = Payment::query()
            ->where('headquarter_id', $user->headquarter_id ?? 1);

        if ($user->hasAnyRole(['asesor', 'cobranza'])) {
            $payQuery->where('user_id', $user->id);
        }

        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda
        } elseif ($term !== '' && $this->fei !== '' && $this->fef !== '') {
            $payQuery->whereDate('fecha', '>=', $this->fei)->whereDate('fecha', '<=', $this->fef);
        } elseif ($term === '' && $this->fei !== '' && $this->fef !== '') {
            $payQuery->whereDate('fecha', '>=', $this->fei)->whereDate('fecha', '<=', $this->fef);
        } else {
            $payQuery->whereDate('fecha', now()->format('Y-m-d'));
        }

        $payTotals = $payQuery->selectRaw('tipo, sum(monto) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $tocapi  = (float) ($payTotals['CAPITAL'] ?? 0);
        $totinte = (float) ($payTotals['INTERES'] ?? 0);
        $totmora = (float) ($payTotals['MORA'] ?? 0);

        // Sumar capital/interés/mora al total general
        $totalGeneral += $tocapi + $totinte + $totmora;

        return view('livewire.cash.incomes', [
            'incomes'      => $incomes,
            'totalGeneral' => $totalGeneral,
            'tofijo'       => $tofijo,
            'totros'       => $totros,
            'tocapi'       => $tocapi,
            'totinte'      => $totinte,
            'totmora'      => $totmora,
        ]);
    }
}
