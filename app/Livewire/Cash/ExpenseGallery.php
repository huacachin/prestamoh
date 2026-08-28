<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\SavesExpenseAttachments;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExpenseGallery extends Component
{
    use SavesExpenseAttachments;
    use WithFileUploads;

    public Expense $expense;

    public int $expenseId;

    public array $files = [];

    public bool $puedeEditar = true;

    public bool $puedeEliminar = true;

    /** true cuando se anida en otra pantalla (p. ej. editar egreso): sin chrome de página ni redirect. */
    public bool $embedded = false;

    protected function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'files.required' => 'Selecciona o arrastra al menos una imagen.',
            'files.*.image' => 'Cada archivo debe ser una imagen.',
            'files.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
            'files.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
        ];
    }

    public function mount(int $id, bool $embedded = false): void
    {
        $this->expense = Expense::findOrFail($id);

        // Los asientos del Área Legal (caja=4) se gestionan desde su documento de
        // origen (aviso SIGM / trámite notarial), nunca desde las pantallas de Caja.
        abort_if((int) $this->expense->caja === 4, 403, 'Movimiento de la Caja Legal: se gestiona desde el Área Legal.');
        $this->expenseId = $id;
        $this->embedded = $embedded;

        $user = auth()->user();

        // Quien puede editar el histórico (admin/director/etc.) maneja también la galería completa.
        // Operadores de caja sin ese permiso solo pueden adjuntar a sus propios egresos y nunca eliminar.
        if ($user?->can('caja.editar-historico')) {
            $this->puedeEditar = true;
            $this->puedeEliminar = $user->can('caja.eliminar');
        } else {
            $this->puedeEditar = $this->expense->user_id === $user?->id;
            $this->puedeEliminar = false;
        }
    }

    public function save()
    {
        if (! $this->puedeEditar) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para subir adjuntos a este egreso.']);

            return;
        }

        $this->validate();

        $count = $this->storeExpenseAttachments($this->expense, $this->files);

        $this->files = [];
        $msg = $count === 1 ? 'Imagen subida.' : "$count imágenes subidas.";

        if ($this->embedded) {
            $this->dispatch('successAlert', ['message' => $msg]);

            return;
        }

        session()->flash('cash_success', $msg);

        return $this->redirectRoute('cash.expenses');
    }

    public function removeFile(int $i): void
    {
        if (isset($this->files[$i])) {
            unset($this->files[$i]);
            $this->files = array_values($this->files);
        }
    }

    public function questionDelete(int $id): void
    {
        if (! $this->puedeEliminar) {
            return;
        }
        // Canal propio: al anidarse en editar egreso, el register_destroy global
        // también lo escucha EditExpense (borraría un EGRESO con el id del adjunto).
        $this->dispatch('questionDelete', ['id' => $id, 'event' => 'attachment_destroy']);
    }

    #[On('attachment_destroy')]
    public function deleteAttachment(int $id): void
    {
        if (! $this->puedeEliminar) {
            return;
        }

        $att = ExpenseAttachment::where('expense_id', $this->expenseId)->find($id);
        if (! $att) {
            return;
        }

        $disk = Storage::disk('public');
        if ($att->path && $disk->exists($att->path)) {
            $disk->delete($att->path);
        }
        if ($att->thumb_path && $disk->exists($att->thumb_path)) {
            $disk->delete($att->thumb_path);
        }

        $att->delete();
    }

    public function render()
    {
        $attachments = ExpenseAttachment::where('expense_id', $this->expenseId)
            ->orderByDesc('id')->get();

        return view('livewire.cash.expense-gallery', compact('attachments'));
    }
}
