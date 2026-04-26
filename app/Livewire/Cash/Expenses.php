<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Livewire\Component;

class Expenses extends Component
{
    public string $tipo = '1';   // 1=A, 2=Motivo, 3=Usuario, 4=Respons.
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

        $query = Expense::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        // Filtros por rol (legacy)
        // - Asesor/Cobranza: solo sus propios egresos con reason='Diario'
        if ($user->hasAnyRole(['asesor', 'cobranza'])) {
            $query->where('user_id', $user->id)
                  ->where('reason', 'Diario');
        }

        // Lógica fechas + búsqueda (estilo legacy)
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda, sin filtro de fecha
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        // Filtro búsqueda
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%")
                ),
                '4' => $query->where('in_charge', 'like', "%{$term}%"),
                default => null,
            };
        }

        $expenses = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        // Subtotales
        $tofijo = 0; $totros = 0;
        $sumdiario = 0; $summensu = 0; $sumdm = 0;
        $totalGeneral = 0;

        foreach ($expenses as $e) {
            $totalGeneral += (float) $e->total;
            if ($e->modo === 'Fijos') $tofijo += $e->total;
            else $totros += $e->total;

            if ($e->reason === 'Diario') $sumdiario += $e->total;
            elseif ($e->reason === 'Mensual') $summensu += $e->total;
            elseif ($e->reason === 'D.M') $sumdm += $e->total;
        }

        $datos220 = $sumdm / 2;
        $valor1 = $sumdiario + $datos220;
        $valor2 = $summensu + $datos220;
        $valor3 = $valor1 + $valor2;

        return view('livewire.cash.expenses', [
            'expenses'     => $expenses,
            'totalGeneral' => $totalGeneral,
            'tofijo'       => $tofijo,
            'totros'       => $totros,
            'sumdiario'    => $sumdiario,
            'summensu'     => $summensu,
            'sumdm'        => $sumdm,
            'valor1'       => $valor1,
            'valor2'       => $valor2,
            'valor3'       => $valor3,
        ]);
    }
}
