<?php

namespace App\Livewire\Headquarters;

use App\Models\Headquarter;
use Livewire\Component;

class Index extends Component
{
    public $search = '';
    public $headquarters;

    public function mount()
    {
        $this->loadHeadquarters();
    }

    public function updatedSearch()
    {
        $this->loadHeadquarters();
    }

    private function loadHeadquarters(): void
    {
        $term = trim($this->search);
        $this->headquarters = Headquarter::query()
            ->with('activeUsers:id,username')
            ->when($term !== '', fn ($q) =>
                $q->where('name', 'like', "%{$term}%")
            )
            ->orderBy('sort_order')
            ->get();
    }

    public function render()
    {
        return view('livewire.headquarters.index');
    }
}
