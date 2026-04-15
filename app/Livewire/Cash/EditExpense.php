<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Expense;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditExpense extends Component
{
    use WithFileUploads;

    public Expense $expense;
    public int $expenseId;

    public string $date = '';
    public string $reason = '';
    public string $detail = '';
    public string $total = '';
    public string $document_type = '';
    public string $in_charge = '';
    public $image;
    public ?string $current_image = null;

    public function mount(int $id): void
    {
        $this->expense   = Expense::findOrFail($id);
        $this->expenseId = $id;

        $this->date          = $this->expense->date->format('Y-m-d');
        $this->reason        = (string) $this->expense->reason;
        $this->detail        = (string) ($this->expense->detail ?? '');
        $this->total         = (string) $this->expense->total;
        $this->document_type = (string) ($this->expense->document_type ?? '');
        $this->in_charge     = (string) ($this->expense->in_charge ?? '');
        $this->current_image = $this->expense->image_path;
    }

    protected $rules = [
        'date'          => 'required|date',
        'reason'        => 'required|string|max:255',
        'detail'        => 'nullable|string|max:500',
        'total'         => 'required|numeric|min:0.01',
        'document_type' => 'nullable|string|max:100',
        'in_charge'     => 'nullable|string|max:255',
        'image'         => 'nullable|image|max:2048',
    ];

    public function update(): void
    {
        try {
            $this->validate();

            $data = [
                'date'          => $this->date,
                'reason'        => $this->reason,
                'detail'        => $this->detail,
                'total'         => $this->total,
                'document_type' => $this->document_type,
                'in_charge'     => $this->in_charge,
            ];

            if ($this->image) {
                $data['image_path'] = $this->image->store('expenses', 'public');
            }

            $this->expense->update($data);

            session()->flash('cash_success', 'Egreso actualizado correctamente.');
            $this->redirectRoute('cash.expenses');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('superusuario', 'administrador')) {
            abort(403);
        }

        Expense::findOrFail($id)->delete();
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
