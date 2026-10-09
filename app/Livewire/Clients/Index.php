<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use App\Support\ConReglasDeEliminacion;
use App\Support\MorosidadClientes;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ConReglasDeEliminacion;
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /**
     * 25/09: el listado renderizaba SIEMPRE la tabla de escritorio Y las 100
     * tarjetas móviles (una de las dos oculta por CSS), así que cada cambio de
     * página bajaba ~560 KB de HTML. Alpine avisa al cargar si la pantalla es
     * móvil y solo se renderiza la versión que se ve.
     */
    public bool $movil = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['nexpediente', 'documento', 'nombre', 'ruta', 'giro', 'ejecutivo'], true)) {
            $this->resetPage();
        }
    }

    /** El modal hijo avisa que envió una notificación: re-render para refrescar el check ✓. */
    #[On('notif-enviada')]
    public function refrescarChecks(): void {}

    #[Url(as: 'expediente', except: '')]
    public $nexpediente = '';

    #[Url(as: 'documento', except: '')]
    public $documento = '';

    #[Url(as: 'nombre', except: '')]
    public $nombre = '';

    #[Url(as: 'ruta', except: '')]
    public $ruta = '';

    #[Url(as: 'giro', except: '')]
    public $giro = '';

    #[Url(as: 'asesor', except: '')]
    public $ejecutivo = '';

    /**
     * Filtro de morosidad, por cuotas vencidas del crédito activo con más atraso:
     * '' (todos) | 'aldia' (0-1) | 'naranja' (2) | 'rojo' (3) | 'critico' (4+)
     * | 'ejecucion' (en manos legales, fuera del recuento de mora).
     *
     * Los chips de la vista son enlaces que abren una ventana nueva con este
     * parámetro en la URL; por eso no hay acción de Livewire que los aplique.
     */
    #[Url(as: 'estado', except: '')]
    public $morosidadFiltro = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (! auth()->user()?->can('clientes.eliminar')) {
            abort(403);
        }
        $client = Client::findOrFail($id);
        if ($this->eliminacionBloqueada($client)) {
            return;
        }
        $client->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Cliente desactivado correctamente']);
    }

    // La captura de coordenadas (antes columnas C. y N.) vive ahora en la
    // pestaña GPS de editar cliente: App\Livewire\Clients\Gps, que usa el
    // parser compartido App\Support\Coordenadas.

    public function render()
    {
        $user = auth()->user();

        // 10/10 (Antony): clientes y personas relacionadas (copropietarios) en la MISMA lista.
        $query = Client::query()
            ->where('status', 'active')
            ->with(['asesor:id,name,username', 'headquarter:id,name'])
            // attachments ya no se cuenta: el botón Adjuntos salió del listado (28/08)
            ->withCount('avales');

        if ($user->can('clientes.scope-propio')) {
            $query->where('asesor_id', $user->id);
        }

        // Filtros individuales
        if (trim($this->documento) !== '') {
            $query->where('documento', trim($this->documento));
        }
        if (trim($this->nombre) !== '') {
            // Cada palabra debe aparecer en algún campo de nombre. Así "Obregon
            // Lopez Fernando" (nombre completo, repartido en 3 columnas) calza,
            // en cualquier orden, en vez de exigir la frase entera en una columna.
            foreach (preg_split('/\s+/', trim($this->nombre)) as $word) {
                if ($word === '') {
                    continue;
                }
                $query->where(function ($q) use ($word) {
                    $q->where('nombre', 'like', "%{$word}%")
                        ->orWhere('apellido_pat', 'like', "%{$word}%")
                        ->orWhere('apellido_mat', 'like', "%{$word}%");
                });
            }
        }
        // 10/10 (Antony): las personas relacionadas (copropietarios) no tienen expediente,
        // zona, giro ni asesor propios: el listado les muestra los del titular del préstamo
        // (Client::herenciaDeCopropietario) y estos filtros también los buscan por ahí.
        $propioOTitular = function (callable $condicion) use ($query) {
            $query->where(function ($q) use ($condicion) {
                $q->where(fn ($propio) => $condicion($propio))
                    ->orWhereHas('vehiculosCompartidos.client', fn ($titular) => $condicion($titular))
                    ->orWhereHas('contratosComoCodeudor.credit.client', fn ($titular) => $condicion($titular));
            });
        };
        if (trim($this->nexpediente) !== '') {
            $propioOTitular(fn ($q) => $q->where('expediente', trim($this->nexpediente)));
        }
        if (trim($this->ejecutivo) !== '') {
            if ($this->ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $propioOTitular(fn ($q) => $q->where('asesor_id', $this->ejecutivo));
            }
        }
        if (trim($this->ruta) !== '') {
            $propioOTitular(fn ($q) => $q->where('zona', 'like', '%'.trim($this->ruta).'%'));
        }
        if (trim($this->giro) !== '') {
            $propioOTitular(fn ($q) => $q->where('giro', 'like', '%'.trim($this->giro).'%'));
        }

        // Asesores para dropdown: cualquier usuario activo puede ser asesor responsable.
        $asesores = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // Solo IDs del conjunto filtrado (liviano): base para morosidad y chips.
        $clientIds = (clone $query)->pluck('clients.id')->toArray();

        // Morosidad + expedientes en ejecución: cálculo compartido con el
        // Excel de clientes (App\Support\MorosidadClientes) para que pantalla
        // y archivo cuadren siempre. Reglas documentadas en el helper.
        ['morosidad' => $morosidad, 'enEjecucion' => $enEjecucion] = MorosidadClientes::calcular($clientIds);
        $countEjecucion = count($enEjecucion);

        // Conteos para los chips (sobre TODO el conjunto filtrado, no la página).
        // Los cuatro niveles de mora excluyen a los que están en ejecución.
        $mora = fn (callable $cumple) => count(array_filter(
            $morosidad,
            fn ($v, $id) => $cumple($v) && ! isset($enEjecucion[$id]),
            ARRAY_FILTER_USE_BOTH
        ));
        $countMasde8 = $mora(fn ($v) => $v >= 8);
        $countCritico = $mora(fn ($v) => $v >= 4 && $v <= 7);
        $countRojo = $mora(fn ($v) => $v === 3);
        $countNaranja = $mora(fn ($v) => $v === 2);
        $countAldia = count($clientIds) - $countMasde8 - $countCritico - $countRojo - $countNaranja - $countEjecucion;

        // Chip seleccionado → se restringe la query por IDs del nivel
        if ($this->morosidadFiltro !== '') {
            $query->whereIn('clients.id', MorosidadClientes::idsDelNivel(
                $clientIds, $this->morosidadFiltro, $morosidad, $enEjecucion
            ));
        }

        $totalFiltrados = match ($this->morosidadFiltro) {
            'ejecucion' => $countEjecucion,
            'masde8' => $countMasde8,
            'critico' => $countCritico,
            'rojo' => $countRojo,
            'naranja' => $countNaranja,
            'aldia' => $countAldia,
            default => count($clientIds),
        };

        // Paginación real: solo se hidratan y renderizan los 100 de la página.
        // Orden por expediente EFECTIVO: el copropietario (sin expediente propio) va justo
        // debajo de su titular, con el expediente del dueño del vehículo compartido.
        $expedienteEfectivo = "COALESCE(NULLIF(clients.expediente, ''), (SELECT t.expediente FROM cliente_vehiculo cv"
            .' JOIN vehiculos v ON v.id = cv.vehiculo_id JOIN clients t ON t.id = v.client_id'
            .' WHERE cv.client_id = clients.id ORDER BY cv.id DESC LIMIT 1))';
        $clients = $query->orderByRaw("CAST({$expedienteEfectivo} AS UNSIGNED) ASC, clients.es_relacionado ASC, clients.id ASC")->paginate(100);
        $pageIds = $clients->pluck('id');

        // Estado de créditos de los visibles de la página, para el color del texto:
        //   'activo'    → tiene al menos un crédito vigente
        //   'cancelado' → tuvo créditos y TODOS están cancelados  → se pinta en rojo
        //   'sin'       → nunca tuvo un crédito (cliente nuevo)   → también en rojo (25/09,
        //                 como el legacy: rojo = sin crédito vigente)
        $estadoCreditos = Credit::whereIn('client_id', $pageIds)
            ->selectRaw("client_id, SUM(situacion = 'Activo') AS activos")
            ->groupBy('client_id')
            ->pluck('activos', 'client_id')
            ->map(fn ($activos) => $activos > 0 ? 'activo' : 'cancelado')
            ->toArray();

        $waEnviadosHoy = DB::table('client_notifications')
            ->whereDate('created_at', now()->format('Y-m-d'))
            ->whereIn('client_id', $pageIds)
            ->distinct()
            ->pluck('client_id')
            ->flip()
            ->toArray();

        return view('livewire.clients.index', compact('clients', 'asesores', 'estadoCreditos', 'morosidad', 'countAldia', 'countNaranja', 'countRojo', 'countCritico', 'countMasde8', 'countEjecucion', 'waEnviadosHoy', 'totalFiltrados'));
    }
}
