<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class Schedule extends Component
{
    public Credit $credit;

    public function mount(int $id)
    {
        $this->credit = Credit::with([
            'client',
            'installments' => fn ($q) => $q->orderBy('num_cuota'),
        ])->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.credits.schedule');
    }
}
