<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Income;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateIncome extends Component
{
    use WithFileUploads;

    public string $date = '';
    public string $reason = '';
    public string $detail = '';
    public string $total = '';
    public $image;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    protected $rules = [
        'date'   => 'required|date',
        'reason' => 'required|string|max:255',
        'detail' => 'nullable|string|max:500',
        'total'  => 'required|numeric|min:0.01',
        'image'  => 'nullable|image|max:2048',
    ];

    public function clear(): void
    {
        $this->date   = now()->format('Y-m-d');
        $this->reason = '';
        $this->detail = '';
        $this->total  = '';
        $this->image  = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        try {
            $this->validate();

            $user = auth()->user();

            $imagePath = null;
            if ($this->image) {
                $imagePath = $this->image->store('incomes', 'public');
            }

            Income::create([
                'date'           => $this->date,
                'reason'         => $this->reason,
                'detail'         => $this->detail,
                'total'          => $this->total,
                'image_path'     => $imagePath,
                'user_id'        => $user->id,
                'headquarter_id' => $user->headquarter_id,
            ]);

            session()->flash('cash_success', 'Ingreso registrado correctamente.');
            $this->redirectRoute('cash.incomes');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $concepts = Concept::where('type', 'ingreso')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.cash.create-income', compact('concepts'));
    }
}
