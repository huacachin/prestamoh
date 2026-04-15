<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Income;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditIncome extends Component
{
    use WithFileUploads;

    public Income $income;
    public int $incomeId;

    public string $date = '';
    public string $reason = '';
    public string $detail = '';
    public string $total = '';
    public $image;
    public ?string $current_image = null;

    public function mount(int $id): void
    {
        $this->income  = Income::findOrFail($id);
        $this->incomeId = $id;

        $this->date          = $this->income->date->format('Y-m-d');
        $this->reason        = (string) $this->income->reason;
        $this->detail        = (string) ($this->income->detail ?? '');
        $this->total         = (string) $this->income->total;
        $this->current_image = $this->income->image_path;
    }

    protected $rules = [
        'date'   => 'required|date',
        'reason' => 'required|string|max:255',
        'detail' => 'nullable|string|max:500',
        'total'  => 'required|numeric|min:0.01',
        'image'  => 'nullable|image|max:2048',
    ];

    public function update(): void
    {
        try {
            $this->validate();

            $data = [
                'date'   => $this->date,
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total'  => $this->total,
            ];

            if ($this->image) {
                $data['image_path'] = $this->image->store('incomes', 'public');
            }

            $this->income->update($data);

            session()->flash('cash_success', 'Ingreso actualizado correctamente.');
            $this->redirectRoute('cash.incomes');
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

        Income::findOrFail($id)->delete();
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
