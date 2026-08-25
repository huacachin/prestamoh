<?php

namespace App\Livewire\Legal\Garantias;

use App\Models\Garantia;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de una garantía mobiliaria: crédito, deudor(es), vehículos con su
 * pivot, parámetros, estado/vigencia y el timeline de avisos SIGM.
 *
 * El registro de avisos vive en el modal hijo (AvisoModal); este componente
 * solo lo abre por evento y se re-renderiza cuando el hijo avisa que guardó.
 */
class Show extends Component
{
    public int $garantiaId;

    public function mount(int $garantiaId): void
    {
        // 404 temprano si el id no existe (la carga completa se hace en render)
        Garantia::findOrFail($garantiaId);
        $this->garantiaId = $garantiaId;
    }

    /** Abre el modal hijo de registro de aviso SIGM. */
    public function abrirAvisoModal(): void
    {
        $this->dispatch('abrir-aviso-modal', garantiaId: $this->garantiaId);
    }

    /** Abre el modal hijo de edición de la garantía (monto máximo, valores, parámetros). */
    public function abrirEditarModal(): void
    {
        $this->dispatch('abrir-garantia-editar', garantiaId: $this->garantiaId);
    }

    /**
     * Un modal hijo avisa que guardó: basta re-renderizar, porque render()
     * recarga la garantía y sus relaciones desde la base.
     */
    #[On('aviso-registrado')]
    #[On('garantia-editada')]
    public function refrescar(): void {}

    public function render()
    {
        $garantia = Garantia::with([
            'client', 'codeudor', 'credit',
            'vehiculos', 'avisos.registradoPor', 'contratos.generadoPor',
        ])->findOrFail($this->garantiaId);

        return view('livewire.legal.garantias.show', compact('garantia'));
    }
}
