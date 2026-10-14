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
 * (2) cada cambio queda en una tablita —dirección, antes, después, quién,
 * cuándo— que sale de la auditoría: se registra a mano con el detalle
 * (properties.gps) y también se leen los cambios de coordenadas hechos desde
 * la ficha del cliente (auditoría automática del modelo).
 *
 * 13/10/2026 (Antony): las direcciones van en una TABLA como las ubicaciones
 * de los vehículos: Op. (lápiz) · N° · Dirección · Coordenadas. El lápiz abre
 * un formulario con el NOMBRE (Casa y Negocio también se renombran: columnas
 * clients.ubicacion1_nombre / ubicacion2_nombre) y las coordenadas; Borrar
 * vive dentro de ese formulario.
 *
 * Claves de dirección: 'casa' y 'negocio' (fijas, columnas de clients) y
 * 'u:{id}' para las adicionales.
 */
class Gps extends Component
{
    use ConReglasDeEliminacion;

    /** Nombres por defecto de las direcciones fijas (el usuario puede renombrarlas). */
    public const TIPOS = ['casa' => 'Casa', 'negocio' => 'Negocio'];

    /** Columnas de clients por dirección fija. */
    public const COLUMNAS = ['casa' => ['latitud', 'longitud'], 'negocio' => ['latitud2', 'longitud2']];

    /** Columna de clients con el nombre que el usuario le puso a cada dirección fija. */
    public const COLUMNA_NOMBRE = ['casa' => 'ubicacion1_nombre', 'negocio' => 'ubicacion2_nombre'];

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    /** Texto pegado por clave de dirección: ['casa' => '...', 'negocio' => '...', 'u:12' => '...'] */
    public array $pegado = ['casa' => '', 'negocio' => ''];

    /** Dirección cuyo formulario de edición está abierto (null = ninguno) y el nombre que se edita. */
    public ?string $editClave = null;

    public string $editNombre = '';

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

    /** Abre el formulario de una dirección: nombre y coordenadas (vacías = se conservan). */
    public function editar(string $clave): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar coordenadas.');
        if (! array_key_exists($clave, self::TIPOS) && ! $this->ubicacionExtra($clave)) {
            return;
        }
        $this->resetErrorBag();
        $this->msg = null;
        $this->editClave = $clave;
        $this->editNombre = $this->nombre($clave);
        $this->pegado[$clave] = '';
    }

    public function cancelarEdicion(): void
    {
        if ($this->editClave !== null) {
            $this->pegado[$this->editClave] = '';
        }
        $this->editClave = null;
        $this->editNombre = '';
        $this->resetErrorBag();
    }

    /**
     * Guarda la dirección: las coordenadas pegadas (si se pegó algo) y, con el
     * formulario abierto, el nombre nuevo. Sin nada que cambiar, avisa.
     */
    public function guardar(string $clave): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar coordenadas.');
        $extra = $this->ubicacionExtra($clave);
        if (! array_key_exists($clave, self::TIPOS) && ! $extra) {
            return;
        }

        $texto = trim((string) ($this->pegado[$clave] ?? ''));
        $coords = null;
        if ($texto !== '') {
            $coords = Coordenadas::parse($texto);
            if (! $coords) {
                $this->msgType = 'err';
                $this->msg = self::mensajeFormato($texto);

                return;
            }
        }

        $nombreActual = $this->nombre($clave);
        $nombreNuevo = $this->editClave === $clave ? trim(preg_replace('/\s+/', ' ', $this->editNombre)) : $nombreActual;
        if ($nombreNuevo !== $nombreActual) {
            $this->validate(['editNombre' => ['required', 'string', 'max:60', $this->nombreLibre($clave)]], [], ['editNombre' => 'nombre']);
        } elseif ($coords === null) {
            $this->msgType = 'warn';
            $this->msg = 'No hay nada que guardar: pega las coordenadas o cambia el nombre.';

            return;
        }

        if ($coords !== null) {
            [$lat, $lng] = $coords;
            $antes = $this->coordenadas($clave);
            if ($extra) {
                $extra->update(['latitud' => $lat, 'longitud' => $lng]);
            } else {
                [$cLat, $cLng] = self::COLUMNAS[$clave];
                $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$cLat => $lat, $cLng => $lng]));
            }
            $this->client->refresh();
            $verbo = $antes['lat'] !== null ? 'Actualizó' : 'Registró';
            $this->registrarCambio("{$verbo} la ubicación GPS de {$nombreActual} del cliente #{$this->clientId}", $clave, $antes, $this->coordenadas($clave), $nombreActual);
        }

        if ($nombreNuevo !== $nombreActual) {
            if ($extra) {
                $extra->update(['nombre' => $nombreNuevo]);
            } else {
                $columna = self::COLUMNA_NOMBRE[$clave];
                // null = vuelve al nombre de siempre
                $this->client->sinAuditoriaAutomatica(fn () => $this->client->update([$columna => $nombreNuevo === self::TIPOS[$clave] ? null : $nombreNuevo]));
            }
            $this->client->refresh();
            $c = $this->coordenadas($clave);
            Audit::log("Renombró la dirección GPS {$nombreActual} a {$nombreNuevo} del cliente #{$this->clientId}", $this->client, [
                'gps' => ['tipo' => $clave, 'nombre' => $nombreNuevo, 'antes' => $c, 'despues' => $c, 'renombre' => ['de' => $nombreActual, 'a' => $nombreNuevo]],
            ]);
        }

        $this->pegado[$clave] = '';
        $this->editClave = null;
        $this->editNombre = '';
        $this->msgType = 'ok';
        $this->msg = $coords !== null
            ? "Coordenadas de {$nombreNuevo} guardadas."
            : "Dirección renombrada: {$nombreActual} → {$nombreNuevo}.";
        $this->dispatch('gps-guardado', clave: $clave);
    }

    /** Dirección adicional nueva, con nombre propio y sus coordenadas. */
    public function agregar(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar coordenadas.');
        $this->nuevaNombre = trim(preg_replace('/\s+/', ' ', $this->nuevaNombre));
        $this->validate([
            'nuevaNombre' => ['required', 'string', 'max:60', $this->nombreLibre(null)],
            'nuevaCoordenadas' => ['required', 'string', fn ($attr, $valor, $fail) => Coordenadas::parse((string) $valor) ?: $fail(self::mensajeFormato((string) $valor))],
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

        if ($this->editClave === $clave) {
            $this->editClave = null;
            $this->editNombre = '';
        }
        $this->msgType = 'ok';
        $this->msg = $extra ? "Dirección {$nombre} eliminada." : "Coordenadas de {$nombre} borradas.";
        $this->dispatch('gps-guardado', clave: $clave);
    }

    /**
     * Todas las direcciones en el orden de la pestaña: Casa, Negocio y las adicionales.
     *
     * @return list<array{clave: string, nombre: string, fija: bool, icono: string, lat: ?string, lng: ?string, url: ?string, modelo: ?ClientUbicacion, fecha: ?Carbon, quien: ?string}>
     */
    public function direcciones(): array
    {
        $ultimos = $this->ultimosRegistros();
        $filas = [];
        foreach (array_keys(self::TIPOS) as $clave) {
            $c = $this->coordenadas($clave);
            $filas[] = ['clave' => $clave, 'nombre' => $this->nombre($clave), 'fija' => true, 'icono' => $clave === 'casa' ? 'ti-home' : 'ti-building-store',
                'lat' => $c['lat'], 'lng' => $c['lng'], 'url' => self::url($c), 'modelo' => null,
                'fecha' => $ultimos[$clave]['fecha'] ?? null, 'quien' => $ultimos[$clave]['quien'] ?? null];
        }
        foreach ($this->client->ubicaciones as $u) {
            $c = ['lat' => $u->latitud, 'lng' => $u->longitud];
            $clave = "u:{$u->id}";
            // Sin rastro en la auditoría (altas viejas), vale el alta de la fila.
            $filas[] = ['clave' => $clave, 'nombre' => $u->nombre, 'fija' => false, 'icono' => 'ti-map-pin',
                'lat' => $c['lat'], 'lng' => $c['lng'], 'url' => self::url($c), 'modelo' => $u,
                'fecha' => $ultimos[$clave]['fecha'] ?? $u->created_at, 'quien' => $ultimos[$clave]['quien'] ?? ($u->user?->username ?? $u->user?->name)];
        }

        return $filas;
    }

    /**
     * Último registro o actualización de coordenadas por dirección (14/10,
     * Antony): fecha y usuario, para la tabla. Sale de la auditoría; los
     * renombres y borrados no cuentan.
     *
     * @return array<string, array{fecha: Carbon, quien: string}>
     */
    private function ultimosRegistros(): array
    {
        $ultimos = [];
        foreach ($this->filasHistorial() as $f) {
            if (! in_array($f['accion'], ['Registró', 'Actualizó'], true) || isset($ultimos[$f['clave']])) {
                continue;
            }
            $ultimos[$f['clave']] = ['fecha' => $f['fecha'], 'quien' => $f['quien']];
        }

        return $ultimos;
    }

    /** Aviso de formato: si era un enlace de Maps que no se pudo leer, dice qué hacer. */
    public static function mensajeFormato(string $texto): string
    {
        if (Coordenadas::esEnlaceDeMaps($texto)) {
            return 'No pude leer las coordenadas de ese enlace de Google Maps. Abre el punto en Maps, mantén pulsado el pin, copia las coordenadas y pégalas aquí.';
        }

        return 'Formato inválido. Pega las coordenadas como: -12.014431, -76.824936 (o el enlace de Google Maps).';
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

    /** Nombre actual de una dirección: el puesto por el usuario o el de siempre. */
    private function nombre(string $clave): string
    {
        if (array_key_exists($clave, self::TIPOS)) {
            return $this->client->nombreUbicacion($clave);
        }

        return $this->ubicacionExtra($clave)?->nombre ?? $clave;
    }

    /** Regla: el nombre no puede repetir el de otra dirección del cliente (sin distinguir mayúsculas). */
    private function nombreLibre(?string $claveExcluida): \Closure
    {
        return function ($attr, $valor, $fail) use ($claveExcluida) {
            $usados = [];
            foreach ($this->direcciones() as $d) {
                if ($d['clave'] !== $claveExcluida) {
                    $usados[] = mb_strtolower($d['nombre']);
                }
            }
            if (in_array(mb_strtolower(trim((string) $valor)), $usados, true)) {
                $fail("Ya hay una dirección llamada {$valor}.");
            }
        };
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
        return $this->filasHistorial()->take(50)->values();
    }

    /** Todas las filas de cambios (hasta 300 registros de auditoría), del más reciente al más viejo. */
    private function filasHistorial(): Collection
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
            ->values();
    }

    /** @return list<array{fecha: Carbon, accion: string, tipo: string, antes: string, despues: string, quien: string}> */
    private function filasDelRegistro(ActivityLog $a): array
    {
        $p = $a->properties;
        $quien = $a->causer?->username ?? $a->causer?->name ?? $a->user_name ?? '—';

        if (isset($p['gps'])) {
            $g = $p['gps'];
            // Un cambio de nombre (13/10): antes y después son los nombres, no las coordenadas.
            if (isset($g['renombre'])) {
                return [[
                    'clave' => (string) $g['tipo'],
                    'fecha' => $a->created_at,
                    'accion' => 'Renombró',
                    'tipo' => (string) ($g['renombre']['a'] ?? $g['nombre'] ?? $g['tipo']),
                    'antes' => (string) ($g['renombre']['de'] ?? '—'),
                    'despues' => (string) ($g['renombre']['a'] ?? '—'),
                    'quien' => $quien,
                ]];
            }

            return [[
                'clave' => (string) $g['tipo'],
                'fecha' => $a->created_at,
                'accion' => explode(' ', $a->description)[0],
                'tipo' => $g['nombre'] ?? (self::TIPOS[$g['tipo']] ?? (string) $g['tipo']),
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
                'clave' => $tipo,
                'fecha' => $a->created_at,
                'accion' => $despues['lat'] === null ? 'Borró' : ($antes['lat'] === null ? 'Registró' : 'Actualizó'),
                'tipo' => $this->client->nombreUbicacion($tipo).' (desde la ficha)',
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
