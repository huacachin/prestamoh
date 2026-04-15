<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Expense;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateExpense extends Component
{
    use WithFileUploads;

    public string $date = '';
    public string $reason = '';
    public string $detail = '';
    public string $total = '';
    public string $document_type = '';
    public string $in_charge = '';
    public $image;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
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

    public function clear(): void
    {
        $this->date          = now()->format('Y-m-d');
        $this->reason        = '';
        $this->detail        = '';
        $this->total         = '';
        $this->document_type = '';
        $this->in_charge     = '';
        $this->image         = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        try {
            $this->validate();

            $user = auth()->user();

            $imagePath = null;
            if ($this->image) {
                $imagePath = $this->image->store('expenses', 'public');
            }

            Expense::create([
                'date'           => $this->date,
                'reason'         => $this->reason,
                'detail'         => $this->detail,
                'total'          => $this->total,
                'document_type'  => $this->document_type,
                'in_charge'      => $this->in_charge,
                'image_path'     => $imagePath,
                'user_id'        => $user->id,
                'headquarter_id' => $user->headquarter_id,
            ]);

            session()->flash('cash_success', 'Egreso registrado correctamente.');
            $this->redirectRoute('cash.expenses');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $concepts = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.cash.create-expense', compact('concepts'));
    }
}
