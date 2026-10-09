<?php

namespace App\Livewire\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Support\Audit;
use App\Support\ConReglasDeEliminacion;
use App\Support\Coordenadas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ubicaciones GPS del cliente (pestaña de /clients/{id}/edit, 28/08).
 *
 * Reemplaza las columnas "C." y "N." del listado, que solo mostraban un pin
 * y abrían un modal para pegar coordenadas. Misma funcionalidad —pegar el
 * texto de Google Maps— pero con la ubicación a la vista, editable y con
 * lectura cómoda en el celular (que es donde se captura).
 *
 * 10/10/2026 (Antony, ficha 173): (1) vuelve NEGOCIO como segunda dirección
 * (latitud2/longitud2, que el 26/09 se había quitado del mapa pero seguía en
 * la BD); (2) si la dirección ya tiene coordenadas, el campo va bloqueado y
 * solo se habilita al pulsar "Modificar" (Alpine, sin viaje al servidor);
 * (3) cada cambio queda en una tablita —dirección, antes, después, quién,
 * cuándo— que sale de la auditoría: se registra a mano con el detalle
 * (properties.gps) y también se leen los cambios de coordenadas hechos desde
 * la ficha del cliente (auditoría automática del modelo).
 */
class Gps extends Component
{
    use ConReglasDeEliminacion;

    public const TIPOS = ['casa' => 'Casa', 'negocio' => 'Negocio'];

    /** Columnas de clients por tipo de dirección. */
    public const COLUMNAS = ['casa' => ['latitud', 'longitud'], 'negocio' => ['latitud2', 'longitud2']];

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
        [$cLat, $cLng] = self::COLUMNAS[$tipo];
        $antes = $this->coordenadasActuales($tipo);

        $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$cLat => $lat, $cLng => $lng]));
        $this->client->refresh();

        $verbo = $antes['lat'] !== null ? 'Actualizó' : 'Registró';
        Audit::log("{$verbo} la ubicación GPS de ".self::TIPOS[$tipo]." del cliente #{$this->clientId}", $this->client, [
            'gps' => ['tipo' => $tipo, 'antes' => $antes, 'despues' => $this->coordenadasActuales($tipo)],
        ]);

        $this->pegado[$tipo] = '';
        $this->msgType = 'ok';
        $this->msg = 'Coordenadas de '.self::TIPOS[$tipo].' guardadas.';
        // El navegador vuelve a bloquear el campo de esa dirección.
        $this->dispatch('gps-guardado', tipo: $tipo);
    }

    public function borrar(string $tipo): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar coordenadas.');
        if (! array_key_exists($tipo, self::TIPOS)) {
            return;
        }
        // Las coordenadas no tienen fecha propia de registro: quien no es director no las borra.
        if ($this->eliminacionBloqueada(null)) {
            return;
        }

        [$cLat, $cLng] = self::COLUMNAS[$tipo];
        $antes = $this->coordenadasActuales($tipo);

        $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$cLat => null, $cLng => null]));
        $this->client->refresh();

        Audit::log('Borró la ubicación GPS de '.self::TIPOS[$tipo]." del cliente #{$this->clientId}", $this->client, [
            'gps' => ['tipo' => $tipo, 'antes' => $antes, 'despues' => ['lat' => null, 'lng' => null]],
        ]);

        $this->msgType = 'ok';
        $this->msg = 'Coordenadas de '.self::TIPOS[$tipo].' borradas.';
        $this->dispatch('gps-guardado', tipo: $tipo);
    }

    /** @return array{lat: ?string, lng: ?string, url: ?string} */
    public function ubicacion(string $tipo): array
    {
        $c = $this->coordenadasActuales($tipo);

        return [
            'lat' => $c['lat'],
            'lng' => $c['lng'],
            'url' => ($c['lat'] && $c['lng']) ? "https://maps.google.com/?q={$c['lat']},{$c['lng']}" : null,
        ];
    }

    /** @return array{lat: ?string, lng: ?string} */
    private function coordenadasActuales(string $tipo): array
    {
        [$cLat, $cLng] = self::COLUMNAS[$tipo];

        return ['lat' => $this->client->{$cLat}, 'lng' => $this->client->{$cLng}];
    }

    /**
     * Cambios de ubicación del cliente, del más reciente al más viejo, para la
     * tablita. Salen de la auditoría: las filas con detalle `gps` (las de esta
     * pestaña) y las automáticas del modelo que tocaron alguna coordenada
     * (p. ej. desde la ficha del cliente).
     *
     * @return Collection<int, array{fecha: Carbon, accion: string, tipo: string, antes: string, despues: string, quien: string}>
     */
    public function historial(): Collection
    {
        return ActivityLog::query()
            ->where('log_name', Audit::LOG)
            ->where('subject_type', $this->client->getMorphClass())
            ->where('subject_id', $this->clientId)
            ->with('causer:id,name,username')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->flatMap(fn (ActivityLog $a) => $this->filasDelRegistro($a))
            ->take(50)
            ->values();
    }

    /** @return list<array{fecha: Carbon, accion: string, tipo: string, antes: string, despues: string, quien: string}> */
    private function filasDelRegistro(ActivityLog $a): array
    {
        $p = $a->properties;
        $quien = $a->causer?->username ?? $a->causer?->name ?? $a->user_name ?? '—';

        if (isset($p['gps'])) {
            $g = $p['gps'];

            return [[
                'fecha' => $a->created_at,
                'accion' => explode(' ', $a->description)[0],
                'tipo' => self::TIPOS[$g['tipo']] ?? (string) $g['tipo'],
                'antes' => self::textoCoordenadas($g['antes'] ?? []),
                'despues' => self::textoCoordenadas($g['despues'] ?? []),
                'quien' => $quien,
            ]];
        }

        // Los cambios del modelo van en old_data / new_data (GuardarActividad), no en
        // properties. Solo ediciones: el alta trae todas las columnas (en null).
        if ($a->event !== 'updated') {
            return [];
        }
        $nuevo = $a->new_data ?? [];
        $viejo = $a->old_data ?? [];
        $filas = [];
        foreach (self::COLUMNAS as $tipo => [$cLat, $cLng]) {
            if (! array_key_exists($cLat, $nuevo) && ! array_key_exists($cLng, $nuevo)) {
                continue;
            }
            $antes = ['lat' => $viejo[$cLat] ?? null, 'lng' => $viejo[$cLng] ?? null];
            $despues = ['lat' => $nuevo[$cLat] ?? $antes['lat'], 'lng' => $nuevo[$cLng] ?? $antes['lng']];
            if (self::textoCoordenadas($antes) === '—' && self::textoCoordenadas($despues) === '—') {
                continue;
            }
            $filas[] = [
                'fecha' => $a->created_at,
                'accion' => $despues['lat'] === null ? 'Borró' : ($antes['lat'] === null ? 'Registró' : 'Actualizó'),
                'tipo' => self::TIPOS[$tipo].' (desde la ficha)',
                'antes' => self::textoCoordenadas($antes),
                'despues' => self::textoCoordenadas($despues),
                'quien' => $quien,
            ];
        }

        return $filas;
    }

    /** "-11.9000000" (DECIMAL de la BD) → "-11.9"; null se queda null. */
    private static function numero(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = rtrim(rtrim(number_format((float) $v, 7, '.', ''), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    private static function textoCoordenadas(array $c): string
    {
        $lat = self::numero($c['lat'] ?? null);
        $lng = self::numero($c['lng'] ?? null);

        return ($lat === null || $lat === '') && ($lng === null || $lng === '') ? '—' : trim("{$lat}, {$lng}", ', ');
    }

    public function render()
    {
        return view('livewire.clients.gps', ['historial' => $this->historial()]);
    }
}
