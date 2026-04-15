<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Livewire\Component;
use Livewire\WithPagination;

class Expenses extends Component
{
    use WithPagination;

    public string $fecha = '';
    public string $search = '';

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $term = trim($this->search);

        $expenses = Expense::query()
            ->where('headquarter_id', $user->headquarter_id)
            ->when($this->fecha !== '', fn ($q) => $q->whereDate('date', $this->fecha))
            ->when($term !== '', fn ($q) =>
                $q->where(function ($w) use ($term) {
                    $w->where('reason', 'like', "%{$term}%")
                      ->orWhere('detail', 'like', "%{$term}%")
                      ->orWhere('in_charge', 'like', "%{$term}%");
                })
            )
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate(30);

        return view('livewire.cash.expenses', compact('expenses'));
    }
}
