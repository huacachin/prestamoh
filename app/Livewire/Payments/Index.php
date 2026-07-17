<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'dni', except: '')]
    public string $nombre = ''; // DNI

    #[Url(as: 'nombre', except: '')]
    public string $nombre1 = ''; // Nombre

    #[Url(as: 'codigo', except: '')]
    public string $codigo1 = ''; // Código

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['nombre', 'nombre1', 'codigo1'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id'])
            ->where('situacion', '<>', 'Cancelado');

        if ($user->can('clientes.scope-propio')) {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $user->id));
        }

        // Filtro DNI
        if (trim($this->nombre) !== '') {
            $term = trim($this->nombre);
            $query->whereHas('client', fn ($c) => $c->where('documento', 'like', "%{$term}%"));
        }

        // Filtro Nombre
        if (trim($this->nombre1) !== '') {
            $term = trim($this->nombre1);
            $query->whereHas('client', function ($c) use ($term) {
                $c->where('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido_pat', 'like', "%{$term}%")
                    ->orWhere('apellido_mat', 'like', "%{$term}%");
            });
        }

        // Filtro Código
        if (trim($this->codigo1) !== '') {
            $query->where('id', 'like', '%'.trim($this->codigo1).'%');
        }

        // Totales sobre el conjunto filtrado completo (agregado en SQL),
        // independientes de la página visible.
        $totales = (clone $query)->selectRaw('COUNT(*) as n, COALESCE(SUM(importe), 0) as cap')->first();
        $totalFiltrados = (int) $totales->n;
        $totalCapital = (float) $totales->cap;

        // Paginación real: LIMIT en SQL, el HTML solo lleva la página visible.
        $credits = $query->orderBy('id', 'asc')->paginate(100);

        return view('livewire.payments.index', compact('credits', 'totalCapital', 'totalFiltrados'));
    }
}
