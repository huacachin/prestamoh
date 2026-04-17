<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $tipo = '2'; // 1=Codigo, 2=Nombre
    public $compra = '';
    public $estados = 'Activo';

    public function updatingTipo() { $this->resetPage(); }
    public function updatingCompra() { $this->resetPage(); }
    public function updatingEstados() { $this->resetPage(); }

    public function render()
    {
        $query = Concept::query();

        // Filtro búsqueda
        if (trim($this->compra) !== '') {
            $term = trim($this->compra);
            if ($this->tipo === '1') {
                $query->where('code', 'like', "%{$term}%");
            } else {
                $query->where('name', 'like', "%{$term}%");
            }
        }

        // Filtro estado
        if ($this->estados === 'Cesado') {
            $query->where('status', 'inactive');
        } else {
            $query->where('status', 'active');
        }

        $concepts = $query->orderBy('code', 'asc')->paginate(50);

        return view('livewire.concepts.index', compact('concepts'));
    }
}
