<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class Index extends Component
{
    public $search = '';
    public $filterSituacion = '';
    public $filterTipo = '';

    public function render()
    {
        $term = trim($this->search);

        $credits = Credit::query()
            ->with(['client', 'user:id,name', 'headquarter:id,name'])
            ->when($this->filterSituacion !== '', fn ($q) =>
                $q->where('situacion', $this->filterSituacion)
            )
            ->when($this->filterTipo !== '', fn ($q) =>
                $q->where('tipo_planilla', $this->filterTipo)
            )
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->whereHas('client', fn ($c) =>
                        $c->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('documento', 'like', "%{$term}%")
                    );
                });
            })
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.credits.index', compact('credits'));
    }
}
