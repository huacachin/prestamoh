<?php

namespace App\Livewire\ExchangeRates;

use App\Models\ExchangeRate;
use Livewire\Component;

class Index extends Component
{
    public string $fecha = '';
    public string $compra = '';
    public string $venta = '';
    public $rates;

    public function mount()
    {
        $this->fecha = now()->format('Y-m-d');
        $this->loadRates();
    }

    private function loadRates(): void
    {
        $this->rates = ExchangeRate::orderByDesc('fecha')->limit(30)->get();
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

        $this->dispatch('successAlert', ['message' => 'Tipo de cambio guardado correctamente.']);
        $this->compra = '';
        $this->venta = '';
        $this->loadRates();
    }

    public function render()
    {
        return view('livewire.exchange-rates.index');
    }
}
