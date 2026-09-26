<?php

namespace App\Livewire\Audit;

use App\Models\ActivityLog as Activity;
use App\Models\Client;
use App\Models\Credit;
use App\Models\ExpedienteJudicial;
use App\Models\Garantia;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Visor del módulo de Auditoría (acceso solo rol director, vía route role:director).
 * Lista las acciones clave registradas en activity_log (log_name = 'auditoria'):
 * los registros manuales de Audit::log (texto libre con verbo inicial) y los
 * automáticos por modelo del trait Auditable (event created/updated/deleted
 * con el antes/después en attribute_changes). El botón "Ver" abre el detalle.
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /**
     * Clasificación de acciones (réplica del filtro de acciones de newtaxivan).
     * Los registros automáticos traen la columna `event`; los manuales de
     * Audit::log solo el texto, con convención de verbo al inicio, así que el
     * tipo se deriva de `event` O del prefijo de la descripción — cubre tanto
     * los registros existentes como los nuevos, sin migración. Si se agrega un
     * verbo nuevo en un Audit::log, añadirlo aquí para que filtre y lleve su badge.
     */
    public const ACCIONES = [
        'creacion' => [
            'label' => 'Creación', 'badge' => 'success', 'event' => 'created',
            'verbos' => ['Creó', 'Registró', 'Agregó', 'Aperturó', 'Refinanció', 'Generó', 'Vinculó', 'Adjuntó'],
        ],
        'edicion' => [
            'label' => 'Edición', 'badge' => 'warning text-dark', 'event' => 'updated',
            'verbos' => ['Editó', 'Actualizó', 'Ajustó', 'Reactivó', 'Re-activó', 'Cambió', 'Cambio de', 'Marcó', 'Condonó'],
        ],
        'eliminacion' => [
            'label' => 'Eliminación', 'badge' => 'danger', 'event' => 'deleted',
            'verbos' => ['Eliminó', 'Anuló', 'Desactivó', 'Revirtió', 'Borró', 'Quitó'],
        ],
        'acceso' => [
            'label' => 'Acceso', 'badge' => 'secondary', 'event' => null,
            'verbos' => ['Inicio de sesión', 'Cerró sesión', 'Intento'],
        ],
    ];

    /** Ruta de la ficha de cada modelo auditado (solo las que existen en routes/web.php). */
    public const FICHAS = [
        Client::class => 'clients.show',
        Credit::class => 'credits.show',
        Garantia::class => 'legal.garantias.show',
        ExpedienteJudicial::class => 'legal.expedientes.show',
    ];

    #[Url(as: 'accion', except: '')]
    public string $accion = '';

    #[Url(as: 'modulo', except: '')]
    public string $modulo = '';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    #[Url(as: 'usuario', except: '')]
    public $causer = '';

    #[Url(as: 'buscar', except: '')]
    public string $buscar = '';

    /** Detalle abierto en el modal (ya preparado para la vista), o null. */
    public ?array $detalle = null;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset(['desde', 'hasta', 'causer', 'buscar', 'accion', 'modulo']);
        $this->resetPage();
    }

    /**
     * Usuarios del filtro: una consulta por render (sin persist: con el cache
     * en base de datos la colección volvía rehidratada como texto y la vista
     * caía con "Attempt to read property id on string").
     */
    #[Computed]
    public function usuarios(): Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'username']);
    }

    /** Clase → nombre legible de cada modelo auditado (filtro Módulo y columna Afectado). */
    #[Computed]
    public function modulos(): array
    {
        return config('auditoria.modulos', []);
    }

    /**
     * Tipo de acción de un registro: por el verbo inicial de la descripción y,
     * si no arranca con un verbo conocido, por la columna `event`. Null si no
     * se puede clasificar.
     */
    public function clasificar(string $descripcion, ?string $event = null): ?string
    {
        foreach (self::ACCIONES as $tipo => $cfg) {
            foreach ($cfg['verbos'] as $verbo) {
                if (str_starts_with($descripcion, $verbo)) {
                    return $tipo;
                }
            }
        }
        if ($event !== null) {
            foreach (self::ACCIONES as $tipo => $cfg) {
                if ($cfg['event'] === $event) {
                    return $tipo;
                }
            }
        }

        return null;
    }

    /** Nombre legible del modelo afectado (config auditoria.modulos, o el nombre de la clase). */
    public function etiquetaModulo(?string $tipo): ?string
    {
        if ($tipo === null || $tipo === '') {
            return null;
        }

        return $this->modulos[$tipo] ?? class_basename($tipo);
    }

    /** URL de la ficha del modelo afectado, si ese modelo tiene una. */
    public static function urlFicha(?string $tipo, mixed $id): ?string
    {
        $ruta = self::FICHAS[$tipo] ?? null;
        if ($ruta === null || ! $id || ! Route::has($ruta)) {
            return null;
        }

        return route($ruta, $id);
    }

    /**
     * Nombre legible de una columna en el detalle: config auditoria.etiquetas
     * por modelo, o el nombre "humanizado" (fecha_prestamo → Fecha prestamo).
     */
    public static function etiquetaColumna(?string $tipo, string $columna): string
    {
        $etiqueta = $tipo ? config("auditoria.etiquetas.{$tipo}.{$columna}") : null;

        return is_string($etiqueta) && $etiqueta !== ''
            ? $etiqueta
            : ucfirst(str_replace('_', ' ', $columna));
    }

    /** Valor tal como se muestra en el detalle: null → "—", booleanos → Sí/No, arreglos → JSON legible. */
    public static function formatearValor(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }
        if (is_array($valor) || $valor instanceof Collection) {
            return (string) json_encode(
                $valor instanceof Collection ? $valor->toArray() : $valor,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return (string) $valor;
    }

    /** Abre el modal con el detalle de un registro. */
    public function ver(int $id): void
    {
        $actividad = Activity::query()
            ->where('log_name', Audit::LOG)
            ->with('causer')
            ->findOrFail($id);

        $this->detalle = $this->prepararDetalle($actividad);
        $this->dispatch('audit-detalle-open');
    }

    public function cerrarDetalle(): void
    {
        $this->detalle = null;
        $this->dispatch('audit-detalle-close');
    }

    /** Arma el arreglo que pinta el modal a partir de la fila de activity_log. */
    private function prepararDetalle(Activity $a): array
    {
        $props = collect($a->properties ?? [])->toArray();
        $contexto = is_array($props['contexto'] ?? null) ? $props['contexto'] : [];
        unset($props['contexto']);
        $cambios = collect($a->attribute_changes ?? [])->toArray();
        $nuevos = is_array($a->new_data) ? $a->new_data : (is_array($cambios['attributes'] ?? null) ? $cambios['attributes'] : null);
        $viejos = is_array($a->old_data) ? $a->old_data : (is_array($cambios['old'] ?? null) ? $cambios['old'] : null);

        // Usuario: la copia guardada en el contexto manda (sobrevive a cambios o
        // borrados del usuario); si no está, el causer.
        // 26/09: primero las columnas propias (estructura newtaxivan), luego el contexto JSON.
        $ctxUsuario = is_array($contexto['usuario'] ?? null) ? $contexto['usuario'] : null;
        if ($a->user_name !== null) {
            $ctxUsuario = ['nombre' => $a->user_name, 'username' => $ctxUsuario['username'] ?? ($a->causer->username ?? null), 'rol' => $a->user_role];
        }
        $usuario = $ctxUsuario ? [
            'nombre' => $ctxUsuario['nombre'] ?? null,
            'username' => $ctxUsuario['username'] ?? null,
            'rol' => $ctxUsuario['rol'] ?? null,
        ] : ($a->causer ? [
            'nombre' => $a->causer->name ?? null,
            'username' => $a->causer->username ?? null,
            'rol' => method_exists($a->causer, 'getRoleNames') ? $a->causer->getRoleNames()->first() : null,
        ] : null);

        // Modo del detalle: por event; si no lo hay, por los bloques presentes.
        $modo = match ($a->event) {
            'created' => 'created',
            'deleted' => 'deleted',
            'updated' => 'updated',
            default => match (true) {
                $nuevos !== null && $viejos !== null => 'updated',
                $nuevos !== null => 'created',
                $viejos !== null => 'deleted',
                default => null,
            },
        };

        $tipo = $a->subject_type;
        $valores = [];
        $antesDespues = [];
        if ($modo === 'updated') {
            foreach ($nuevos ?? [] as $col => $nuevo) {
                $antesDespues[] = [
                    'campo' => self::etiquetaColumna($tipo, (string) $col),
                    'antes' => self::formatearValor($viejos[$col] ?? null),
                    'despues' => self::formatearValor($nuevo),
                ];
            }
        } elseif ($modo === 'created' || $modo === 'deleted') {
            foreach (($modo === 'created' ? $nuevos : $viejos) ?? [] as $col => $valor) {
                $valores[] = [
                    'campo' => self::etiquetaColumna($tipo, (string) $col),
                    'valor' => self::formatearValor($valor),
                ];
            }
        }

        $propiedades = [];
        foreach ($props as $clave => $valor) {
            $propiedades[] = [
                'campo' => ucfirst(str_replace('_', ' ', (string) $clave)),
                'valor' => self::formatearValor($valor),
            ];
        }

        $accion = $this->clasificar($a->description, $a->event);

        return [
            'id' => $a->id,
            'fecha' => $a->created_at?->format('d/m/Y H:i:s'),
            'event' => $a->event,
            'modo' => $modo,
            'accion' => $accion,
            'accion_label' => $accion ? self::ACCIONES[$accion]['label'] : null,
            'accion_badge' => $accion ? self::ACCIONES[$accion]['badge'] : null,
            'descripcion' => $a->description,
            'usuario' => $usuario,
            'ip' => $a->ip_address ?? $contexto['ip'] ?? null,
            'navegador' => $a->user_agent ?? $contexto['agente'] ?? null,
            'ruta' => $contexto['ruta'] ?? null,
            'modulo' => $this->etiquetaModulo($tipo),
            'subject_id' => $a->subject_id,
            'subject_url' => self::urlFicha($tipo, $a->subject_id),
            'cambios' => $antesDespues,
            'valores' => $valores,
            'propiedades' => $propiedades,
        ];
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
        if ($this->modulo !== '') {
            $query->where('subject_type', $this->modulo);
        }
        if (trim($this->buscar) !== '') {
            $query->where('description', 'like', '%'.trim($this->buscar).'%');
        }
        if (isset(self::ACCIONES[$this->accion])) {
            $cfg = self::ACCIONES[$this->accion];
            $query->where(function ($q) use ($cfg) {
                if ($cfg['event'] !== null) {
                    $q->orWhere('event', $cfg['event']);
                }
                foreach ($cfg['verbos'] as $verbo) {
                    $q->orWhere('description', 'like', $verbo.'%');
                }
            });
        }

        $logs = $query->paginate(30);

        return view('livewire.audit.index', compact('logs'));
    }
}
