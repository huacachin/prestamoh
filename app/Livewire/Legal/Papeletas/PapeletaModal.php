<?php

namespace App\Livewire\Legal\Papeletas;

use App\Models\Papeleta;
use App\Models\Vehiculo;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de alta/edición de papeletas (hijo de Legal\Papeletas\Index).
 *
 * Es componente aparte a propósito (mismo patrón que Garantias\EditarModal):
 * sus interacciones —incluido el buscador de vehículos por placa— solo
 * re-renderizan el modal y no el listado completo. El padre lo abre con
 * dispatch('abrir-papeleta-modal') (sin id = crear; con papeletaId = editar)
 * y escucha 'papeleta-guardada' para refrescarse.
 */
class PapeletaModal extends Component
{
    /** null = creando; id = editando esa papeleta. */
    public ?int $editingId = null;

    // ── Vehículo asociado ──

    public ?int $vehiculo_id = null;

    /** "PLACA — MARCA MODELO" del vehículo elegido, solo para mostrar. */
    public string $vehiculoLabel = '';

    /** Texto del buscador de vehículos por placa. */
    public string $vehiculoBusqueda = '';

    // ── Formulario ──

    public string $entidad = 'SAT';

    public string $nro_papeleta = '';

    public string $codigo_falta = '';

    public $puntos = null;

    public string $fecha_infraccion = '';

    public $monto = null;

    public string $responsable_pago = 'propietario';

    public string $conductor_nombre = '';

    public string $conductor_documento = '';

    public string $estado = 'pendiente';

    /** Solo editable al editar (los altas nacen sin la marca). */
    public bool $requiere_revision = false;

    public string $nota = '';

    /** Reglas dinámicas: el unique compuesto entidad+nro ignora el propio id. */
    protected function rules(): array
    {
        return [
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')],
            'entidad' => ['required', Rule::in(array_keys(Papeleta::ENTIDADES))],
            'nro_papeleta' => [
                'required', 'string', 'max:30',
                Rule::unique('papeletas', 'nro_papeleta')
                    ->where('entidad', $this->entidad)
                    ->ignore($this->editingId),
            ],
            'codigo_falta' => ['nullable', 'string', 'max:10'],
            'puntos' => ['nullable', 'integer', 'between:0,100'],
            'fecha_infraccion' => ['required', 'date', 'before_or_equal:'.now()->toDateString()],
            'monto' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'responsable_pago' => ['required', Rule::in(array_keys(Papeleta::RESPONSABLES))],
            'conductor_nombre' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($this->responsable_pago === 'conductor'),
            ],
            'conductor_documento' => ['nullable', 'string', 'max:15'],
            'estado' => ['required', Rule::in(array_keys(Papeleta::ESTADOS))],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'vehiculo_id.required' => 'Busca y selecciona el vehículo de la papeleta.',
            'vehiculo_id.exists' => 'El vehículo seleccionado ya no existe.',
            'entidad.required' => 'Selecciona la entidad emisora.',
            'entidad.in' => 'La entidad no es válida.',
            'nro_papeleta.required' => 'El N° de papeleta es obligatorio.',
            'nro_papeleta.max' => 'El N° de papeleta no debe exceder 30 caracteres.',
            'nro_papeleta.unique' => 'Esa papeleta ya está registrada para esta entidad.',
            'codigo_falta.max' => 'El código de falta no debe exceder 10 caracteres.',
            'puntos.integer' => 'Los puntos deben ser un número entero.',
            'puntos.between' => 'Los puntos deben estar entre 0 y 100.',
            'fecha_infraccion.required' => 'Indica la fecha de la infracción.',
            'fecha_infraccion.date' => 'La fecha de infracción no es válida.',
            'fecha_infraccion.before_or_equal' => 'La fecha de infracción no puede ser futura.',
            'monto.required' => 'El monto de la papeleta es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto no puede ser negativo.',
            'monto.max' => 'El monto excede el máximo permitido.',
            'responsable_pago.required' => 'Indica el responsable del pago.',
            'responsable_pago.in' => 'El responsable de pago no es válido.',
            'conductor_nombre.required' => 'Si el responsable es el conductor, su nombre es obligatorio.',
            'conductor_nombre.max' => 'El nombre del conductor no debe exceder 255 caracteres.',
            'conductor_documento.max' => 'El documento del conductor no debe exceder 15 caracteres.',
            'estado.required' => 'Indica el estado de la papeleta.',
            'estado.in' => 'El estado no es válido.',
            'nota.max' => 'La nota no puede exceder los 2000 caracteres.',
        ];
    }

    /** Deja el formulario en blanco (valores por defecto de un alta). */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'editingId', 'vehiculo_id', 'vehiculoLabel', 'vehiculoBusqueda',
            'entidad', 'nro_papeleta', 'codigo_falta', 'puntos', 'fecha_infraccion',
            'monto', 'responsable_pago', 'conductor_nombre', 'conductor_documento',
            'estado', 'requiere_revision', 'nota',
        ]);
        $this->fecha_infraccion = now()->toDateString();
        $this->resetErrorBag();
    }

    /** Abre el modal: sin id = alta; con papeletaId = edición. */
    #[On('abrir-papeleta-modal')]
    public function abrir(?int $papeletaId = null): void
    {
        // El evento es invocable desde el navegador con cualquier id: se
        // vuelve a verificar el permiso del módulo antes de abrir.
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }

        $this->limpiarFormulario();

        if ($papeletaId) {
            $papeleta = Papeleta::with('vehiculo')->findOrFail($papeletaId);

            $this->editingId = $papeleta->id;
            $this->vehiculo_id = $papeleta->vehiculo_id;
            $this->vehiculoLabel = $papeleta->vehiculo?->descripcion() ?? '';
            $this->entidad = $papeleta->entidad;
            $this->nro_papeleta = $papeleta->nro_papeleta;
            $this->codigo_falta = (string) $papeleta->codigo_falta;
            $this->puntos = $papeleta->puntos;
            $this->fecha_infraccion = $papeleta->fecha_infraccion?->toDateString() ?? '';
            $this->monto = $papeleta->monto;
            $this->responsable_pago = $papeleta->responsable_pago ?? 'propietario';
            $this->conductor_nombre = (string) $papeleta->conductor_nombre;
            $this->conductor_documento = (string) $papeleta->conductor_documento;
            $this->estado = $papeleta->estado;
            $this->requiere_revision = (bool) $papeleta->requiere_revision;
            $this->nota = (string) $papeleta->nota;
        }

        $this->dispatch('papeleta-modal-open');
    }

    /** Asocia el vehículo elegido del buscador por placa. */
    public function seleccionarVehiculo(int $id): void
    {
        $vehiculo = Vehiculo::find($id);
        if (! $vehiculo) {
            return;
        }

        $this->vehiculo_id = $vehiculo->id;
        $this->vehiculoLabel = $vehiculo->descripcion();
        $this->vehiculoBusqueda = '';
        $this->resetErrorBag('vehiculo_id');
    }

    /** Quita el vehículo asociado (solo en altas: al editar queda fijado). */
    public function quitarVehiculo(): void
    {
        if ($this->editingId) {
            return;
        }
        $this->vehiculo_id = null;
        $this->vehiculoLabel = '';
    }

    /** Crea o actualiza la papeleta según $editingId. */
    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }

        // Normaliza ANTES de validar: N° de papeleta en mayúsculas (clave
        // natural del unique compuesto) y textos sin espacios sobrantes.
        $this->nro_papeleta = strtoupper(trim($this->nro_papeleta));
        $this->codigo_falta = strtoupper(trim($this->codigo_falta));
        $this->conductor_nombre = trim($this->conductor_nombre);
        $this->conductor_documento = trim($this->conductor_documento);
        $this->nota = trim($this->nota);

        $this->validate();

        // Los datos del conductor solo aplican cuando él es el responsable.
        $esConductor = $this->responsable_pago === 'conductor';

        $data = [
            'vehiculo_id' => $this->vehiculo_id,
            'entidad' => $this->entidad,
            'nro_papeleta' => $this->nro_papeleta,
            'codigo_falta' => $this->codigo_falta !== '' ? $this->codigo_falta : null,
            'puntos' => $this->puntos !== null && $this->puntos !== '' ? (int) $this->puntos : null,
            'fecha_infraccion' => $this->fecha_infraccion,
            'monto' => round((float) $this->monto, 2),
            'responsable_pago' => $this->responsable_pago,
            'conductor_nombre' => $esConductor ? $this->conductor_nombre : null,
            'conductor_documento' => $esConductor && $this->conductor_documento !== '' ? $this->conductor_documento : null,
            'estado' => $this->estado,
            'nota' => $this->nota !== '' ? $this->nota : null,
        ];

        $entidadLabel = Papeleta::ENTIDADES[$this->entidad] ?? $this->entidad;

        if ($this->editingId) {
            $papeleta = Papeleta::findOrFail($this->editingId);
            $data['requiere_revision'] = $this->requiere_revision;
            $papeleta->update($data);

            Audit::log("Editó la papeleta {$entidadLabel} {$papeleta->nro_papeleta}", $papeleta, [
                'vehiculo_id' => $papeleta->vehiculo_id,
                'estado' => $papeleta->estado,
                'monto' => $papeleta->monto,
            ]);
            $mensaje = 'Papeleta actualizada correctamente.';
        } else {
            $data['registrado_por'] = auth()->id();
            $papeleta = Papeleta::create($data);

            Audit::log("Registró la papeleta {$entidadLabel} {$papeleta->nro_papeleta}", $papeleta, [
                'vehiculo_id' => $papeleta->vehiculo_id,
                'estado' => $papeleta->estado,
                'monto' => $papeleta->monto,
            ]);
            $mensaje = 'Papeleta registrada correctamente.';
        }

        $this->dispatch('papeleta-guardada');
        $this->dispatch('successAlert', ['message' => $mensaje]);
        $this->dispatch('papeleta-modal-close');
        $this->limpiarFormulario();
    }

    public function render()
    {
        // Buscador de vehículos por placa: primeras 10 coincidencias
        // (mínimo 2 caracteres para no barrer la tabla; cualquier
        // propietario_tipo — las papeletas llegan a toda la flota).
        $vehiculosEncontrados = collect();
        $term = trim($this->vehiculoBusqueda);
        if (! $this->vehiculo_id && mb_strlen($term) >= 2) {
            $vehiculosEncontrados = Vehiculo::query()
                ->where('placa', 'like', "%{$term}%")
                ->orderBy('placa')
                ->limit(10)
                ->get(['id', 'placa', 'marca', 'modelo', 'propietario_tipo', 'estado']);
        }

        return view('livewire.legal.papeletas.papeleta-modal', compact('vehiculosEncontrados'));
    }
}
