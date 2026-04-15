<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Component;

class Show extends Component
{
    public Client $client;

    public function mount(int $id)
    {
        $this->client = Client::with([
            'credits' => fn ($q) => $q->orderByDesc('id'),
            'credits.installments',
            'asesor:id,name',
            'headquarter:id,name',
        ])->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.clients.show');
    }
}
