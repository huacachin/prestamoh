<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pestaña "Préstamos" de /clients/{id}/edit (10/10/2026, Antony, ficha 27):
 * lista los créditos del cliente y el botón "Nuevo préstamo" abre el mismo
 * formulario de /credits/create/{id} AQUÍ, dentro de la pestaña. Al guardar,
 * el alta avisa con el evento `prestamo-creado`, el formulario se cierra y la
 * tabla se vuelve a pintar con el crédito nuevo resaltado.
 */
class Prestamos extends Component
{
    public int $clientId;

    /** true = se muestra el formulario de alta en lugar del botón. */
    public bool $nuevo = false;

    /** Crédito recién guardado desde esta pestaña: su fila sale resaltada. */
    public ?int $recienCreado = null;

    public bool $puedeVerCreditos = false;

    public bool $puedeCrear = false;

    public function mount(int $id): void
    {
        $this->clientId = Client::findOrFail($id)->id;

        $user = auth()->user();
        $this->puedeVerCreditos = $user?->can('creditos') ?? false;
        // Misma regla que Credits\Create::mount: el analista (scope propio) ve, no crea.
        $this->puedeCrear = $this->puedeVerCreditos && ! ($user?->can('clientes.scope-propio') ?? false);
    }

    public function abrirNuevo(): void
    {
        if (! $this->puedeCrear) {
            $this->dispatch('errorAlert', ['message' => 'Tu rol no permite registrar préstamos.']);

            return;
        }
        $this->recienCreado = null;
        $this->nuevo = true;
    }

    #[On('prestamo-cancelado')]
    public function cerrarNuevo(): void
    {
        $this->nuevo = false;
    }

    #[On('prestamo-creado')]
    public function alCrearPrestamo(int $id): void
    {
        $this->nuevo = false;
        $this->recienCreado = $id;
    }

    /**
     * Créditos del cliente (todos menos los eliminados), del más reciente al
     * más antiguo, con lo pagado y el saldo del cronograma en una sola consulta.
     *
     * @return array{credits: Collection<int, Credit>, resumen: array<int, array{pagado: float, saldo: float}>}
     */
    private function creditos(): array
    {
        $credits = Credit::query()
            ->where('client_id', $this->clientId)
            ->where('situacion', '<>', 'Eliminado')
            ->with('user:id,name,username')
            ->orderByDesc('fecha_prestamo')
            ->orderByDesc('id')
            ->get();

        $resumen = [];
        if ($credits->isNotEmpty()) {
            $filas = CreditInstallment::query()
                ->whereIn('credit_id', $credits->pluck('id'))
                // Alias distintos de las columnas: `pagado` existe en la tabla y el modelo lo castea a booleano.
                ->selectRaw('credit_id, COALESCE(SUM(importe_aplicado + interes_aplicado), 0) total_pagado, COALESCE(SUM(importe_cuota + importe_interes - importe_aplicado - interes_aplicado), 0) saldo_pendiente')
                ->groupBy('credit_id')
                ->get();
            foreach ($filas as $f) {
                $resumen[(int) $f->credit_id] = ['pagado' => (float) $f->total_pagado, 'saldo' => max(0.0, (float) $f->saldo_pendiente)];
            }
        }

        return ['credits' => $credits, 'resumen' => $resumen];
    }

    public function render()
    {
        $client = Client::findOrFail($this->clientId);
        ['credits' => $credits, 'resumen' => $resumen] = $this->creditos();
        $activos = $credits->where('situacion', 'Activo')->count();

        return view('livewire.clients.prestamos', [
            'client' => $client,
            'credits' => $credits,
            'resumen' => $resumen,
            'conteo' => $credits->count().($activos > 0 ? ' · '.$activos.($activos === 1 ? ' activo' : ' activos') : ''),
        ]);
    }
}
