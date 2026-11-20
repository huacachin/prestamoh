<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Support\RutaExpediente as Ruta;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Barra "Ruta del expediente" de la ficha del cliente (20/11/2026, Antony: "que el flujo desde crear
 * el cliente hasta generar sus documentos les sea amigable"): los siete pasos con su estado, cuántos
 * van y un botón al paso que sigue. El cálculo vive en App\Support\RutaExpediente.
 *
 * 20/11 (tras hablar con el cliente): el paso a paso es de UN préstamo —se elige en la barra cuando el
 * cliente tiene varios activos—, se recorre con Anterior / Siguiente y es la única navegación de la ficha.
 *
 * Es un componente aparte de la ficha para refrescarse solo: cuando una pestaña guarda algo (un
 * vehículo, una foto, el préstamo, un documento, la cuenta GPS) avisa con uno de los eventos de abajo
 * y solo se vuelve a pintar esta barra, no el formulario entero del cliente.
 */
class RutaExpediente extends Component
{
    public int $clientId;

    /** Pestaña abierta en la ficha: marca el paso en el que se está. */
    #[Reactive]
    public string $tab = 'datos';

    /** Préstamo cuyo paso a paso se muestra; null = el de la ruta (RutaExpediente::creditoEnCurso). */
    #[Reactive]
    public ?int $prestamo = null;

    public function mount(int $id, string $tab = 'datos', ?int $prestamo = null): void
    {
        $client = Client::findOrFail($id);
        // Analista (scope-propio): solo SUS clientes, igual que el resto de la ficha.
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        $this->clientId = $id;
        $this->tab = $tab;
        $this->prestamo = $prestamo;
    }

    /** Algo cambió en una pestaña: la barra se vuelve a calcular al pintarse. */
    #[On('expediente-actualizado')]
    #[On('prestamo-creado')]
    #[On('successAlert')]
    #[On('cuenta-gps-modal-close')]
    public function refrescar(): void {}

    public function render(): View
    {
        $client = Client::findOrFail($this->clientId);
        // Una persona relacionada (copropietario) no lleva expediente propio: va en el de su titular.
        if ($client->es_relacionado) {
            return view('livewire.clients.ruta-expediente', ['ruta' => null, 'prestamos' => []]);
        }

        $ruta = Ruta::de($client, $this->prestamo);
        // A un cliente cesado no se le puede prestar (Client::bloqueoParaPrestamos): en vez de mandarlo
        // al paso que sigue, la barra dice qué hay que hacer antes.
        if ($ruta['credito'] === null && $client->bloqueoParaPrestamos() !== null) {
            $ruta['aviso'] = $client->status === 'inactive'
                ? 'Cliente cesado: dalo de alta en Cliente Cesados para continuar con su expediente.'
                : 'El cliente no está activo: no se le pueden hacer préstamos.';
        }

        return view('livewire.clients.ruta-expediente', ['ruta' => $ruta, 'prestamos' => Ruta::prestamosActivos($this->clientId)]);
    }
}
