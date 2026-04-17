<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Ceased extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $nexpediente = '';
    public $documento = '';
    public $nombre = '';
    public $ruta = '';
    public $ejecutivo = '';

    public function updatingNexpediente() { $this->resetPage(); }
    public function updatingDocumento() { $this->resetPage(); }
    public function updatingNombre() { $this->resetPage(); }
    public function updatingRuta() { $this->resetPage(); }
    public function updatingEjecutivo() { $this->resetPage(); }

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
        $user = auth()->user();

        $query = Client::query()
            ->where('status', 'inactive')
            ->with(['asesor:id,name,username', 'headquarter:id,name']);

        // Filtro por rol: Asesor solo ve sus clientes
        if ($user->hasRole('asesor')) {
            $query->where('asesor_id', $user->id);
        }

        // Filtros individuales
        if (trim($this->documento) !== '') {
            $query->where('documento', trim($this->documento));
        }
        if (trim($this->nombre) !== '') {
            $term = trim($this->nombre);
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                  ->orWhere('apellido_pat', 'like', "%{$term}%")
                  ->orWhere('apellido_mat', 'like', "%{$term}%");
            });
        }
        if (trim($this->nexpediente) !== '') {
            $query->where('expediente', trim($this->nexpediente));
        }
        if (trim($this->ejecutivo) !== '') {
            if ($this->ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $query->where('asesor_id', $this->ejecutivo);
            }
        }
        if (trim($this->ruta) !== '') {
            $query->where('zona', 'like', '%' . trim($this->ruta) . '%');
        }

        $clients = $query->orderBy('expediente', 'asc')->paginate(50);

        // Asesores para dropdown
        $asesores = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['asesor', 'superusuario', 'administrador', 'director']))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // IDs de clientes con crédito vigente (para colorear)
        $clientIds = $clients->pluck('id')->toArray();
        $clientsWithCredit = [];
        if (!empty($clientIds)) {
            $clientsWithCredit = \App\Models\Credit::whereIn('client_id', $clientIds)
                ->where('situacion', 'Activo')
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();
        }

        return view('livewire.clients.ceased', compact('clients', 'asesores', 'clientsWithCredit'));
    }
}