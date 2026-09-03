<?php

namespace App\Livewire\Legal\Expedientes;

use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use App\Models\User;
use App\Support\Audit;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de registro de plazos judiciales sueltos (hijo de Legal\Expedientes\Show).
 *
 * Mismo patrón que Garantias\AvisoModal: componente aparte para que sus
 * interacciones no re-rendericen la ficha completa. Los plazos derivados de
 * una actuación se crean desde ActuacionModal; este modal registra plazos
 * sin actuación vinculada (ej.: fecha de remate, vencimiento de oficio).
 */
class PlazoModal extends Component
{
    public ?int $expedienteId = null;

    /** Rótulo del encabezado: "N° expediente — Cuaderno" */
    public string $expedienteLabel = '';

    // ── Formulario ──
    public string $descripcion = '';

    public string $fechaVencimiento = '';

    public ?int $responsableId = null;

    protected function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
            'fechaVencimiento' => ['required', 'date'],
            'responsableId' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    protected function messages(): array
    {
        return [
            'descripcion.required' => 'Indica la descripción del plazo.',
            'descripcion.max' => 'La descripción no puede exceder los 255 caracteres.',
            'fechaVencimiento.required' => 'Indica la fecha de vencimiento del plazo.',
            'fechaVencimiento.date' => 'La fecha de vencimiento no es válida.',
            'responsableId.required' => 'Selecciona al responsable del plazo.',
            'responsableId.exists' => 'El responsable seleccionado no es válido.',
        ];
    }

    #[On('abrir-plazo-modal')]
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
        $this->dispatch('plazo-modal-open');
    }

    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }
        if (! $this->expedienteId) {
            return;
        }

        $this->validate();

        $expediente = ExpedienteJudicial::findOrFail($this->expedienteId);

        $plazo = PlazoJudicial::create([
            'expediente_id' => $expediente->id,
            'actuacion_id' => null,
            'descripcion' => trim($this->descripcion),
            'fecha_vencimiento' => $this->fechaVencimiento,
            'responsable_id' => $this->responsableId,
        ]);

        Audit::log(
            "Registró plazo del expediente {$expediente->nro_expediente}: \"{$plazo->descripcion}\""
            .' (vence '.Carbon::parse($this->fechaVencimiento)->format('d/m/Y').')',
            $plazo,
            ['expediente_id' => $expediente->id, 'responsable_id' => $this->responsableId]
        );

        $this->dispatch('plazo-registrado');
        $this->dispatch('successAlert', ['message' => 'Plazo registrado correctamente.']);
        $this->dispatch('plazo-modal-close');
        $this->limpiarFormulario();
    }

    /** Deja el formulario listo para un nuevo registro. */
    private function limpiarFormulario(): void
    {
        $this->descripcion = '';
        $this->fechaVencimiento = '';
        $this->responsableId = auth()->id();
        $this->resetErrorBag();
    }

    public function render()
    {
        $usuarios = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('livewire.legal.expedientes.plazo-modal', compact('usuarios'));
    }
}
