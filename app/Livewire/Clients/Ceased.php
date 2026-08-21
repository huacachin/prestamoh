<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Ceased extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['nexpediente', 'documento', 'nombre', 'ruta', 'ejecutivo'], true)) {
            $this->resetPage();
        }
    }

    #[Url(as: 'expediente', except: '')]
    public $nexpediente = '';

    #[Url(as: 'documento', except: '')]
    public $documento = '';

    #[Url(as: 'nombre', except: '')]
    public $nombre = '';

    #[Url(as: 'ruta', except: '')]
    public $ruta = '';

    #[Url(as: 'asesor', except: '')]
    public $ejecutivo = '';

    #[On('register_destroy')]
    public function reactivate(int $id): void
    {
        if (! auth()->user()?->can('clientes.eliminar')) {
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
            $query->where('zona', 'like', '%'.trim($this->ruta).'%');
        }

        // Paginado (100 por página como /clients): antes bajaban ~8 MB de HTML
        $clients = $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->paginate(100);

        $asesores = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // IDs de clientes con crédito vigente (para colorear)
        $clientIds = $clients->pluck('id')->toArray();
        $clientsWithCredit = [];
        if (! empty($clientIds)) {
            $clientsWithCredit = Credit::whereIn('client_id', $clientIds)
                ->where('situacion', 'Activo')
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();
        }

        return view('livewire.clients.ceased', compact('clients', 'asesores', 'clientsWithCredit'));
    }
}
