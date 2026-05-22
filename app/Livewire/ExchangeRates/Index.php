<?php

namespace App\Livewire\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
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

    /**
     * Trae el tipo de cambio SUNAT desde la API de migo.pe.
     * - Si hay fecha seleccionada → POST /exchange/date con esa fecha.
     * - Sin fecha → POST /exchange/latest (último publicado).
     * Llena los campos compra/venta para que el usuario revise y guarde.
     */
    public function cargarDeSunat(): void
    {
        $token = config('services.migo.token');
        $base  = rtrim((string) config('services.migo.base'), '/');

        if (!$token) {
            $this->dispatch('errorAlert', ['message' => 'Falta MIGO_PE_TOKEN en .env']);
            return;
        }

        try {
            $endpoint = $this->fecha !== '' ? '/exchange/date' : '/exchange/latest';
            $payload  = ['token' => $token];
            if ($this->fecha !== '') $payload['fecha'] = $this->fecha;

            $resp = Http::timeout(8)->acceptJson()->asJson()->post("$base$endpoint", $payload);

            if (!$resp->ok()) {
                $this->dispatch('errorAlert', ['message' => "SUNAT respondió HTTP {$resp->status()}"]);
                return;
            }

            $j = $resp->json();
            if (empty($j) || (isset($j['success']) && $j['success'] === false)) {
                $msg = $j['message'] ?? 'No se pudo obtener el tipo de cambio.';
                $this->dispatch('errorAlert', ['message' => $msg]);
                return;
            }

            $this->fecha  = (string) ($j['fecha'] ?? $this->fecha);
            $this->compra = (string) ($j['precio_compra'] ?? '');
            $this->venta  = (string) ($j['precio_venta']  ?? '');

            $this->dispatch('successAlert', ['message' => "TC SUNAT {$this->fecha}: compra {$this->compra} · venta {$this->venta}"]);
        } catch (\Throwable $e) {
            $this->dispatch('errorAlert', ['message' => 'Error consultando SUNAT: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.exchange-rates.index');
    }
}
