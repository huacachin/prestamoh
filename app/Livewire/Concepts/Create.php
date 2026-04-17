<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;

class Create extends Component
{
    public string $code   = '';
    public string $name   = '';
    public string $status = 'active';
    public string $type   = 'ingreso';
    public $factor_ingreso = 0;
    public $factor_egreso  = 0;

    public function mount(): void
    {
        $this->code = $this->generateCode();
    }

    private function generateCode(): string
    {
        $nextId = (Concept::max('id') ?? 0) + 1;
        return $nextId < 10 ? '0' . $nextId : (string) $nextId;
    }

    public function clear(): void
    {
        $this->name   = '';
        $this->status = 'active';
        $this->type   = 'ingreso';
        $this->code   = $this->generateCode();
        $this->factor_ingreso = 0;
        $this->factor_egreso  = 0;
        $this->resetErrorBag();
    }

    protected $rules = [
        'code'           => 'required|string|max:10',
        'name'           => 'required|string|max:255',
        'status'         => 'required|in:active,inactive',
        'type'           => 'required|in:ingreso,egreso',
        'factor_ingreso' => 'nullable|numeric|min:0',
        'factor_egreso'  => 'nullable|numeric|min:0',
    ];

    public function save(): void
    {
        try {
            $this->validate();

            Concept::create([
                'code'           => $this->code,
                'name'           => $this->name,
                'status'         => $this->status,
                'type'           => $this->type,
                'factor_ingreso' => $this->factor_ingreso ?: 0,
                'factor_egreso'  => $this->factor_egreso ?: 0,
            ]);

            session()->flash('concept_success', 'Concepto creado correctamente.');
            $this->redirectRoute('settings.concepts.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('concept_error', 'Error al crear: ' . $e->getMessage());
            $this->redirectRoute('settings.concepts.index');
        }
    }

    public function render()
    {
        return view('livewire.concepts.create');
    }
}
