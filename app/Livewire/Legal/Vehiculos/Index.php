<?php

namespace App\Livewire\Legal\Vehiculos;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado y mantenimiento de vehículos (Área Legal, garantías SIGM).
 *
 * El alta/edición se hace en un modal inline del mismo componente:
 * props del formulario + $editingId (null = crear). El modal se abre y
 * cierra con eventos de navegador ('vehiculo-modal-open'/'-close') que
 * escucha Alpine sobre la instancia de Bootstrap Modal (mismo patrón que
 * Clients\NotificationsModal).
 */
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // ── Filtros del listado (persisten en la URL) ─────────────────────

    /** Busca por placa, marca, modelo o nombre/documento del cliente. */
    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    #[Url(as: 'estado', except: '')]
    public $filtroEstado = '';

    #[Url(as: 'propietario', except: '')]
    public $filtroPropietario = '';

    /** Solo vehículos activos con algún documento vencido o venciendo en <= 30 días. */
    #[Url(as: 'vencidos', except: false)]
    public bool $filtroVencidos = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['buscar', 'filtroEstado', 'filtroPropietario', 'filtroVencidos'], true)) {
            $this->resetPage();
        }
    }

    // ── Formulario del modal (crear/editar) ───────────────────────────

    /** null = creando; id = editando ese vehículo. */
    public ?int $editingId = null;

    public ?int $client_id = null;

    /** Nombre del cliente seleccionado, solo para mostrarlo en el modal. */
    public string $clienteNombre = '';

    /** Texto del buscador de clientes dentro del modal. */
    public string $clienteBusqueda = '';

    public string $propietario_tipo = 'cliente';

    public string $propietario_nombre = '';

    public string $propietario_documento = '';

    public string $placa = '';

    public string $marca = '';

    public string $modelo = '';

    public string $nro_motor = '';

    public string $nro_serie = '';

    public string $categoria = '';

    public $anio = null;

    public string $carroceria = '';

    public string $color = '';

    public string $combustible = '';

    public $valor = null;

    public string $estado = 'activo';

    public string $observaciones = '';

    // Vencimientos documentarios (fechas 'Y-m-d' de los inputs date)

    public $soat_vence = null;

    public $revision_tecnica_vence = null;

    public $habilitacion_atu_vence = null;

    /** Reglas dinámicas: la placa única debe ignorar el propio registro al editar. */
    protected function rules(): array
    {
        return [
            'client_id' => [
                'nullable', 'integer', Rule::exists('clients', 'id'),
                Rule::requiredIf($this->propietario_tipo === 'cliente'),
            ],
            'propietario_tipo' => ['required', Rule::in(array_keys(Vehiculo::PROPIETARIO_TIPOS))],
            'propietario_nombre' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($this->propietario_tipo === 'tercero'),
            ],
            'propietario_documento' => ['nullable', 'string', 'max:15'],
            'placa' => [
                'required', 'string', 'max:10',
                Rule::unique('vehiculos', 'placa')->ignore($this->editingId),
            ],
            'marca' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'nro_motor' => ['nullable', 'string', 'max:30'],
            'nro_serie' => ['nullable', 'string', 'max:30'],
            'categoria' => ['nullable', 'string', 'max:30'],
            'anio' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'carroceria' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'combustible' => ['nullable', 'string', 'max:30'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(array_keys(Vehiculo::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'soat_vence' => ['nullable', 'date'],
            'revision_tecnica_vence' => ['nullable', 'date'],
            'habilitacion_atu_vence' => ['nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'client_id.required' => 'Selecciona el cliente propietario del vehículo.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
            'propietario_tipo.required' => 'Indica el tipo de propietario.',
            'propietario_tipo.in' => 'El tipo de propietario no es válido.',
            'propietario_nombre.required' => 'Indica el nombre del tercero propietario.',
            'propietario_nombre.max' => 'El nombre del propietario no debe exceder 255 caracteres.',
            'propietario_documento.max' => 'El documento del propietario no debe exceder 15 caracteres.',
            'placa.required' => 'La placa es obligatoria.',
            'placa.max' => 'La placa no debe exceder 10 caracteres.',
            'placa.unique' => 'Ya existe un vehículo registrado con esa placa.',
            'anio.integer' => 'El año debe ser un número.',
            'anio.between' => 'El año debe estar entre 1950 y '.(now()->year + 1).'.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo.',
            'estado.required' => 'Indica el estado del vehículo.',
            'estado.in' => 'El estado no es válido.',
            'observaciones.max' => 'Las observaciones son demasiado largas.',
            'soat_vence.date' => 'La fecha de vencimiento del SOAT no es válida.',
            'revision_tecnica_vence.date' => 'La fecha de vencimiento de la revisión técnica no es válida.',
            'habilitacion_atu_vence.date' => 'La fecha de vencimiento de la habilitación ATU no es válida.',
        ];
    }

    /** Deja el formulario en blanco (valores por defecto de un alta). */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'editingId', 'client_id', 'clienteNombre', 'clienteBusqueda',
            'propietario_tipo', 'propietario_nombre', 'propietario_documento',
            'placa', 'marca', 'modelo', 'nro_motor', 'nro_serie', 'categoria',
            'anio', 'carroceria', 'color', 'combustible', 'valor', 'estado',
            'observaciones', 'soat_vence', 'revision_tecnica_vence',
            'habilitacion_atu_vence',
        ]);
        $this->resetErrorBag();
    }

    /** Abre el modal para registrar un vehículo nuevo. */
    public function nuevo(): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $this->limpiarFormulario();
        $this->dispatch('vehiculo-modal-open');
    }

    /** Abre el modal con los datos del vehículo a editar. */
    public function editar(int $id): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $vehiculo = Vehiculo::with('client:id,nombre,apellido_pat,apellido_mat,documento')->findOrFail($id);

        $this->limpiarFormulario();
        $this->editingId = $vehiculo->id;
        $this->client_id = $vehiculo->client_id;
        $this->clienteNombre = $vehiculo->client?->fullName() ?? '';
        $this->propietario_tipo = $vehiculo->propietario_tipo;
        $this->propietario_nombre = (string) $vehiculo->propietario_nombre;
        $this->propietario_documento = (string) $vehiculo->propietario_documento;
        $this->placa = $vehiculo->placa;
        $this->marca = (string) $vehiculo->marca;
        $this->modelo = (string) $vehiculo->modelo;
        $this->nro_motor = (string) $vehiculo->nro_motor;
        $this->nro_serie = (string) $vehiculo->nro_serie;
        $this->categoria = (string) $vehiculo->categoria;
        $this->anio = $vehiculo->anio;
        $this->carroceria = (string) $vehiculo->carroceria;
        $this->color = (string) $vehiculo->color;
        $this->combustible = (string) $vehiculo->combustible;
        $this->valor = $vehiculo->valor;
        $this->estado = $vehiculo->estado;
        $this->observaciones = (string) $vehiculo->observaciones;
        $this->soat_vence = $vehiculo->soat_vence?->format('Y-m-d');
        $this->revision_tecnica_vence = $vehiculo->revision_tecnica_vence?->format('Y-m-d');
        $this->habilitacion_atu_vence = $vehiculo->habilitacion_atu_vence?->format('Y-m-d');

        $this->dispatch('vehiculo-modal-open');
    }

    /** Asocia el cliente elegido del buscador del modal. */
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
        $this->client_id = null;
        $this->clienteNombre = '';
    }

    /** Crea o actualiza el vehículo según $editingId. */
    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        // La placa siempre se guarda en mayúsculas y sin espacios sobrantes.
        $this->placa = strtoupper(trim($this->placa));

        $this->validate();

        // Coherencia según el tipo de propietario: los campos del tipo que
        // no aplica se guardan en null para no dejar datos huérfanos.
        $clientId = $this->propietario_tipo === 'empresa' ? null : $this->client_id;
        $propNombre = $this->propietario_tipo === 'tercero' ? trim($this->propietario_nombre) : null;
        $propDocumento = $this->propietario_tipo === 'tercero'
            ? (trim($this->propietario_documento) !== '' ? trim($this->propietario_documento) : null)
            : null;

        $data = [
            'client_id' => $clientId,
            'propietario_tipo' => $this->propietario_tipo,
            'propietario_nombre' => $propNombre,
            'propietario_documento' => $propDocumento,
            'placa' => $this->placa,
            'marca' => trim($this->marca) !== '' ? trim($this->marca) : null,
            'modelo' => trim($this->modelo) !== '' ? trim($this->modelo) : null,
            'nro_motor' => trim($this->nro_motor) !== '' ? trim($this->nro_motor) : null,
            'nro_serie' => trim($this->nro_serie) !== '' ? trim($this->nro_serie) : null,
            'categoria' => trim($this->categoria) !== '' ? trim($this->categoria) : null,
            'anio' => $this->anio !== null && $this->anio !== '' ? (int) $this->anio : null,
            'carroceria' => trim($this->carroceria) !== '' ? trim($this->carroceria) : null,
            'color' => trim($this->color) !== '' ? trim($this->color) : null,
            'combustible' => trim($this->combustible) !== '' ? trim($this->combustible) : null,
            'valor' => $this->valor !== null && $this->valor !== '' ? round((float) $this->valor, 2) : null,
            'estado' => $this->estado,
            'observaciones' => trim($this->observaciones) !== '' ? trim($this->observaciones) : null,
            'soat_vence' => $this->soat_vence !== null && $this->soat_vence !== '' ? $this->soat_vence : null,
            'revision_tecnica_vence' => $this->revision_tecnica_vence !== null && $this->revision_tecnica_vence !== '' ? $this->revision_tecnica_vence : null,
            'habilitacion_atu_vence' => $this->habilitacion_atu_vence !== null && $this->habilitacion_atu_vence !== '' ? $this->habilitacion_atu_vence : null,
        ];

        if ($this->editingId) {
            $vehiculo = Vehiculo::findOrFail($this->editingId);
            $vehiculo->update($data);
            Audit::log("Editó el vehículo {$vehiculo->placa}", $vehiculo);
            $mensaje = 'Vehículo actualizado correctamente';
        } else {
            $vehiculo = Vehiculo::create($data);
            Audit::log("Creó el vehículo {$vehiculo->placa}", $vehiculo);
            $mensaje = 'Vehículo registrado correctamente';
        }

        $this->dispatch('vehiculo-modal-close');
        $this->limpiarFormulario();
        $this->dispatch('successAlert', ['message' => $mensaje]);
    }

    public function render()
    {
        $query = Vehiculo::query()
            ->with('client:id,nombre,apellido_pat,apellido_mat,documento')
            ->withCount('garantias');

        // Buscador general: placa/marca/modelo del vehículo o nombre/documento del cliente
        if (trim($this->buscar) !== '') {
            $term = trim($this->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('placa', 'like', "%{$term}%")
                    ->orWhere('marca', 'like', "%{$term}%")
                    ->orWhere('modelo', 'like', "%{$term}%")
                    ->orWhere('propietario_nombre', 'like', "%{$term}%")
                    ->orWhereHas('client', function ($c) use ($term) {
                        $c->where('documento', 'like', "%{$term}%")
                            ->orWhere(function ($n) use ($term) {
                                // Cada palabra debe calzar en algún campo del
                                // nombre, en cualquier orden (mismo criterio
                                // que Clients\Index).
                                foreach (preg_split('/\s+/', $term) as $word) {
                                    if ($word === '') {
                                        continue;
                                    }
                                    $n->where(function ($w) use ($word) {
                                        $w->where('nombre', 'like', "%{$word}%")
                                            ->orWhere('apellido_pat', 'like', "%{$word}%")
                                            ->orWhere('apellido_mat', 'like', "%{$word}%");
                                    });
                                }
                            });
                    });
            });
        }

        if ($this->filtroEstado !== '' && isset(Vehiculo::ESTADOS[$this->filtroEstado])) {
            $query->where('estado', $this->filtroEstado);
        }
        if ($this->filtroPropietario !== '' && isset(Vehiculo::PROPIETARIO_TIPOS[$this->filtroPropietario])) {
            $query->where('propietario_tipo', $this->filtroPropietario);
        }

        // Docs. vencidos/por vencer: activos con algún documento vencido o
        // venciendo dentro de los próximos 30 días.
        if ($this->filtroVencidos) {
            $limite = now()->addDays(30)->toDateString();
            $query->where('estado', 'activo')
                ->where(function ($q) use ($limite) {
                    $q->whereDate('soat_vence', '<=', $limite)
                        ->orWhereDate('revision_tecnica_vence', '<=', $limite)
                        ->orWhereDate('habilitacion_atu_vence', '<=', $limite);
                });
        }

        $vehiculos = $query->orderBy('placa')->paginate(25);

        // Buscador de clientes del modal: primeras 10 coincidencias activas
        // por nombre o documento (mínimo 2 caracteres para no barrer la tabla).
        $clientesEncontrados = collect();
        $termCliente = trim($this->clienteBusqueda);
        if (mb_strlen($termCliente) >= 2) {
            $clientesEncontrados = Client::query()
                ->where('status', 'active')
                ->where(function ($q) use ($termCliente) {
                    $q->where('documento', 'like', "%{$termCliente}%")
                        ->orWhere(function ($n) use ($termCliente) {
                            foreach (preg_split('/\s+/', $termCliente) as $word) {
                                if ($word === '') {
                                    continue;
                                }
                                $n->where(function ($w) use ($word) {
                                    $w->where('nombre', 'like', "%{$word}%")
                                        ->orWhere('apellido_pat', 'like', "%{$word}%")
                                        ->orWhere('apellido_mat', 'like', "%{$word}%");
                                });
                            }
                        });
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get(['id', 'nombre', 'apellido_pat', 'apellido_mat', 'documento']);
        }

        return view('livewire.legal.vehiculos.index', compact('vehiculos', 'clientesEncontrados'));
    }
}
