<?php

namespace App\Livewire\ExchangeRates;

use App\Models\ExchangeRate;
use Livewire\Component;

class Index extends Component
{
    public string $fecha = '';
    public $compra = '';
    public $venta = '';

    public bool $saved = false;

    public function mount()
    {
        $current = ExchangeRate::orderByDesc('fecha')->first();
        $this->fecha  = $current?->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->compra = $current?->compra ?? '';
        $this->venta  = $current?->venta ?? '';
    }

    protected $rules = [
        'fecha'  => 'required|date',
        'compra' => 'required|numeric|min:0',
        'venta'  => 'required|numeric|min:0',
    ];

    public function save(): void
    {
        $this->validate();

        ExchangeRate::updateOrCreate(
            ['fecha' => $this->fecha],
            ['compra' => $this->compra, 'venta' => $this->venta]
        );

        $this->saved = true;
        $this->dispatch('successAlert', ['message' => 'Se actualizó el Tipo de Cambio con éxito']);
    }

    public function render()
    {
        return view('livewire.exchange-rates.index');
    }
}
