<?php

namespace App\Livewire\Legal\Garantias;

use App\Models\Garantia;
use App\Support\Audit;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de edición de la garantía (hijo de Legal\Garantias\Show): completa lo
 * que el importador del Excel no traía y el validador de contratos exige —
 * monto máximo de la garantía (con sugerencia calculada del cronograma real),
 * valor de cada vehículo, parámetros GPS/custodia y el levantamiento de la
 * marca "requiere revisión". El padre lo abre con
 * dispatch('abrir-garantia-editar', garantiaId: ...).
 */
class EditarModal extends Component
{
    public ?int $garantiaId = null;

    public string $garantiaLabel = '';

    public $montoGravamen = null;

    public bool $gps = false;

    public bool $custodia = false;

    public string $observaciones = '';

    public bool $requiereRevision = false;

    /** vehiculo_id => valor (S/) */
    public array $valoresVehiculos = [];

    /** vehiculo_id => "PLACA — MARCA MODELO" (solo display) */
    public array $vehiculosInfo = [];

    /** Suma real del cronograma del crédito (sugerencia para el monto máximo) */
    public ?float $totalCronograma = null;

    #[On('abrir-garantia-editar')]
    public function abrir(int $garantiaId): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $garantia = Garantia::with(['client', 'credit.installments', 'vehiculos'])->findOrFail($garantiaId);

        $this->garantiaId = $garantia->id;
        $this->garantiaLabel = 'Garantía #'.$garantia->id.' — '.($garantia->client?->fullName() ?? 's/cliente');
        $this->montoGravamen = $garantia->monto_gravamen;
        $this->gps = (bool) $garantia->gps;
        $this->custodia = (bool) $garantia->custodia;
        $this->observaciones = (string) $garantia->observaciones;
        $this->requiereRevision = (bool) $garantia->requiere_revision;

        $this->valoresVehiculos = [];
        $this->vehiculosInfo = [];
        foreach ($garantia->vehiculos as $v) {
            $this->valoresVehiculos[$v->id] = $v->valor;
            $this->vehiculosInfo[$v->id] = $v->descripcion();
        }

        $this->totalCronograma = $garantia->credit
            ? round((float) $garantia->credit->installments->sum(
                fn ($i) => (float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente
            ), 2)
            : null;

        $this->resetErrorBag();
        $this->dispatch('garantia-editar-modal-open');
    }

    /** Copia la suma real del cronograma al monto máximo (lo que exige el validador de contratos). */
    public function usarTotalCronograma(): void
    {
        if ($this->totalCronograma !== null) {
            $this->montoGravamen = number_format($this->totalCronograma, 2, '.', '');
        }
    }

    protected function rules(): array
    {
        return [
            'montoGravamen' => 'required|numeric|min:0.01',
            'valoresVehiculos.*' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'montoGravamen.required' => 'El monto máximo de la garantía es obligatorio.',
            'montoGravamen.numeric' => 'El monto máximo debe ser un número.',
            'montoGravamen.min' => 'El monto máximo debe ser mayor a cero.',
            'valoresVehiculos.*.numeric' => 'El valor del vehículo debe ser un número.',
        ];
    }

    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $this->validate();

        $garantia = Garantia::with('vehiculos')->findOrFail($this->garantiaId);

        $garantia->update([
            'monto_gravamen' => $this->montoGravamen,
            'gps' => $this->gps,
            'custodia' => $this->custodia,
            'observaciones' => $this->observaciones ?: null,
            'requiere_revision' => $this->requiereRevision,
        ]);

        foreach ($garantia->vehiculos as $v) {
            $valor = $this->valoresVehiculos[$v->id] ?? null;
            if ($valor !== null && $valor !== '' && (float) $valor != (float) $v->valor) {
                $v->update(['valor' => $valor]);
            }
        }

        Audit::log("Editó la garantía #{$garantia->id} (monto máximo/parámetros)", $garantia, [
            'monto_gravamen' => $this->montoGravamen,
            'gps' => $this->gps,
            'custodia' => $this->custodia,
        ]);

        $this->dispatch('garantia-editada');
        $this->dispatch('successAlert', ['message' => 'Garantía actualizada.']);
        $this->dispatch('garantia-editar-modal-close');
    }

    public function render()
    {
        return view('livewire.legal.garantias.editar-modal');
    }
}
