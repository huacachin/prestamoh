<?php

namespace App\Livewire\Legal\Expedientes;

use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use App\Support\Audit;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de un expediente judicial: datos del proceso, cuaderno cautelar
 * (o vuelta al principal), timeline de actuaciones y control de plazos.
 *
 * El registro de actuaciones y plazos vive en los modales hijos
 * (ActuacionModal / PlazoModal); este componente solo los abre por evento
 * y se re-renderiza cuando el hijo avisa que guardó.
 */
class Show extends Component
{
    public int $expedienteId;

    public function mount(int $expedienteId): void
    {
        // 404 temprano si el id no existe (la carga completa se hace en render)
        ExpedienteJudicial::findOrFail($expedienteId);
        $this->expedienteId = $expedienteId;
    }

    /** Cambia el estado del expediente dentro del catálogo de su cuaderno. */
    public function cambiarEstado(string $estado): void
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }

        $expediente = ExpedienteJudicial::findOrFail($this->expedienteId);

        if (! array_key_exists($estado, $expediente->estadosDisponibles())) {
            $this->dispatch('errorAlert', ['message' => 'El estado seleccionado no es válido para este cuaderno.']);

            return;
        }

        if ($expediente->estado === $estado) {
            return;
        }

        $anterior = $expediente->estadoLabel();
        $expediente->update(['estado' => $estado]);

        Audit::log(
            "Cambió el expediente {$expediente->nro_expediente} a {$expediente->estadoLabel()}",
            $expediente,
            ['estado_anterior' => $anterior, 'estado' => $estado]
        );

        $this->dispatch('successAlert', ['message' => "Estado actualizado a {$expediente->estadoLabel()}."]);
    }

    /** Crea el cuaderno cautelar 1 derivado del principal (si aún no existe). */
    public function crearCautelar(): void
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }

        $expediente = ExpedienteJudicial::findOrFail($this->expedienteId);

        if ($expediente->cuaderno !== 'principal') {
            $this->dispatch('errorAlert', ['message' => 'Solo un expediente principal puede generar cuaderno cautelar.']);

            return;
        }

        if ($expediente->cautelares()->exists()) {
            $this->dispatch('errorAlert', ['message' => 'Este expediente ya tiene cuaderno cautelar.']);

            return;
        }

        $nroCautelar = ExpedienteJudicial::nroCautelarDesde($expediente->nro_expediente);

        if (ExpedienteJudicial::where('nro_expediente', $nroCautelar)->exists()) {
            $this->dispatch('errorAlert', ['message' => "Ya existe un expediente con el número {$nroCautelar}."]);

            return;
        }

        $cautelar = ExpedienteJudicial::create([
            'client_id' => $expediente->client_id,
            'credit_id' => $expediente->credit_id,
            'garantia_id' => $expediente->garantia_id,
            'exp_interno' => $expediente->exp_interno,
            'nro_expediente' => $nroCautelar,
            'cuaderno' => 'cautelar',
            'expediente_padre_id' => $expediente->id,
            'juzgado' => $expediente->juzgado,
            'distrito_judicial' => $expediente->distrito_judicial,
            'materia' => $expediente->materia,
            'proceso' => $expediente->proceso,
            'juez' => $expediente->juez,
            'secretario' => $expediente->secretario,
            'via' => $expediente->via,
            'asesor_responsable_id' => $expediente->asesor_responsable_id,
            'estado' => 'solicitada',
            'fecha_inicio' => now()->toDateString(),
        ]);

        Audit::log(
            "Creó el cuaderno cautelar {$cautelar->nro_expediente} del expediente {$expediente->nro_expediente}",
            $cautelar,
            ['expediente_padre_id' => $expediente->id]
        );

        $this->dispatch('successAlert', ['message' => "Cuaderno cautelar {$cautelar->nro_expediente} creado."]);
    }

    /** Marca un plazo del expediente como cumplido. */
    public function marcarCumplido(int $plazoId): void
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }

        $plazo = PlazoJudicial::where('expediente_id', $this->expedienteId)->findOrFail($plazoId);

        if ($plazo->cumplido_at) {
            return;
        }

        $plazo->update(['cumplido_at' => now()]);

        Audit::log(
            "Marcó cumplido el plazo \"{$plazo->descripcion}\" del expediente {$plazo->expediente->nro_expediente}",
            $plazo,
            ['expediente_id' => $this->expedienteId]
        );

        $this->dispatch('successAlert', ['message' => 'Plazo marcado como cumplido.']);
    }

    /** Abre el modal hijo de registro de actuación. */
    public function abrirActuacionModal(): void
    {
        $this->dispatch('abrir-actuacion-modal', expedienteId: $this->expedienteId);
    }

    /** Abre el modal hijo de registro de plazo. */
    public function abrirPlazoModal(): void
    {
        $this->dispatch('abrir-plazo-modal', expedienteId: $this->expedienteId);
    }

    /**
     * Los modales hijos avisan que guardaron: basta re-renderizar, porque
     * render() recarga el expediente y sus relaciones desde la base.
     */
    #[On('actuacion-registrada')]
    #[On('plazo-registrado')]
    public function refrescar(): void {}

    public function render()
    {
        $expediente = ExpedienteJudicial::with([
            'client', 'credit', 'garantia', 'asesor', 'principal',
            'cautelares.client', 'actuaciones.registradoPor', 'plazos.responsable',
        ])->findOrFail($this->expedienteId);

        return view('livewire.legal.expedientes.show', compact('expediente'));
    }
}
