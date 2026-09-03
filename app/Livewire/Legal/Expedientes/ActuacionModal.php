<?php

namespace App\Livewire\Legal\Expedientes;

use App\Models\ActuacionJudicial;
use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use App\Support\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de registro de actuaciones judiciales (hijo de Legal\Expedientes\Show).
 *
 * Es componente aparte a propósito (mismo patrón que Garantias\AvisoModal):
 * sus interacciones solo re-renderizan el modal y no la ficha completa del
 * expediente. El padre lo abre con dispatch('abrir-actuacion-modal', expedienteId: ...).
 * Opcionalmente crea un plazo vinculado a la actuación (ej.: traslado de 3 días).
 */
class ActuacionModal extends Component
{
    public ?int $expedienteId = null;

    /** Rótulo del encabezado: "N° expediente — Cuaderno" */
    public string $expedienteLabel = '';

    // ── Formulario ──
    public string $tipo = 'resolucion';

    public ?string $numero = null;

    public string $fecha = '';

    public string $sumilla = '';

    public string $detalle = '';

    /** Check "Genera un plazo": crea un PlazoJudicial vinculado a la actuación */
    public bool $generaPlazo = false;

    public string $descripcionPlazo = '';

    public ?string $fechaVencimiento = null;

    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(ActuacionJudicial::TIPOS))],
            'numero' => ['nullable', 'string', 'max:40'],
            // Máx. mañana: cubre cédulas notificadas con fecha del día siguiente
            'fecha' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'sumilla' => ['required', 'string', 'max:500'],
            'detalle' => ['nullable', 'string', 'max:5000'],
            'descripcionPlazo' => [Rule::requiredIf($this->generaPlazo), 'nullable', 'string', 'max:255'],
            'fechaVencimiento' => [Rule::requiredIf($this->generaPlazo), 'nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de actuación.',
            'tipo.in' => 'El tipo de actuación no es válido.',
            'numero.max' => 'El número no puede exceder los 40 caracteres.',
            'fecha.required' => 'Indica la fecha de la actuación.',
            'fecha.date' => 'La fecha de la actuación no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser posterior a mañana.',
            'sumilla.required' => 'Indica la sumilla de la actuación.',
            'sumilla.max' => 'La sumilla no puede exceder los 500 caracteres.',
            'detalle.max' => 'El detalle no puede exceder los 5000 caracteres.',
            'descripcionPlazo.required' => 'Indica la descripción del plazo que genera esta actuación.',
            'descripcionPlazo.max' => 'La descripción del plazo no puede exceder los 255 caracteres.',
            'fechaVencimiento.required' => 'Indica la fecha de vencimiento del plazo.',
            'fechaVencimiento.date' => 'La fecha de vencimiento no es válida.',
        ];
    }

    #[On('abrir-actuacion-modal')]
    public function abrir(int $expedienteId): void
    {
        // El evento es invocable desde el navegador con cualquier id: se
        // vuelve a verificar el permiso del módulo antes de abrir.
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }

        $expediente = ExpedienteJudicial::findOrFail($expedienteId);

        $this->expedienteId = $expediente->id;
        $this->expedienteLabel = $expediente->nro_expediente
            .' — '.(ExpedienteJudicial::CUADERNOS[$expediente->cuaderno] ?? $expediente->cuaderno);

        $this->limpiarFormulario();
        $this->dispatch('actuacion-modal-open');
    }

    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }
        if (! $this->expedienteId) {
            return;
        }

        // Normaliza vacíos a null ANTES de validar (para nullable)
        $this->numero = trim((string) $this->numero) ?: null;
        $this->fechaVencimiento = $this->fechaVencimiento ?: null;

        $this->validate();

        $expediente = ExpedienteJudicial::findOrFail($this->expedienteId);

        $actuacion = ActuacionJudicial::create([
            'expediente_id' => $expediente->id,
            'tipo' => $this->tipo,
            'numero' => $this->numero,
            'fecha' => $this->fecha,
            'sumilla' => trim($this->sumilla),
            'detalle' => trim($this->detalle) !== '' ? trim($this->detalle) : null,
            'registrado_por' => auth()->id(),
        ]);

        // Plazo derivado de la actuación (ej.: traslado de 3 días)
        if ($this->generaPlazo) {
            PlazoJudicial::create([
                'expediente_id' => $expediente->id,
                'actuacion_id' => $actuacion->id,
                'descripcion' => trim($this->descripcionPlazo),
                'fecha_vencimiento' => $this->fechaVencimiento,
                'responsable_id' => auth()->id(),
            ]);
        }

        Audit::log(
            "Registró actuación en el expediente {$expediente->nro_expediente}: ".Str::limit($actuacion->sumilla, 80),
            $actuacion,
            [
                'expediente_id' => $expediente->id,
                'tipo' => $actuacion->tipo,
                'genera_plazo' => $this->generaPlazo,
            ]
        );

        $this->dispatch('actuacion-registrada');
        $this->dispatch('successAlert', ['message' => 'Actuación registrada correctamente.']);
        $this->dispatch('actuacion-modal-close');
        $this->limpiarFormulario();
    }

    /** Deja el formulario listo para un nuevo registro. */
    private function limpiarFormulario(): void
    {
        $this->tipo = 'resolucion';
        $this->numero = null;
        $this->fecha = now()->toDateString();
        $this->sumilla = '';
        $this->detalle = '';
        $this->generaPlazo = false;
        $this->descripcionPlazo = '';
        $this->fechaVencimiento = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.legal.expedientes.actuacion-modal');
    }
}
