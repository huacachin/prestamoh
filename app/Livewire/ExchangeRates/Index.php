<?php

namespace App\Livewire\ExchangeRates;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Livewire\Component;

class Index extends Component
{
    public string $fecha = '';

    public $compra = '';

    public $venta = '';

    public bool $saved = false;

    /** De dónde salió el valor mostrado: cache | bd | sunat | bd-anterior */
    public string $origen = '';

    public function mount(ExchangeRateService $tc): void
    {
        // Antes se cargaba el último registro de la base, que podía ser de hace
        // años, y había que pulsar "Traer de SUNAT" a mano. Ahora se resuelve
        // solo: caché → base de datos → SUNAT, y se guarda lo que llegue.
        $this->mostrar($tc->delDia());
    }

    protected $rules = [
        'fecha' => 'required|date',
        'compra' => 'required|numeric|min:0',
        'venta' => 'required|numeric|min:0',
    ];

    /** Vuelve a preguntar a SUNAT saltando caché y base de datos. */
    public function refrescar(ExchangeRateService $tc): void
    {
        $datos = $tc->delDia($this->fecha ?: null, forzar: true);

        if ($datos === null) {
            $this->dispatch('errorAlert', ['message' => 'No se pudo consultar SUNAT. Revisa la conexión o ingresa el valor a mano.']);

            return;
        }

        $this->mostrar($datos);

        if ($datos['origen'] === ExchangeRateService::DE_BD_ANTERIOR) {
            $this->dispatch('errorAlert', ['message' => 'SUNAT no respondió. Se muestra el último tipo de cambio conocido ('.$datos['fecha'].').']);

            return;
        }

        $this->dispatch('successAlert', ['message' => "Tipo de cambio actualizado: compra {$datos['compra']} · venta {$datos['venta']}"]);
    }

    /** Guardado manual, para corregir a mano lo que haga falta. */
    public function save(ExchangeRateService $tc): void
    {
        $this->validate();

        $rate = ExchangeRate::updateOrCreate(
            ['fecha' => $this->fecha],
            ['compra' => $this->compra, 'venta' => $this->venta]
        );

        // Sin esto la caché seguiría sirviendo el valor viejo el resto del día.
        $tc->recordar($this->fecha, [
            'fecha' => $this->fecha,
            'compra' => (string) $this->compra,
            'venta' => (string) $this->venta,
        ]);

        \App\Support\Audit::log("Actualizó el tipo de cambio {$this->fecha} (compra {$this->compra} / venta {$this->venta})", $rate);

        $this->origen = ExchangeRateService::DE_BD;
        $this->saved = true;
        $this->dispatch('successAlert', ['message' => 'Se actualizó el Tipo de Cambio con éxito']);
    }

    private function mostrar(?array $datos): void
    {
        if ($datos === null) {
            $this->fecha = now()->format('Y-m-d');

            return;
        }

        $this->fecha = $datos['fecha'];
        $this->compra = $datos['compra'];
        $this->venta = $datos['venta'];
        $this->origen = $datos['origen'];
    }

    public function render()
    {
        return view('livewire.exchange-rates.index', [
            'esDeHoy' => $this->fecha === now()->format('Y-m-d'),
        ]);
    }
}
