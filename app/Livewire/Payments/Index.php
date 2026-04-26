<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use Livewire\Component;

class Index extends Component
{
    public string $nombre  = ''; // DNI
    public string $nombre1 = ''; // Nombre
    public string $codigo1 = ''; // Código

    public function render()
    {
        $user = auth()->user();

        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id'])
            ->where('situacion', '<>', 'Cancelado');

        // Filtros por rol (legacy):
        // Asesor → solo sus créditos
        // SupervisorD → solo tipoplani=4 (diario)
        // SupervisorM → solo tipoplani=1 (semanal)
        if ($user->hasRole('asesor')) {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $user->id));
        }
        // Si tienes roles SupervisorD/M en el sistema, aquí se filtraría
        // Por ahora se omite porque no tenemos esos roles activos

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
            $query->where('id', 'like', '%' . trim($this->codigo1) . '%');
        }

        $credits = $query->orderBy('id', 'asc')->get();

        $totalCapital = $credits->sum('importe');

        return view('livewire.payments.index', compact('credits', 'totalCapital'));
    }
}
