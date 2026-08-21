<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use App\Support\Audit;
use App\Support\MorosidadClientes;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

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

    // Modal de coordenadas (Casa / Negocio)
    public ?int $coordClientId = null;

    public string $coordTipo = '';        // 'casa' | 'negocio'

    public ?string $coordClientName = null;

    public string $coordPaste = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (! auth()->user()?->can('clientes.eliminar')) {
            abort(403);
        }
        Client::findOrFail($id)->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Cliente desactivado correctamente']);
    }

    /** Mismo gate que el botón Guardar de /clients/edit. */
    private function puedeGuardarCoords(): bool
    {
        return ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    public function openCoord(int $id, string $tipo): void
    {
        if (! $this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);

            return;
        }
        if (! in_array($tipo, ['casa', 'negocio'], true)) {
            return;
        }

        $client = Client::select('id', 'apellido_pat', 'apellido_mat', 'nombre')->findOrFail($id);

        $this->coordClientId = $client->id;
        $this->coordTipo = $tipo;
        $this->coordClientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->coordPaste = '';
        $this->resetErrorBag();

        $this->dispatch('coord-open');
    }

    public function saveCoord(): void
    {
        if (! $this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);

            return;
        }
        if (! $this->coordClientId || ! in_array($this->coordTipo, ['casa', 'negocio'], true)) {
            return;
        }

        $coords = $this->parseCoords($this->coordPaste);
        if (! $coords) {
            $this->dispatch('errorAlert', ['message' => 'Formato inválido. Pega las coordenadas como: -12.014431, -76.824936']);

            return;
        }
        [$lat, $lng] = $coords;

        $client = Client::findOrFail($this->coordClientId);
        if ($this->coordTipo === 'casa') {
            $client->update(['latitud' => $lat, 'longitud' => $lng]);
            $etiqueta = 'Casa';
        } else {
            $client->update(['latitud2' => $lat, 'longitud2' => $lng]);
            $etiqueta = 'Negocio';
        }

        Audit::log("Registró coordenadas ({$etiqueta}) del cliente ".$client->fullName(), $client);

        $this->dispatch('coord-close');
        $this->reset(['coordClientId', 'coordTipo', 'coordClientName', 'coordPaste']);
        $this->dispatch('successAlert', ['message' => "Coordenadas de {$etiqueta} guardadas correctamente"]);
    }

    /**
     * Normaliza un texto pegado a [lat, lng]. Acepta "lat, lng", separados por
     * espacio, paréntesis o incluso una URL de Google Maps. Solo considera
     * números con decimales (ignora enteros sueltos como el zoom "17z" o números
     * de dirección). Devuelve null si no hay 2 coordenadas válidas en rango.
     */
    private function parseCoords(string $raw): ?array
    {
        preg_match_all('/-?\d+\.\d+/', trim($raw), $m);
        $nums = $m[0] ?? [];
        if (count($nums) < 2) {
            return null;
        }

        $lat = (float) $nums[0];
        $lng = (float) $nums[1];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [round($lat, 7), round($lng, 7)];
    }

    public function render()
    {
        $user = auth()->user();

        $query = Client::query()
            ->where('status', 'active')
            ->with(['asesor:id,name,username', 'headquarter:id,name'])
            ->withCount(['avales', 'attachments']);

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
        if (trim($this->nexpediente) !== '') {
            $query->where('expediente', trim($this->nexpediente));
        }
        if (trim($this->ejecutivo) !== '') {
            if ($this->ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $query->where('asesor_id', $this->ejecutivo);
            }
        }
        if (trim($this->ruta) !== '') {
            $query->where('zona', 'like', '%'.trim($this->ruta).'%');
        }
        if (trim($this->giro) !== '') {
            $query->where('giro', 'like', '%'.trim($this->giro).'%');
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
        $clients = $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->paginate(100);
        $pageIds = $clients->pluck('id');

        // Estado de créditos de los visibles de la página, para el color del texto:
        //   'activo'    → tiene al menos un crédito vigente
        //   'cancelado' → tuvo créditos y TODOS están cancelados  → se pinta en rojo
        //   'sin'       → nunca tuvo un crédito (no es lo mismo, no se pinta)
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

        $puedeCoords = $this->puedeGuardarCoords();

        return view('livewire.clients.index', compact('clients', 'asesores', 'estadoCreditos', 'morosidad', 'puedeCoords', 'countAldia', 'countNaranja', 'countRojo', 'countCritico', 'countMasde8', 'countEjecucion', 'waEnviadosHoy', 'totalFiltrados'));
    }
}
