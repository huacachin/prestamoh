<?php

namespace App\Livewire\Legal\Garantias;

use App\Models\Garantia;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de garantías mobiliarias (Área Legal).
 *
 * Filtros por URL (compartibles): búsqueda por cliente/placa, estado
 * (incluye el pseudo-estado 'por_renovar' que aplica scopePorRenovar())
 * y el check de "requiere revisión" (importadas con datos por validar).
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    /** Clave de Garantia::ESTADOS, '' (todos) o 'por_renovar' (scopePorRenovar) */
    #[Url(as: 'estado', except: '')]
    public $estado = '';

    #[Url(as: 'revision', except: false)]
    public bool $requiereRevision = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['buscar', 'estado', 'requiereRevision'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Garantia::query()
            ->with(['client', 'credit', 'vehiculos'])
            ->withCount('avisos');

        // Búsqueda: documento o nombre del cliente, o placa de un vehículo de la garantía
        if (trim($this->buscar) !== '') {
            $term = trim($this->buscar);
            $query->where(function ($q) use ($term) {
                $q->whereHas('client', fn ($c) => $c->where('documento', 'like', "%{$term}%"))
                    ->orWhereHas('vehiculos', fn ($v) => $v->where('placa', 'like', "%{$term}%"))
                    // Nombre: cada palabra debe calzar en algún campo, en cualquier
                    // orden (mismo criterio que el buscador de Clientes).
                    ->orWhereHas('client', function ($c) use ($term) {
                        foreach (preg_split('/\s+/', $term) as $word) {
                            if ($word === '') {
                                continue;
                            }
                            $c->where(function ($w) use ($word) {
                                $w->where('nombre', 'like', "%{$word}%")
                                    ->orWhere('apellido_pat', 'like', "%{$word}%")
                                    ->orWhere('apellido_mat', 'like', "%{$word}%");
                            });
                        }
                    });
            });
        }

        // Estado: clave real de ESTADOS o el pseudo-estado 'por_renovar'
        if ($this->estado === 'por_renovar') {
            $query->porRenovar();
        } elseif (array_key_exists($this->estado, Garantia::ESTADOS)) {
            $query->where('estado', $this->estado);
        }

        if ($this->requiereRevision) {
            $query->where('requiere_revision', true);
        }

        $garantias = $query->orderByDesc('id')->paginate(25);

        return view('livewire.legal.garantias.index', compact('garantias'));
    }
}
