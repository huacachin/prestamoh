<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Livewire\Attributes\On;
use Livewire\Component;

class Ceased extends Component
{
    public $search = '';
    public $filterBy = 'nombre';

    public function updatedSearch()
    {
        // reactive
    }

    #[On('register_destroy')]
    public function reactivate(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('superusuario', 'administrador', 'director')) {
            abort(403);
        }

        Client::findOrFail($id)->update(['status' => 'active']);
        $this->dispatch('successAlert', ['message' => 'Cliente reactivado correctamente']);
    }

    public function render()
    {
        $term = trim($this->search);

        $clients = Client::query()
            ->where('status', 'inactive')
            ->when($term !== '', function ($q) use ($term) {
                $field = $this->filterBy;
                if ($field === 'nombre') {
                    $q->where(function ($w) use ($term) {
                        $w->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('apellido_mat', 'like', "%{$term}%");
                    });
                } elseif ($field === 'documento') {
                    $q->where('documento', 'like', "%{$term}%");
                } elseif ($field === 'expediente') {
                    $q->where('expediente', 'like', "%{$term}%");
                }
            })
            ->with(['asesor:id,name', 'headquarter:id,name'])
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.clients.ceased', compact('clients'));
    }
}
