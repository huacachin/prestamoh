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

    public function mount(int $id): void
    {
        $this->income = Income::findOrFail($id);
        $this->incomeId = $id;

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
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
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
