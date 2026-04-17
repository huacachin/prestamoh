<?php

namespace App\Livewire\Credits;

use App\Models\MassDeletion;
use Livewire\Component;
use Livewire\WithPagination;

class MassDelete extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $tipo = '1'; // 1=Codigo, 2=Asesor, 3=Usuario
    public string $compra = '';
    public string $fei = '';
    public string $fef = '';

    public function mount(): void
    {
        $this->fei = now()->format('Y-m-d');
        $this->fef = now()->format('Y-m-d');
    }

    public function updatingTipo() { $this->resetPage(); }
    public function updatingCompra() { $this->resetPage(); }
    public function updatingFei() { $this->resetPage(); }
    public function updatingFef() { $this->resetPage(); }

    public function render()
    {
        $term = trim($this->compra);
        $user = auth()->user();

        $query = MassDeletion::query()->with(['credit.client']);

        // Filtro por rol (Cobranza solo ve sus propios registros)
        if ($user && $user->hasRole('cobranza')) {
            $query->where('user', $user->username);
        }

        // Lógica del legacy: si hay búsqueda y no hay fechas, ignora fechas
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

        // Filtro por búsqueda
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('credit_id', 'like', "%{$term}%"),
                '2' => $query->where('advisor', 'like', "%{$term}%"),
                '3' => $query->where('performed_by', 'like', "%{$term}%"),
                default => null,
            };
        }

        $records = $query->orderBy('date', 'desc')->paginate(50);

        // Total para todas las páginas
        $totalQuery = clone $query;
        $totalSum = $totalQuery->sum('amount');

        return view('livewire.credits.mass-delete', compact('records', 'totalSum'));
    }
}
