<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Support\Audit;
use App\Support\Coordenadas;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ubicaciones GPS del cliente (pestaña de /clients/{id}/edit, 28/08).
 *
 * Reemplaza las columnas "C." y "N." del listado, que solo mostraban un pin
 * y abrían un modal para pegar coordenadas. Misma funcionalidad —pegar el
 * texto de Google Maps— pero con las dos ubicaciones a la vista, editables y
 * con lectura cómoda en el celular (que es donde se capturan).
 */
class Gps extends Component
{
    public const TIPOS = ['casa' => 'Casa', 'negocio' => 'Negocio'];

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    /** Texto pegado por tipo: ['casa' => '...', 'negocio' => '...'] */
    public array $pegado = ['casa' => '', 'negocio' => ''];

    public ?string $msg = null;

    public ?string $msgType = null;

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;

        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        // Mismo gate que el botón Guardar de la ficha
        $this->puedeEditar = ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    public function guardar(string $tipo): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar coordenadas.');
        if (! array_key_exists($tipo, self::TIPOS)) {
            return;
        }

        $coords = Coordenadas::parse((string) ($this->pegado[$tipo] ?? ''));
        if (! $coords) {
            $this->msgType = 'err';
            $this->msg = 'Formato inválido. Pega las coordenadas como: -12.014431, -76.824936 (o el enlace de Google Maps).';

            return;
        }

        [$lat, $lng] = $coords;
        $campos = $tipo === 'casa'
            ? ['latitud' => $lat, 'longitud' => $lng]
            : ['latitud2' => $lat, 'longitud2' => $lng];

        $this->client->update($campos);
        $this->client->refresh();

        Audit::log('Registró coordenadas ('.self::TIPOS[$tipo].') del cliente '.$this->client->fullName(), $this->client);

        $this->pegado[$tipo] = '';
        $this->msgType = 'ok';
        $this->msg = 'Coordenadas de '.self::TIPOS[$tipo].' guardadas.';
    }

    public function borrar(string $tipo): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar coordenadas.');
        if (! array_key_exists($tipo, self::TIPOS)) {
            return;
        }

        $campos = $tipo === 'casa'
            ? ['latitud' => null, 'longitud' => null]
            : ['latitud2' => null, 'longitud2' => null];

        $this->client->update($campos);
        $this->client->refresh();

        Audit::log('Borró las coordenadas ('.self::TIPOS[$tipo].') del cliente '.$this->client->fullName(), $this->client);

        $this->msgType = 'ok';
        $this->msg = 'Coordenadas de '.self::TIPOS[$tipo].' borradas.';
    }

    /** @return array{lat: ?string, lng: ?string, url: ?string} */
    public function ubicacion(string $tipo): array
    {
        $lat = $tipo === 'casa' ? $this->client->latitud : $this->client->latitud2;
        $lng = $tipo === 'casa' ? $this->client->longitud : $this->client->longitud2;

        return [
            'lat' => $lat,
            'lng' => $lng,
            'url' => ($lat && $lng) ? "https://maps.google.com/?q={$lat},{$lng}" : null,
        ];
    }

    public function render()
    {
        return view('livewire.clients.gps');
    }
}
