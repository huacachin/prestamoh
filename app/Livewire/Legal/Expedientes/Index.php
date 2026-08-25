<?php

namespace App\Livewire\Legal\Expedientes;

use App\Models\ExpedienteJudicial;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de expedientes judiciales (Área Legal — FASE 4).
 *
 * Lista SOLO cuadernos 'principal': el cautelar cuelga de su principal
 * (expediente_padre_id) y se muestra como badge en la misma fila.
 * Filtros por URL (compartibles): búsqueda por N° de expediente / exp.
 * interno / cliente, vía, estado, asesor responsable, "requiere revisión"
 * y "con plazos vencidos" (PlazoJudicial::scopePorVencer(0)).
 * Cabecera con contadores por estado clicables (patrón Notaria\Index).
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** Busca por N° de expediente, exp. interno o cliente (nombre/documento). */
    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    /** Clave de ExpedienteJudicial::VIAS o '' (todas). */
    #[Url(as: 'via', except: '')]
    public $filtroVia = '';

    /** Clave de ExpedienteJudicial::ESTADOS_PRINCIPAL o '' (todos). */
    #[Url(as: 'estado', except: '')]
    public $filtroEstado = '';

    /** id de users (asesor responsable) o '' (todos). */
    #[Url(as: 'asesor', except: '')]
    public $filtroAsesor = '';

    /** Solo expedientes importados con datos por revisar. */
    #[Url(as: 'revision', except: false)]
    public bool $requiereRevision = false;

    /** Solo expedientes con algún plazo pendiente ya vencido (porVencer(0)). */
    #[Url(as: 'vencidos', except: false)]
    public bool $conPlazosVencidos = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        $filtros = [
            'buscar', 'filtroVia', 'filtroEstado', 'filtroAsesor',
            'requiereRevision', 'conPlazosVencidos',
        ];
        if (in_array($name, $filtros, true)) {
            $this->resetPage();
        }
    }

    /** Badge clicable de la cabecera: fija el filtro de estado (o lo quita si ya estaba). */
    public function filtrarEstado(string $estado): void
    {
        $this->filtroEstado = $this->filtroEstado === $estado ? '' : $estado;
        $this->resetPage();
    }

    public function render()
    {
        // ── Contadores de cabecera (panorama global de principales, sin filtros) ──
        $porEstado = ExpedienteJudicial::query()
            ->where('cuaderno', 'principal')
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // ── Listado filtrado (solo cuadernos principales) ──
        $query = ExpedienteJudicial::query()
            ->where('cuaderno', 'principal')
            ->with([
                'client:id,nombre,apellido_pat,apellido_mat,documento',
                'asesor:id,name',
                'cautelares',
            ])
            ->withCount([
                // Pendientes (sin cumplir) y, de ellos, los ya vencidos:
                // el badge se pinta rojo cuando plazos_vencidos_count > 0.
                'plazos as plazos_pendientes_count' => fn ($q) => $q->whereNull('cumplido_at'),
                'plazos as plazos_vencidos_count' => fn ($q) => $q->porVencer(0),
            ]);

        // Búsqueda: N° de expediente, exp. interno, o cliente por documento/nombre
        if (trim($this->buscar) !== '') {
            $term = trim($this->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('nro_expediente', 'like', "%{$term}%")
                    ->orWhere('exp_interno', 'like', "%{$term}%")
                    ->orWhereHas('client', fn ($c) => $c->where('documento', 'like', "%{$term}%"))
                    // Nombre: cada palabra debe calzar en algún campo, en
                    // cualquier orden (mismo criterio que Garantias\Index).
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

        if (array_key_exists($this->filtroVia, ExpedienteJudicial::VIAS)) {
            $query->where('via', $this->filtroVia);
        }
        if (array_key_exists($this->filtroEstado, ExpedienteJudicial::ESTADOS_PRINCIPAL)) {
            $query->where('estado', $this->filtroEstado);
        }
        if ($this->filtroAsesor !== '' && ctype_digit((string) $this->filtroAsesor)) {
            $query->where('asesor_responsable_id', (int) $this->filtroAsesor);
        }
        if ($this->requiereRevision) {
            $query->where('requiere_revision', true);
        }
        if ($this->conPlazosVencidos) {
            $query->whereHas('plazos', fn ($q) => $q->porVencer(0));
        }

        $expedientes = $query->orderByDesc('id')->paginate(25);

        // Asesores con expedientes asignados (para el filtro)
        $asesores = User::whereIn(
            'id',
            ExpedienteJudicial::query()
                ->whereNotNull('asesor_responsable_id')
                ->select('asesor_responsable_id'),
        )->orderBy('name')->get(['id', 'name']);

        return view('livewire.legal.expedientes.index', compact('expedientes', 'porEstado', 'asesores'));
    }
}
