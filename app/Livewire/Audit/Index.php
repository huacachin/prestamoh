<?php

namespace App\Livewire\Audit;

use App\Models\User;
use App\Support\Audit;
use Livewire\Attributes\Url;
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

    /**
     * Clasificación de acciones por el VERBO inicial de la descripción
     * (réplica del filtro de acciones de newtaxivan). El Audit::log guarda
     * texto libre pero con convención de verbo al inicio, así que el tipo se
     * deriva del prefijo — cubre tanto los registros existentes como los
     * nuevos, sin migración. Si se agrega un verbo nuevo en un Audit::log,
     * añadirlo aquí para que filtre y lleve su badge.
     */
    public const ACCIONES = [
        'creacion' => ['label' => 'Creación', 'badge' => 'success', 'verbos' => ['Creó', 'Registró', 'Agregó', 'Aperturó', 'Refinanció', 'Generó']],
        'edicion' => ['label' => 'Edición', 'badge' => 'warning text-dark', 'verbos' => ['Editó', 'Actualizó', 'Ajustó', 'Reactivó', 'Cambió', 'Marcó']],
        'eliminacion' => ['label' => 'Eliminación', 'badge' => 'danger', 'verbos' => ['Eliminó', 'Anuló', 'Desactivó', 'Revirtió', 'Borró', 'Quitó']],
        'acceso' => ['label' => 'Acceso', 'badge' => 'secondary', 'verbos' => ['Inicio de sesión', 'Cerró sesión']],
    ];

    #[Url(as: 'accion', except: '')]
    public string $accion = '';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    #[Url(as: 'usuario', except: '')]
    public $causer = '';

    #[Url(as: 'buscar', except: '')]
    public string $buscar = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset(['desde', 'hasta', 'causer', 'buscar', 'accion']);
        $this->resetPage();
    }

    /** Tipo de acción de una descripción (por su verbo inicial), o null. */
    public function clasificar(string $descripcion): ?string
    {
        foreach (self::ACCIONES as $tipo => $cfg) {
            foreach ($cfg['verbos'] as $verbo) {
                if (str_starts_with($descripcion, $verbo)) {
                    return $tipo;
                }
            }
        }

        return null;
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
            $query->where('description', 'like', '%'.trim($this->buscar).'%');
        }
        if (isset(self::ACCIONES[$this->accion])) {
            $query->where(function ($q) {
                foreach (self::ACCIONES[$this->accion]['verbos'] as $verbo) {
                    $q->orWhere('description', 'like', $verbo.'%');
                }
            });
        }

        $logs = $query->paginate(30);

        $usuarios = User::orderBy('name')->get(['id', 'name', 'username']);

        return view('livewire.audit.index', compact('logs', 'usuarios'));
    }
}
