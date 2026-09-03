<?php

namespace App\Livewire\Legal\Papeletas;

use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de papeletas de tránsito de la flota (Área Legal — FASE 5).
 *
 * Reemplaza las hojas Pap.1/Pap.2 del Excel: la cabecera muestra la DEUDA
 * viva (papeletas no pagadas ni anuladas) por entidad y por responsable de
 * pago — los totales que el área sumaba a mano — y el listado filtra por
 * entidad/estado/responsable, marca "requiere revisión" y recursos por
 * vencer (scopePorVencer de la campana legal).
 *
 * El alta/edición vive en el hijo PapeletaModal y los recursos en
 * RecursosModal (patrón Garantias\Show: dispatch('abrir-*-modal') + #[On]).
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // ── Filtros del listado (persisten en la URL) ─────────────────────

    /** Busca por N° de papeleta, placa del vehículo o nombre del conductor. */
    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    /** Clave de Papeleta::ENTIDADES o '' (todas). */
    #[Url(as: 'entidad', except: '')]
    public $filtroEntidad = '';

    /** Clave de Papeleta::ESTADOS o '' (todos). */
    #[Url(as: 'estado', except: '')]
    public $filtroEstado = '';

    /** Clave de Papeleta::RESPONSABLES o '' (todos). */
    #[Url(as: 'responsable', except: '')]
    public $filtroResponsable = '';

    /** Solo papeletas marcadas "requiere revisión" (datos por validar). */
    #[Url(as: 'revision', except: false)]
    public bool $soloRevision = false;

    /** Solo papeletas con recursos pendientes por vencer (campana legal). */
    #[Url(as: 'por_vencer', except: false)]
    public bool $soloPorVencer = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        $filtros = [
            'buscar', 'filtroEntidad', 'filtroEstado', 'filtroResponsable',
            'soloRevision', 'soloPorVencer',
        ];
        if (in_array($name, $filtros, true)) {
            $this->resetPage();
        }
    }

    /**
     * Los modales hijos avisan al guardar: basta re-renderizar para que el
     * listado y los contadores de deuda reflejen el cambio.
     */
    #[On('papeleta-guardada')]
    #[On('recurso-guardado')]
    public function refrescar(): void
    {
        // Re-render sin más: los datos se recalculan en render().
    }

    public function render()
    {
        // ── Contadores de DEUDA de la cabecera (sin filtros: panorama
        //    global). Deuda = papeletas NO pagadas ni anuladas. ──
        $deudaBase = fn () => Papeleta::query()->whereNotIn('estado', ['pagada', 'anulada']);

        $deudaPorEntidad = $deudaBase()
            ->selectRaw('entidad, COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total')
            ->groupBy('entidad')
            ->get()
            ->keyBy('entidad');

        $deudaPorResponsable = $deudaBase()
            ->selectRaw("COALESCE(responsable_pago, 'sin_asignar') AS responsable, COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total")
            ->groupBy('responsable')
            ->get()
            ->keyBy('responsable');

        // ── Listado filtrado ──
        $query = Papeleta::query()
            ->with(['vehiculo', 'recursos'])
            ->withCount('recursos');

        if (trim($this->buscar) !== '') {
            $term = trim($this->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('nro_papeleta', 'like', "%{$term}%")
                    ->orWhere('conductor_nombre', 'like', "%{$term}%")
                    ->orWhereHas('vehiculo', fn ($v) => $v->where('placa', 'like', "%{$term}%"));
            });
        }

        if (array_key_exists($this->filtroEntidad, Papeleta::ENTIDADES)) {
            $query->where('entidad', $this->filtroEntidad);
        }
        if (array_key_exists($this->filtroEstado, Papeleta::ESTADOS)) {
            $query->where('estado', $this->filtroEstado);
        }
        if (array_key_exists($this->filtroResponsable, Papeleta::RESPONSABLES)) {
            $query->where('responsable_pago', $this->filtroResponsable);
        }
        if ($this->soloRevision) {
            $query->where('requiere_revision', true);
        }
        if ($this->soloPorVencer) {
            $query->whereHas('recursos', fn ($r) => $r->porVencer());
        }

        $papeletas = $query
            ->orderByDesc('fecha_infraccion')
            ->orderByDesc('id')
            ->paginate(25);

        // Fecha tope de la campana: recursos pendientes que vencen antes de
        // este día (o ya vencidos) pintan su contador en rojo.
        $fechaAviso = now()->addDays(PapeletaRecurso::DIAS_AVISO)->toDateString();

        return view('livewire.legal.papeletas.index', compact(
            'papeletas', 'deudaPorEntidad', 'deudaPorResponsable', 'fechaAviso',
        ));
    }
}
