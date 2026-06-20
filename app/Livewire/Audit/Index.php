<?php

namespace App\Livewire\Audit;

use App\Models\User;
use App\Support\Audit;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Visor del módulo de Auditoría (acceso solo rol director, vía route role:director).
 * Lista las acciones clave registradas en activity_log (log_name = 'auditoria').
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $desde = '';

    public string $hasta = '';

    public $causer = '';

    public string $buscar = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset(['desde', 'hasta', 'causer', 'buscar']);
        $this->resetPage();
    }

    public function render()
    {
        $query = Activity::query()
            ->where('log_name', Audit::LOG)
            ->with('causer')
            ->latest();

        if ($this->desde !== '') {
            $query->whereDate('created_at', '>=', $this->desde);
        }
        if ($this->hasta !== '') {
            $query->whereDate('created_at', '<=', $this->hasta);
        }
        if ($this->causer !== '') {
            $query->where('causer_id', $this->causer)
                ->where('causer_type', User::class);
        }
        if (trim($this->buscar) !== '') {
            $query->where('description', 'like', '%' . trim($this->buscar) . '%');
        }

        $logs = $query->paginate(30);

        $usuarios = User::orderBy('name')->get(['id', 'name', 'username']);

        return view('livewire.audit.index', compact('logs', 'usuarios'));
    }
}
