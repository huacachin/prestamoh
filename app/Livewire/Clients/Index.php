<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $nexpediente = '';
    public $documento = '';
    public $nombre = '';
    public $ruta = '';
    public $ejecutivo = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->can('clientes.eliminar')) {
            abort(403);
        }
        Client::findOrFail($id)->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Cliente desactivado correctamente']);
    }

    public function render()
    {
        $user = auth()->user();

        $query = Client::query()
            ->where('status', 'active')
            ->with(['asesor:id,name,username', 'headquarter:id,name'])
            ->withCount(['avales', 'attachments']);

        if ($user->can('clientes.scope-propio')) {
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

        $clients = $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->get();

        // Asesores para dropdown: cualquier usuario que pueda ser "asesor responsable" o tenga acceso amplio.
        $asesores = User::permission('creditos.ser-asesor-responsable')
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

        return view('livewire.clients.index', compact('clients', 'asesores', 'clientsWithCredit'));
    }
}
