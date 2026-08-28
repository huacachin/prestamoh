<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\SavesIncomeAttachments;
use App\Models\Income;
use App\Models\IncomeAttachment;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class IncomeGallery extends Component
{
    use SavesIncomeAttachments;
    use WithFileUploads;

    public Income $income;

    public int $incomeId;

    public array $files = [];

    public bool $puedeEditar = true;   // subir nuevos

    public bool $puedeEliminar = true; // borrar existentes

    /** true cuando se anida en otra pantalla (p. ej. editar ingreso): sin chrome de página ni redirect. */
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
        $this->income = Income::findOrFail($id);

        // Los asientos del Área Legal (caja=4) se gestionan desde su documento de
        // origen (aviso SIGM / trámite notarial), nunca desde las pantallas de Caja.
        abort_if((int) $this->income->caja === 4, 403, 'Movimiento de la Caja Legal: se gestiona desde el Área Legal.');
        $this->incomeId = $id;
        $this->embedded = $embedded;

        $user = auth()->user();

        if ($user?->can('caja.editar-historico')) {
            $this->puedeEditar = true;
            $this->puedeEliminar = $user->can('caja.eliminar');
        } else {
            $this->puedeEditar = $this->income->user_id === $user?->id;
            $this->puedeEliminar = false;
        }
    }

    public function save()
    {
        if (! $this->puedeEditar) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para subir adjuntos a este ingreso.']);

            return;
        }

        $this->validate();

        $count = $this->storeIncomeAttachments($this->income, $this->files);

        $this->files = [];
        $msg = $count === 1 ? 'Imagen subida.' : "$count imágenes subidas.";

        if ($this->embedded) {
            $this->dispatch('successAlert', ['message' => $msg]);

            return;
        }

        session()->flash('cash_success', $msg);

        return $this->redirectRoute('cash.incomes');
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
        // Canal propio: al anidarse en editar ingreso, el register_destroy global
        // también lo escucha EditIncome (borraría un INGRESO con el id del adjunto).
        $this->dispatch('questionDelete', ['id' => $id, 'event' => 'attachment_destroy']);
    }

    #[On('attachment_destroy')]
    public function deleteAttachment(int $id): void
    {
        if (! $this->puedeEliminar) {
            return;
        }

        $att = IncomeAttachment::where('income_id', $this->incomeId)->find($id);
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
        $attachments = IncomeAttachment::where('income_id', $this->incomeId)
            ->orderByDesc('id')->get();

        return view('livewire.cash.income-gallery', compact('attachments'));
    }
}
