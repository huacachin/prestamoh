<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Expense;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditExpense extends Component
{
    use WithFileUploads;

    public Expense $expense;

    #[Locked]
    public int $expenseId;

    public string $date = '';

    public string $reason = '';

    public string $detail = '';

    public string $total = '';

    public string $document_type = '';

    public string $in_charge = '';

    public $image;

    public ?string $current_image = null;

    public bool $canEditDate = false;

    public function mount(int $id): void
    {
        $this->expense = Expense::findOrFail($id);

        // Los asientos del Área Legal (caja=4) se gestionan desde su documento de
        // origen (aviso SIGM / trámite notarial), nunca desde las pantallas de Caja.
        abort_if((int) $this->expense->caja === 4, 403, 'Movimiento de la Caja Legal: se gestiona desde el Área Legal.');
        $this->expenseId = $id;

        $this->autorizar();
        $this->canEditDate = auth()->user()?->can('caja.bypass-fecha-anterior') ?? false;

        $this->date = $this->expense->date->format('Y-m-d');
        $this->reason = (string) $this->expense->reason;
        $this->detail = (string) ($this->expense->detail ?? '');
        $this->total = (string) $this->expense->total;
        $this->document_type = (string) ($this->expense->document_type ?? '');
        $this->in_charge = (string) ($this->expense->in_charge ?? '');
        $this->current_image = $this->expense->image_path;
    }

    protected $rules = [
        'date' => 'required|date',
        'reason' => 'required|string|max:255',
        'detail' => 'nullable|string|max:500',
        'total' => 'required|numeric|min:0.01',
        'document_type' => 'nullable|string|max:100',
        'in_charge' => 'nullable|string|max:255',
        'image' => 'nullable|image|max:2048',
    ];

    /**
     * Sin caja.editar-historico solo lo registrado HOY, y solo lo propio salvo
     * caja.ver-todo. Se llama en mount() Y en cada acción (update/destroy):
     * Livewire hidrata sin re-ejecutar mount, así que el guard debe repetirse.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        $esDeHoy = $this->expense->date->format('Y-m-d') === now()->format('Y-m-d');
        $propioOVeTodo = ($user?->can('caja.ver-todo') ?? false) || $this->expense->user_id === $user?->id;
        abort_unless(
            ($user?->can('caja.editar-historico') ?? false) || ($esDeHoy && $propioOVeTodo),
            403,
            'Solo se pueden editar movimientos del día.'
        );
    }

    public function update(): void
    {
        $this->autorizar();

        try {
            $this->validate();

            $data = [
                // Sin bypass-fecha-anterior la fecha original no se toca
                'date' => (auth()->user()?->can('caja.bypass-fecha-anterior') ?? false) ? $this->date : $this->expense->date->format('Y-m-d'),
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total' => $this->total,
                'document_type' => $this->document_type,
                'in_charge' => $this->in_charge,
            ];

            if ($this->image) {
                $data['image_path'] = $this->image->store('expenses', 'public');
            }

            $this->expense->update($data);

            // Espejo caja 3 (legacy gastos-modificar22.php): al editar un egreso se
            // sincroniza la copia caja=3 (aa=reason, detalle, totalgeneral=mismo monto).
            if ($this->expense->modo === 'Fijos') {
                Expense::where('caja', 3)
                    ->where('parent_id', $this->expense->id)
                    ->update([
                        'reason' => $this->reason,
                        'detail' => $this->detail,
                        'total' => $this->total,
                    ]);
            }

            Audit::log("Editó el egreso #{$this->expense->id} (monto {$this->total})", $this->expense);

            session()->flash('cash_success', 'Egreso actualizado correctamente.');
            $this->redirectRoute('cash.expenses');
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
        // pantalla, solo aceptamos el id del propio egreso (el botón Eliminar
        // siempre manda $expenseId; cualquier otro id no es para este componente).
        if ($id !== $this->expenseId) {
            return;
        }

        if (! auth()->user()?->can('caja.eliminar')) {
            abort(403);
        }
        $this->autorizar();

        // Espejo caja 3 (legacy gastos-modificar22.php): el borrado elimina entrada Y entrada3.
        Expense::where('caja', 3)->where('parent_id', $id)->delete();
        Expense::findOrFail($id)->delete();
        Audit::log("Eliminó el egreso #{$id}");
        session()->flash('cash_success', 'Egreso eliminado correctamente.');
        $this->redirectRoute('cash.expenses');
    }

    public function render()
    {
        $concepts = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.cash.edit-expense', compact('concepts'));
    }
}
