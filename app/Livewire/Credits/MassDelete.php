<?php

namespace App\Livewire\Credits;

use App\Models\MassDeletion;
use Livewire\Component;
use Livewire\WithPagination;

class MassDelete extends Component
{
    use WithPagination;

    public string $search = '';
    public string $searchType = '1'; // 1=code, 2=advisor, 3=user
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSearchType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);

        $query = MassDeletion::query()
            ->with(['credit.client']);

        // Role-based filter: cobranza only sees their own records
        $user = auth()->user();
        if ($user && $user->hasRole('Cobranza')) {
            $query->where('user', $user->name);
        }

        // Date range filter
        if ($this->dateFrom !== '') {
            $query->whereDate('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        // Search filter based on searchType
        if ($term !== '') {
            match ($this->searchType) {
                '1' => $query->whereHas('credit', fn ($q) =>
                    $q->where('id', 'like', "%{$term}%")
                ),
                '2' => $query->where('advisor', 'like', "%{$term}%"),
                '3' => $query->where('user', 'like', "%{$term}%"),
                default => null,
            };
        }

        $records = $query->orderByDesc('id')->paginate(50);

        // Calculate total for current filter (all pages)
        $totalQuery = MassDeletion::query();

        if ($user && $user->hasRole('Cobranza')) {
            $totalQuery->where('user', $user->name);
        }
        if ($this->dateFrom !== '') {
            $totalQuery->whereDate('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $totalQuery->whereDate('date', '<=', $this->dateTo);
        }
        if ($term !== '') {
            match ($this->searchType) {
                '1' => $totalQuery->whereHas('credit', fn ($q) =>
                    $q->where('id', 'like', "%{$term}%")
                ),
                '2' => $totalQuery->where('advisor', 'like', "%{$term}%"),
                '3' => $totalQuery->where('user', 'like', "%{$term}%"),
                default => null,
            };
        }

        $totalSum = $totalQuery->sum('amount');

        return view('livewire.credits.mass-delete', compact('records', 'totalSum'));
    }
}
