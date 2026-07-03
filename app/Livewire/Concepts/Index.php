<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'tipo', except: '2')]
    public $tipo = '2'; // 1=Codigo, 2=Nombre

    #[Url(as: 'buscar', except: '')]
    public $compra = '';

    #[Url(as: 'estado', except: 'Activo')]
    public $estados = 'Activo';

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

        $concepts = $query->orderBy('code', 'asc')->get();

        return view('livewire.concepts.index', compact('concepts'));
    }
}
