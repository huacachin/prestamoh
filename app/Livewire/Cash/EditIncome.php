<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Income;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditIncome extends Component
{
    use WithFileUploads;

    public Income $income;

    #[Locked]
    public int $incomeId;

    public string $date = '';

    public string $reason = '';

    public string $detail = '';

    public string $total = '';

    public $image;

    public ?string $current_image = null;

    public bool $canEditDate = false;

    public function mount(int $id): void
    {
        $this->income = Income::findOrFail($id);

        // Los asientos del Área Legal (caja=4) se gestionan desde su documento de
        // origen (aviso SIGM / trámite notarial), nunca desde las pantallas de Caja.
        abort_if((int) $this->income->caja === 4, 403, 'Movimiento de la Caja Legal: se gestiona desde el Área Legal.');
        $this->incomeId = $id;

        $this->autorizar();
        $this->canEditDate = auth()->user()?->can('caja.bypass-fecha-anterior') ?? false;

        $this->date = $this->income->date->format('Y-m-d');
        $this->reason = (string) $this->income->reason;
        $this->detail = (string) ($this->income->detail ?? '');
        $this->total = (string) $this->income->total;
        $this->current_image = $this->income->image_path;
    }

    protected $rules = [
        'date' => 'required|date',
        'reason' => 'required|string|max:255',
        'detail' => 'nullable|string|max:500',
        'total' => 'required|numeric|min:0.01',
        'image' => 'nullable|image|max:2048',
    ];

    /**
     * Regla 04/09 (Antony): el director (caja.editar-historico) edita los
     * registros DE TODOS y de cualquier fecha; administrador hacia abajo,
     * SOLO los hechos por ellos mismos y SOLO del día. caja.ver-todo da
     * visibilidad, ya no edición ajena. Se llama en mount() Y en cada
     * acción (update/destroy): Livewire hidrata sin re-ejecutar mount,
     * así que el guard debe repetirse.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        $esDeHoy = $this->income->date->format('Y-m-d') === now()->format('Y-m-d');
        $esPropio = $this->income->user_id === $user?->id;
        abort_unless(
            ($user?->can('caja.editar-historico') ?? false) || ($esDeHoy && $esPropio),
            403,
            'Solo puedes editar tus propios movimientos del día.'
        );
    }

    public function update(): void
    {
        $this->autorizar();

        try {
            $this->validate();

            $data = [
                // Sin bypass-fecha-anterior la fecha original no se toca
                'date' => (auth()->user()?->can('caja.bypass-fecha-anterior') ?? false) ? $this->date : $this->income->date->format('Y-m-d'),
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total' => $this->total,
            ];

            if ($this->image) {
                $data['image_path'] = $this->image->store('incomes', 'public');
            }

            $this->income->update($data);

            Audit::log("Editó el ingreso #{$this->income->id} (monto {$this->total})", $this->income);

            session()->flash('cash_success', 'Ingreso actualizado correctamente.');
            $this->redirectRoute('cash.incomes');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al actualizar: '.$e->getMessage());
        }
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        // register_destroy es global: con la galería de adjuntos anidada en esta
        // pantalla, solo aceptamos el id del propio ingreso (el botón Eliminar
        // siempre manda $incomeId; cualquier otro id no es para este componente).
        if ($id !== $this->incomeId) {
            return;
        }

        if (! auth()->user()?->can('caja.eliminar')) {
            abort(403);
        }
        $this->autorizar();

        // Espejo caja 3 (legacy ingresos-modificar2.php): el borrado elimina ingreso Y ingreso3.
        // (Nota: el legacy NO sincroniza caja3 al EDITAR un ingreso, por eso update() no la toca.)
        Income::where('caja', 3)->where('parent_id', $id)->delete();
        Income::findOrFail($id)->delete();
        Audit::log("Eliminó el ingreso #{$id}");
        session()->flash('cash_success', 'Ingreso eliminado correctamente.');
        $this->redirectRoute('cash.incomes');
    }

    public function render()
    {
        $concepts = Concept::where('type', 'ingreso')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.cash.edit-income', compact('concepts'));
    }
}
