<?php

namespace App\Livewire\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientUbicacion;
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
 * 10/10/2026 (Antony, ficha 173): (1) vuelve NEGOCIO (latitud2/longitud2) y se
 * pueden AGREGAR más direcciones con nombre propio (tabla client_ubicaciones);
 * (2) si la dirección ya tiene coordenadas, el campo va bloqueado y solo se
 * habilita al pulsar "Modificar" (Alpine, sin viaje al servidor); (3) cada
 * cambio queda en una tablita —dirección, antes, después, quién, cuándo— que
 * sale de la auditoría: se registra a mano con el detalle (properties.gps) y
 * también se leen los cambios de coordenadas hechos desde la ficha del
 * cliente (auditoría automática del modelo).
 *
 * Claves de dirección: 'casa' y 'negocio' (fijas, columnas de clients) y
 * 'u:{id}' para las adicionales.
 */
class Gps extends Component
{
    use ConReglasDeEliminacion;

    public const TIPOS = ['casa' => 'Casa', 'negocio' => 'Negocio'];

    /** Columnas de clients por dirección fija. */
    public const COLUMNAS = ['casa' => ['latitud', 'longitud'], 'negocio' => ['latitud2', 'longitud2']];

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    /** Texto pegado por clave de dirección: ['casa' => '...', 'negocio' => '...', 'u:12' => '...'] */
    public array $pegado = ['casa' => '', 'negocio' => ''];

    /** Alta de una dirección adicional. */
    public string $nuevaNombre = '';

    public string $nuevaCoordenadas = '';

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

    public function guardar(string $clave): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar coordenadas.');
        $extra = $this->ubicacionExtra($clave);
        if (! array_key_exists($clave, self::TIPOS) && ! $extra) {
            return;
        }

        $coords = Coordenadas::parse((string) ($this->pegado[$clave] ?? ''));
        if (! $coords) {
            $this->msgType = 'err';
            $this->msg = 'Formato inválido. Pega las coordenadas como: -12.014431, -76.824936 (o el enlace de Google Maps).';

            return;
        }

        [$lat, $lng] = $coords;
        $antes = $this->coordenadas($clave);

        if ($extra) {
            $extra->update(['latitud' => $lat, 'longitud' => $lng]);
            $this->client->refresh();
        } else {
            [$cLat, $cLng] = self::COLUMNAS[$clave];
            $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$cLat => $lat, $cLng => $lng]));
            $this->client->refresh();
        }

        $verbo = $antes['lat'] !== null ? 'Actualizó' : 'Registró';
        $this->registrarCambio("{$verbo} la ubicación GPS de ".$this->nombre($clave)." del cliente #{$this->clientId}", $clave, $antes, $this->coordenadas($clave));

        $this->pegado[$clave] = '';
        $this->msgType = 'ok';
        $this->msg = 'Coordenadas de '.$this->nombre($clave).' guardadas.';
        // El navegador vuelve a bloquear el campo de esa dirección.
        $this->dispatch('gps-guardado', clave: $clave);
    }

    /** Dirección adicional nueva, con nombre propio y sus coordenadas. */
    public function agregar(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar coordenadas.');
        $this->nuevaNombre = trim(preg_replace('/\s+/', ' ', $this->nuevaNombre));

        $this->validate([
            'nuevaNombre' => ['required', 'string', 'max:60', function ($attr, $valor, $fail) {
                $usados = array_map('mb_strtolower', array_merge(array_values(self::TIPOS), $this->client->ubicaciones()->pluck('nombre')->all()));
                if (in_array(mb_strtolower($valor), $usados, true)) {
                    $fail("Ya hay una dirección llamada {$valor}.");
                }
            }],
            'nuevaCoordenadas' => ['required', 'string', fn ($attr, $valor, $fail) => Coordenadas::parse((string) $valor) ?: $fail('Pega las coordenadas como: -12.014431, -76.824936 (o el enlace de Google Maps).')],
        ], [], ['nuevaNombre' => 'nombre', 'nuevaCoordenadas' => 'coordenadas']);

        [$lat, $lng] = Coordenadas::parse($this->nuevaCoordenadas);
        $u = $this->client->ubicaciones()->create(['nombre' => $this->nuevaNombre, 'latitud' => $lat, 'longitud' => $lng, 'user_id' => auth()->id()]);
        $this->client->refresh();

        $clave = "u:{$u->id}";
        $this->registrarCambio("Registró la ubicación GPS de {$u->nombre} del cliente #{$this->clientId}", $clave, ['lat' => null, 'lng' => null], $this->coordenadas($clave));

        $this->reset('nuevaNombre', 'nuevaCoordenadas');
        $this->resetErrorBag();
        $this->msgType = 'ok';
        $this->msg = "Dirección {$u->nombre} registrada.";
        $this->dispatch('gps-guardado', clave: $clave);
    }

    public function borrar(string $clave): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar coordenadas.');
        $extra = $this->ubicacionExtra($clave);
        if (! array_key_exists($clave, self::TIPOS) && ! $extra) {
            return;
        }
        // Casa y Negocio no tienen fecha propia de registro (solo el director las borra);
        // una dirección adicional sí, y entra en la regla del mismo día.
        if ($this->eliminacionBloqueada($extra)) {
            return;
        }

        $antes = $this->coordenadas($clave);
        $nombre = $this->nombre($clave);

        if ($extra) {
            $extra->delete();
            $this->client->refresh();
            unset($this->pegado[$clave]);
        } else {
            [$cLat, $cLng] = self::COLUMNAS[$clave];
            $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$cLat => null, $cLng => null]));
            $this->client->refresh();
        }

        $this->registrarCambio("Borró la ubicación GPS de {$nombre} del cliente #{$this->clientId}", $clave, $antes, ['lat' => null, 'lng' => null], $nombre);

        $this->msgType = 'ok';
        $this->msg = $extra ? "Dirección {$nombre} eliminada." : "Coordenadas de {$nombre} borradas.";
        $this->dispatch('gps-guardado', clave: $clave);
    }

    /**
     * Todas las direcciones en el orden de la pestaña: Casa, Negocio y las adicionales.
     *
     * @return list<array{clave: string, nombre: string, fija: bool, lat: ?string, lng: ?string, url: ?string, modelo: ?ClientUbicacion}>
     */
    public function direcciones(): array
    {
        $filas = [];
        foreach (self::TIPOS as $clave => $nombre) {
            $c = $this->coordenadas($clave);
            $filas[] = ['clave' => $clave, 'nombre' => $nombre, 'fija' => true, 'lat' => $c['lat'], 'lng' => $c['lng'], 'url' => self::url($c), 'modelo' => null];
        }
        foreach ($this->client->ubicaciones as $u) {
            $c = ['lat' => $u->latitud, 'lng' => $u->longitud];
            $filas[] = ['clave' => "u:{$u->id}", 'nombre' => $u->nombre, 'fija' => false, 'lat' => $c['lat'], 'lng' => $c['lng'], 'url' => self::url($c), 'modelo' => $u];
        }

        return $filas;
    }

    /** @return array{lat: ?string, lng: ?string, url: ?string} */
    public function ubicacion(string $clave): array
    {
        $c = $this->coordenadas($clave);

        return ['lat' => $c['lat'], 'lng' => $c['lng'], 'url' => self::url($c)];
    }

    private static function url(array $c): ?string
    {
        return ($c['lat'] && $c['lng']) ? "https://maps.google.com/?q={$c['lat']},{$c['lng']}" : null;
    }

    private function ubicacionExtra(string $clave): ?ClientUbicacion
    {
        if (! str_starts_with($clave, 'u:')) {
            return null;
        }

        return $this->client->ubicaciones()->find((int) substr($clave, 2));
    }

    private function nombre(string $clave): string
    {
        return self::TIPOS[$clave] ?? ($this->ubicacionExtra($clave)?->nombre ?? $clave);
    }

    /** @return array{lat: ?string, lng: ?string} */
    private function coordenadas(string $clave): array
    {
        if ($extra = $this->ubicacionExtra($clave)) {
            return ['lat' => $extra->latitud, 'lng' => $extra->longitud];
        }
        [$cLat, $cLng] = self::COLUMNAS[$clave] ?? [null, null];

        return ['lat' => $cLat ? $this->client->{$cLat} : null, 'lng' => $cLng ? $this->client->{$cLng} : null];
    }

    /** Fila de auditoría con el detalle que lee la tablita (sujeto: el cliente). */
    private function registrarCambio(string $descripcion, string $clave, array $antes, array $despues, ?string $nombre = null): void
    {
        Audit::log($descripcion, $this->client, [
            'gps' => ['tipo' => $clave, 'nombre' => $nombre ?? $this->nombre($clave), 'antes' => $antes, 'despues' => $despues],
        ]);
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
                'tipo' => self::TIPOS[$g['tipo']] ?? ($g['nombre'] ?? (string) $g['tipo']),
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

        return $lat === null && $lng === null ? '—' : trim("{$lat}, {$lng}", ', ');
    }

    public function render()
    {
        return view('livewire.clients.gps', [
            'direcciones' => $this->direcciones(),
            'historial' => $this->historial(),
        ]);
    }
}
