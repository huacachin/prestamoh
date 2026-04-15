<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;

class Index extends Component
{
    public $search = '';
    public $concepts;

    public function mount()
    {
        $this->loadConcepts();
    }

    public function updatedSearch()
    {
        $this->loadConcepts();
    }

    private function loadConcepts(): void
    {
        $term = trim($this->search);
        $this->concepts = Concept::query()
            ->when($term !== '', fn ($q) =>
                $q->where('name', 'like', "%{$term}%")
            )
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        return view('livewire.concepts.index');
    }
}
