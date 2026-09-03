<?php

namespace App\Livewire\Legal\Notaria;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Garantia;
use App\Models\TramiteNotarial;
use App\Models\User;
use App\Services\Legal\CajaLegal;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tablero de seguimiento notarial (Área Legal — FASE 3).
 *
 * Reemplaza el Excel del área (documentos varados >2 meses sin que nadie
 * lo vea): contadores por estado clicables, alerta roja de VARADOS
 * (scopeVarados) y orden "varados primero" (abiertos por estado_desde asc).
 *
 * El estado SOLO cambia con "Avanzar" (TramiteNotarial::TRANSICIONES);
 * el alta/edición se hace en un modal inline del mismo componente
 * (mismo patrón que Legal\Vehiculos\Index). Al pasar a 'recogido' sin
 * responsable asignado, un mini-modal pide quién recogió el documento.
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // ── Filtros del listado (persisten en la URL) ─────────────────────

    /** Busca por nombre/documento del cliente, placa de la garantía o descripción. */
    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    /** Clave de TramiteNotarial::ESTADOS o '' (todos). */
    #[Url(as: 'estado', except: '')]
    public $filtroEstado = '';

    /** Clave de TramiteNotarial::TIPOS o '' (todos). */
    #[Url(as: 'tipo', except: '')]
    public $filtroTipo = '';

    /** Solo trámites abiertos varados >= DIAS_VARADO días en su estado. */
    #[Url(as: 'varados', except: false)]
    public bool $soloVarados = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['buscar', 'filtroEstado', 'filtroTipo', 'soloVarados'], true)) {
            $this->resetPage();
        }
    }

    /** Badge clicable de la cabecera: fija el filtro de estado (o lo quita si ya estaba). */
    public function filtrarEstado(string $estado): void
    {
        $this->filtroEstado = $this->filtroEstado === $estado ? '' : $estado;
        $this->resetPage();
    }

    /** Contador rojo de la cabecera: alterna el filtro "solo varados". */
    public function alternarVarados(): void
    {
        $this->soloVarados = ! $this->soloVarados;
        $this->resetPage();
    }

    // ── Transiciones de estado ("Avanzar") ────────────────────────────

    /** Trámite a la espera de responsable para confirmar el recojo. */
    public ?int $recojoTramiteId = null;

    public ?int $recojoResponsableId = null;

    public function avanzar(int $id, string $nuevoEstado): void
    {
        if (! auth()->user()?->can('legal.notaria')) {
            abort(403);
        }

        $tramite = TramiteNotarial::findOrFail($id);

        if (! $tramite->puedeTransicionarA($nuevoEstado)) {
            $actual = TramiteNotarial::ESTADOS[$tramite->estado] ?? $tramite->estado;
            $destino = TramiteNotarial::ESTADOS[$nuevoEstado] ?? $nuevoEstado;
            $this->dispatch('errorAlert', ['message' => "El trámite #{$tramite->id} no puede pasar de «{$actual}» a «{$destino}»."]);

            return;
        }

        // Al recoger debe quedar registrado QUIÉN lo hizo: si el trámite no
        // tiene responsable, se pide en el mini-modal antes de avanzar.
        if ($nuevoEstado === 'recogido' && ! $tramite->responsable_id) {
            $this->recojoTramiteId = $tramite->id;
            $this->recojoResponsableId = null;
            $this->resetErrorBag('recojoResponsableId');
            $this->dispatch('recojo-modal-open');

            return;
        }

        $this->ejecutarTransicion($tramite, $nuevoEstado);
    }

    /** Confirma el recojo asignando responsable (mini-modal). */
    public function confirmarRecojo(): void
    {
        if (! auth()->user()?->can('legal.notaria')) {
            abort(403);
        }
        if (! $this->recojoTramiteId) {
            return;
        }

        $this->validate(
            ['recojoResponsableId' => ['required', 'integer', Rule::exists('users', 'id')]],
            [
                'recojoResponsableId.required' => 'Indica quién recogió el documento.',
                'recojoResponsableId.exists' => 'El responsable seleccionado no es válido.',
            ],
        );

        $tramite = TramiteNotarial::findOrFail($this->recojoTramiteId);

        if (! $tramite->puedeTransicionarA('recogido')) {
            $this->dispatch('errorAlert', ['message' => "El trámite #{$tramite->id} ya no admite pasar a «Recogido»."]);
            $this->dispatch('recojo-modal-close');
            $this->recojoTramiteId = null;

            return;
        }

        $tramite->update(['responsable_id' => $this->recojoResponsableId]);
        $this->ejecutarTransicion($tramite, 'recogido');

        $this->dispatch('recojo-modal-close');
        $this->recojoTramiteId = null;
        $this->recojoResponsableId = null;
    }

    /** Avanza el estado (ya validado) registrando auditoría y aviso. */
    private function ejecutarTransicion(TramiteNotarial $tramite, string $nuevoEstado): void
    {
        $tramite->avanzarA($nuevoEstado);

        $label = TramiteNotarial::ESTADOS[$nuevoEstado] ?? $nuevoEstado;
        Audit::log("Cambió el trámite notarial #{$tramite->id} a {$label}", $tramite, [
            'estado' => $nuevoEstado,
            'estado_desde' => $tramite->estado_desde?->toDateString(),
        ]);

        $this->dispatch('successAlert', ['message' => "Trámite #{$tramite->id} pasó a «{$label}»."]);
    }

    // ── Formulario del modal (crear/editar) ───────────────────────────

    /** null = creando; id = editando ese trámite (el estado NO se edita ahí). */
    public ?int $editingId = null;

    public string $tipo = 'contrato_sigm';

    public ?int $garantia_id = null;

    /** Rótulo de la garantía elegida, solo para mostrar en el modal. */
    public string $garantiaLabel = '';

    public string $garantiaBusqueda = '';

    public ?int $client_id = null;

    /** Nombre del cliente elegido, solo para mostrar en el modal. */
    public string $clienteNombre = '';

    public string $clienteBusqueda = '';

    public string $descripcion = '';

    public string $notaria = '';

    /** Solo al crear: estado de partida del flujo. */
    public string $estadoInicial = 'firmado_oficina';

    /** Solo al crear: fecha de estado_desde (default hoy). */
    public string $fecha = '';

    public $costo = null;

    public string $ubicacion_archivo = '';

    public string $nota = '';

    public ?int $responsable_id = null;

    protected function rules(): array
    {
        $reglas = [
            'tipo' => ['required', Rule::in(array_keys(TramiteNotarial::TIPOS))],
            'garantia_id' => ['nullable', 'integer', Rule::exists('garantias', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'descripcion' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $this->garantia_id)],
            'notaria' => ['nullable', 'string', 'max:120'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'ubicacion_archivo' => ['nullable', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'responsable_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];

        if (! $this->editingId) {
            $reglas['estadoInicial'] = ['required', Rule::in(['firmado_oficina', 'en_notaria'])];
            $reglas['fecha'] = ['required', 'date', 'before_or_equal:'.now()->toDateString()];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de trámite.',
            'tipo.in' => 'El tipo de trámite no es válido.',
            'garantia_id.exists' => 'La garantía seleccionada ya no existe.',
            'client_id.exists' => 'El cliente seleccionado ya no existe.',
            'descripcion.required' => 'Sin garantía asociada, la descripción es obligatoria (identifica el documento).',
            'descripcion.max' => 'La descripción no debe exceder 255 caracteres.',
            'notaria.max' => 'El nombre de la notaría no debe exceder 120 caracteres.',
            'estadoInicial.required' => 'Indica el estado inicial del trámite.',
            'estadoInicial.in' => 'Un trámite nuevo solo puede partir de «Firmado en oficina» o «En notaría».',
            'fecha.required' => 'Indica la fecha del estado inicial.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'costo.numeric' => 'El costo debe ser numérico.',
            'costo.min' => 'El costo no puede ser negativo.',
            'costo.max' => 'El costo excede el máximo permitido.',
            'ubicacion_archivo.max' => 'La ubicación del archivo no debe exceder 255 caracteres.',
            'nota.max' => 'La nota no puede exceder los 2000 caracteres.',
            'responsable_id.exists' => 'El responsable seleccionado no es válido.',
        ];
    }

    /** Deja el formulario en blanco (valores por defecto de un alta). */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'editingId', 'tipo', 'garantia_id', 'garantiaLabel', 'garantiaBusqueda',
            'client_id', 'clienteNombre', 'clienteBusqueda', 'descripcion', 'notaria',
            'estadoInicial', 'fecha', 'costo', 'ubicacion_archivo', 'nota', 'responsable_id',
        ]);
        $this->fecha = now()->toDateString();
        $this->resetErrorBag();
    }

    /** Abre el modal para registrar un trámite nuevo. */
    public function nuevo(): void
    {
        if (! auth()->user()?->can('legal.notaria')) {
            abort(403);
        }

        $this->limpiarFormulario();
        $this->dispatch('tramite-modal-open');
    }

    /** Abre el modal con los datos del trámite a editar (el estado no se toca). */
    public function editar(int $id): void
    {
        if (! auth()->user()?->can('legal.notaria')) {
            abort(403);
        }

        $tramite = TramiteNotarial::with([
            'client:id,nombre,apellido_pat,apellido_mat,documento',
            'garantia.vehiculos',
        ])->findOrFail($id);

        $this->limpiarFormulario();
        $this->editingId = $tramite->id;
        $this->tipo = $tramite->tipo;
        $this->garantia_id = $tramite->garantia_id;
        $this->garantiaLabel = $tramite->garantia ? $this->rotuloGarantia($tramite->garantia) : '';
        $this->client_id = $tramite->client_id;
        $this->clienteNombre = $tramite->client?->fullName() ?? '';
        $this->descripcion = (string) $tramite->descripcion;
        $this->notaria = (string) $tramite->notaria;
        $this->costo = $tramite->costo;
        $this->ubicacion_archivo = (string) $tramite->ubicacion_archivo;
        $this->nota = (string) $tramite->nota;
        $this->responsable_id = $tramite->responsable_id;

        $this->dispatch('tramite-modal-open');
    }

    /** Asocia la garantía elegida del buscador y fija su cliente automáticamente. */
    public function seleccionarGarantia(int $id): void
    {
        $garantia = Garantia::with([
            'client:id,nombre,apellido_pat,apellido_mat,documento',
            'vehiculos',
        ])->find($id);

        if (! $garantia) {
            return;
        }

        $this->garantia_id = $garantia->id;
        $this->garantiaLabel = $this->rotuloGarantia($garantia);
        $this->client_id = $garantia->client_id;
        $this->clienteNombre = $garantia->client?->fullName() ?? '';
        $this->garantiaBusqueda = '';
        $this->clienteBusqueda = '';
        $this->resetErrorBag(['garantia_id', 'client_id', 'descripcion']);
    }

    /** Quita la garantía asociada (y el cliente que fijó). */
    public function quitarGarantia(): void
    {
        $this->garantia_id = null;
        $this->garantiaLabel = '';
        $this->client_id = null;
        $this->clienteNombre = '';
    }

    /** Asocia el cliente elegido del buscador (trámites sin garantía). */
    public function seleccionarCliente(int $id): void
    {
        $client = Client::where('status', 'active')
            ->select('id', 'nombre', 'apellido_pat', 'apellido_mat')
            ->find($id);
        if (! $client) {
            return;
        }

        $this->client_id = $client->id;
        $this->clienteNombre = $client->fullName();
        $this->clienteBusqueda = '';
        $this->resetErrorBag('client_id');
    }

    /** Quita el cliente asociado (para volver a buscar otro). */
    public function quitarCliente(): void
    {
        if ($this->garantia_id) {
            return; // con garantía, el cliente lo fija ella
        }
        $this->client_id = null;
        $this->clienteNombre = '';
    }

    /** Crea o actualiza el trámite según $editingId. */
    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.notaria')) {
            abort(403);
        }

        // Normaliza vacíos a null ANTES de validar (para nullable/requiredIf)
        $this->descripcion = trim($this->descripcion);
        $this->notaria = trim($this->notaria);
        $this->ubicacion_archivo = trim($this->ubicacion_archivo);
        $this->nota = trim($this->nota);
        $this->costo = ($this->costo !== null && $this->costo !== '') ? $this->costo : null;

        $this->validate();

        $data = [
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion !== '' ? $this->descripcion : null,
            'notaria' => $this->notaria !== '' ? $this->notaria : null,
            'costo' => $this->costo !== null ? round((float) $this->costo, 2) : null,
            'ubicacion_archivo' => $this->ubicacion_archivo !== '' ? $this->ubicacion_archivo : null,
            'nota' => $this->nota !== '' ? $this->nota : null,
            'responsable_id' => $this->responsable_id,
        ];

        if ($this->editingId) {
            // La garantía/cliente y el ESTADO no se cambian al editar:
            // el estado solo avanza por transiciones ("Avanzar").
            $tramite = TramiteNotarial::findOrFail($this->editingId);
            $tramite->update($data);
            $this->sincronizarEgresoLegal($tramite);

            Audit::log("Editó el trámite notarial #{$tramite->id}", $tramite);
            $mensaje = 'Trámite notarial actualizado correctamente.';
        } else {
            $data['garantia_id'] = $this->garantia_id;
            $data['client_id'] = $this->client_id;
            $data['estado'] = $this->estadoInicial;
            $data['estado_desde'] = $this->fecha;
            if ($this->estadoInicial === 'en_notaria') {
                $data['fecha_ingreso_notaria'] = $this->fecha; // mismo hito que avanzarA()
            }

            $tramite = TramiteNotarial::create($data);
            $this->sincronizarEgresoLegal($tramite);

            $tipoLabel = TramiteNotarial::TIPOS[$tramite->tipo] ?? $tramite->tipo;
            $estadoLabel = TramiteNotarial::ESTADOS[$tramite->estado] ?? $tramite->estado;
            Audit::log(
                "Registró el trámite notarial #{$tramite->id} ({$tipoLabel}) en «{$estadoLabel}»",
                $tramite,
                ['garantia_id' => $tramite->garantia_id, 'client_id' => $tramite->client_id, 'notaria' => $tramite->notaria],
            );
            $mensaje = 'Trámite notarial registrado correctamente.';
        }

        $this->dispatch('tramite-modal-close');
        $this->limpiarFormulario();
        $this->dispatch('successAlert', ['message' => $mensaje]);
    }

    /**
     * Egreso automático del costo notarial en la caja legal (caja=4).
     *
     * Al crear/editar: con costo > 0 y sin asiento previo, crea el Expense y
     * guarda su id en expense_id; si el trámite ya tiene asiento y el costo
     * cambió, sincroniza el total (y el detalle) del Expense vinculado.
     * Costo null/0 no genera asiento ni toca el existente.
     */
    private function sincronizarEgresoLegal(TramiteNotarial $tramite): void
    {
        $costo = $tramite->costo !== null ? (float) $tramite->costo : 0.0;
        if ($costo <= 0) {
            return;
        }

        $tipoLabel = TramiteNotarial::TIPOS[$tramite->tipo] ?? $tramite->tipo;
        $detalle = collect([
            "Trámite notarial #{$tramite->id}",
            $tipoLabel,
            $tramite->client?->fullName() ?: $tramite->descripcion,
        ])->filter()->implode(' — ');

        if (! $tramite->expense_id) {
            $egreso = CajaLegal::egreso(
                'Gasto notarial',
                $costo,
                $detalle,
                $tramite->estado_desde?->toDateString(),
            );
            $tramite->update(['expense_id' => $egreso->id]);

            return;
        }

        $egreso = Expense::find($tramite->expense_id);
        if ($egreso && abs((float) $egreso->total - $costo) >= 0.01) {
            $egreso->update(['total' => $costo, 'detail' => $detalle]);
        }
    }

    /** "Garantía #N — Crédito #X — placas — cliente" para el modal. */
    private function rotuloGarantia(Garantia $garantia): string
    {
        $placas = $garantia->vehiculos->pluck('placa')->filter()->implode(', ');

        return collect([
            "Garantía #{$garantia->id}",
            $garantia->credit_id ? "Crédito #{$garantia->credit_id}" : null,
            $placas !== '' ? $placas : null,
            $garantia->client?->fullName(),
        ])->filter()->implode(' — ');
    }

    public function render()
    {
        // ── Contadores de cabecera (panorama global, sin filtros) ──
        $porEstado = TramiteNotarial::query()
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
        $varadosCount = TramiteNotarial::varados()->count();

        // ── Listado filtrado ──
        $query = TramiteNotarial::query()->with([
            'client:id,nombre,apellido_pat,apellido_mat,documento',
            'garantia.vehiculos',
            'responsable:id,name',
        ]);

        // Búsqueda: nombre/documento del cliente, placa de la garantía o descripción
        if (trim($this->buscar) !== '') {
            $term = trim($this->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('descripcion', 'like', "%{$term}%")
                    ->orWhereHas('garantia.vehiculos', fn ($v) => $v->where('placa', 'like', "%{$term}%"))
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

        if (array_key_exists($this->filtroEstado, TramiteNotarial::ESTADOS)) {
            $query->where('estado', $this->filtroEstado);
        }
        if (array_key_exists($this->filtroTipo, TramiteNotarial::TIPOS)) {
            $query->where('tipo', $this->filtroTipo);
        }
        if ($this->soloVarados) {
            $query->varados();
        }

        // Orden: abiertos primero (los más antiguos en su estado arriba =
        // varados a la cabeza), cerrados al final por id descendente.
        $abiertos = "'".implode("','", TramiteNotarial::ESTADOS_ABIERTOS)."'";
        $tramites = $query
            ->orderByRaw("(estado IN ({$abiertos})) DESC")
            ->orderByRaw("CASE WHEN estado IN ({$abiertos}) THEN estado_desde END ASC")
            ->orderByDesc('id')
            ->paginate(25);

        // ── Datos auxiliares del modal ──
        $notarias = TramiteNotarial::whereNotNull('notaria')
            ->distinct()->orderBy('notaria')->pluck('notaria');

        $usuarios = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        // Buscador de garantías del modal (por placa, cliente o N° de garantía)
        $garantiasEncontradas = collect();
        $termGarantia = trim($this->garantiaBusqueda);
        if (! $this->garantia_id && ! $this->editingId && mb_strlen($termGarantia) >= 2) {
            $garantiasEncontradas = Garantia::with([
                'client:id,nombre,apellido_pat,apellido_mat,documento',
                'vehiculos',
            ])
                ->where(function ($q) use ($termGarantia) {
                    if (ctype_digit($termGarantia)) {
                        $q->orWhere('id', (int) $termGarantia);
                    }
                    $q->orWhereHas('vehiculos', fn ($v) => $v->where('placa', 'like', "%{$termGarantia}%"))
                        ->orWhereHas('client', function ($c) use ($termGarantia) {
                            $c->where('documento', 'like', "%{$termGarantia}%")
                                ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$termGarantia}%"])
                                ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$termGarantia}%"]);
                        });
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        // Buscador de clientes del modal (trámites sin garantía)
        $clientesEncontrados = collect();
        $termCliente = trim($this->clienteBusqueda);
        if (! $this->client_id && ! $this->editingId && mb_strlen($termCliente) >= 2) {
            $clientesEncontrados = Client::query()
                ->where('status', 'active')
                ->where(function ($q) use ($termCliente) {
                    $q->where('documento', 'like', "%{$termCliente}%")
                        ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$termCliente}%"])
                        ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$termCliente}%"]);
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get(['id', 'nombre', 'apellido_pat', 'apellido_mat', 'documento']);
        }

        return view('livewire.legal.notaria.index', compact(
            'tramites', 'porEstado', 'varadosCount', 'notarias', 'usuarios',
            'garantiasEncontradas', 'clientesEncontrados',
        ));
    }
}
