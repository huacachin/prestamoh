<?php

namespace App\Support;

use Livewire\Attributes\Url;

/**
 * "Regresar" de las pantallas de un crédito a las que se entra desde la pestaña Préstamos de la
 * ficha del cliente: el cronograma y el cobro (27/11/2026, Antony, ficha 1493: "le doy a regresar
 * y no regresa al mismo clients/1493/edit?tab=prestamos, sino a otra ventana"). El botón iba
 * siempre a la pantalla de consulta del cliente, viniera uno de donde viniera.
 *
 * El enlace de la ficha lleva ?desde=expediente (y ?prestamo= si la ficha tenía un préstamo
 * abierto en la ruta), igual que el de "Editar el préstamo" (Credits\Edit, 20/11). Quien entra por
 * otro lado sigue volviendo a la consulta del cliente.
 *
 * El componente debe tener $credit.
 */
trait RegresaAlExpediente
{
    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'prestamo', except: '')]
    public string $prestamoDeLaRuta = '';

    /** A dónde lleva "Regresar": a la pestaña Préstamos de la ficha si se vino de ahí; si no, a la consulta del cliente. */
    public function urlDeRegreso(): string
    {
        $clientId = $this->credit?->client_id;
        if ($clientId === null) {
            return route('clients.index');
        }
        if ($this->desde !== 'expediente' || ! (auth()->user()?->can('clientes') ?? false)) {
            return route('clients.show', $clientId);
        }

        return route('clients.edit', ['id' => $clientId]
            + (ctype_digit($this->prestamoDeLaRuta) ? ['prestamo' => $this->prestamoDeLaRuta] : [])
            + ['tab' => 'prestamos']);
    }
}
