<?php

namespace App\Livewire\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Support\Audit;
use App\Support\ConReglasDeEliminacion;
use App\Support\Coordenadas;
use App\Support\HorarioEliminacion;
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
 * un formulario con el NOMBRE (Casa y Negocio también se renombran) y las
 * coordenadas; Borrar
 * vive dentro de ese formulario.
 *
 * 15/10/2026 (Antony): Casa y Negocio se desataron de clients: TODAS las
 * direcciones son filas de client_ubicaciones (tipo casa | negocio | otra) con
 * su último usuario y fecha de registro (user_id, registrado_at). Las claves
 * de la pestaña y de la auditoría siguen siendo 'casa', 'negocio' y 'u:{id}'.
 * Quien no es director edita o borra una dirección solo el día en que se
 * registraron sus coordenadas (la columna Fecha); el director, cualquier día.
 */
class Gps extends Component
{
    use ConReglasDeEliminacion;

    /** Nombres por defecto de las direcciones fijas (el usuario puede renombrarlas). */
    public const TIPOS = ClientUbicacion::TIPOS;

    /**
     * Columnas que Casa y Negocio tenían en clients hasta el 15/10/2026: solo
     * para leer las filas viejas de la auditoría automática ("desde la ficha").
     */
    public const COLUMNAS = ['casa' => ['latitud', 'longitud'], 'negocio' => ['latitud2', 'longitud2']];

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

    /**
     * Abre el formulario de una dirección: nombre y coordenadas (vacías = se conservan).
     * 15/10 (Antony): quien no es director edita solo el día en que se registraron
     * las coordenadas (edicionBloqueada: avisa y deja rastro); el director, siempre.
     * Sin coordenadas todavía no hay nada registrado: se pueden pegar cualquier día.
     */
    public function editar(string $clave): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar coordenadas.');
        if (! array_key_exists($clave, self::TIPOS) && ! $this->ubicacionExtra($clave)) {
            return;
        }
        $fila = $this->fila($clave);
        if ($fila?->tieneCoordenadas() && $this->edicionBloqueada($fila)) {
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
        // Se vuelve a mirar la regla del día al guardar: la página pudo quedar abierta hasta el día siguiente.
        $fila = $this->fila($clave);
        if ($fila?->tieneCoordenadas() && $this->edicionBloqueada($fila, 'guardar')) {
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
            // Casa/Negocio sin fila todavía: se crea con su nombre de siempre.
            $fila = $this->fila($clave) ?? new ClientUbicacion(['client_id' => $this->clientId, 'tipo' => $clave, 'nombre' => $nombreActual]);
            $fila->fill(['latitud' => $lat, 'longitud' => $lng, 'user_id' => auth()->id(), 'registrado_at' => now()])->save();
            $this->client->refresh();
            $verbo = $antes['lat'] !== null ? 'Actualizó' : 'Registró';
            $this->registrarCambio("{$verbo} la ubicación GPS de {$nombreActual} del cliente #{$this->clientId}", $clave, $antes, $this->coordenadas($clave), $nombreActual);
        }

        if ($nombreNuevo !== $nombreActual) {
            // Sin coordenadas aún, la fila fija nace solo con el nombre (se conserva aunque se borren las coordenadas).
            $fila = $this->fila($clave) ?? new ClientUbicacion(['client_id' => $this->clientId, 'tipo' => $clave]);
            $fila->fill(['nombre' => $nombreNuevo])->save();
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
        $u = $this->client->ubicaciones()->create(['tipo' => 'otra', 'nombre' => $this->nuevaNombre, 'latitud' => $lat, 'longitud' => $lng, 'user_id' => auth()->id(), 'registrado_at' => now()]);
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
        $fila = $this->fila($clave);
        if (! $fila || ! $fila->tieneCoordenadas()) {
            return;
        }
        // Regla del mismo día sobre la fecha de registro de las coordenadas (la carga inicial del 14/10
        // cuenta como registro): quien no es director solo borra lo registrado hoy; el director, cualquiera.
        if ($this->eliminacionBloqueada($fila)) {
            return;
        }

        $antes = $this->coordenadas($clave);
        $nombre = $this->nombre($clave);

        if ($extra) {
            $extra->delete();
            unset($this->pegado[$clave]);
        } else {
            // Casa/Negocio: se vacían las coordenadas; la fila queda para conservar el nombre.
            $fila->fill(['latitud' => null, 'longitud' => null, 'user_id' => null, 'registrado_at' => null])->save();
        }
        $this->client->refresh();

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
     * Todas las direcciones en el orden de la pestaña: Casa, Negocio (con o sin
     * fila) y las adicionales, cada una con su último usuario y fecha de registro.
     *
     * @return list<array{clave: string, nombre: string, fija: bool, icono: string, lat: ?string, lng: ?string, url: ?string, modelo: ?ClientUbicacion, fecha: ?Carbon, quien: ?string, editable: bool}>
     */
    public function direcciones(): array
    {
        $filas = [];
        foreach (array_keys(self::TIPOS) as $clave) {
            $filas[] = $this->filaDireccion($clave, $this->client->ubicacionFija($clave), true, $clave === 'casa' ? 'ti-home' : 'ti-building-store');
        }
        foreach ($this->client->ubicaciones->where('tipo', 'otra') as $u) {
            $filas[] = $this->filaDireccion("u:{$u->id}", $u, false, 'ti-map-pin');
        }

        return $filas;
    }

    /** @return array{clave: string, nombre: string, fija: bool, icono: string, lat: ?string, lng: ?string, url: ?string, modelo: ?ClientUbicacion, fecha: ?Carbon, quien: ?string, editable: bool} */
    private function filaDireccion(string $clave, ?ClientUbicacion $u, bool $fija, string $icono): array
    {
        return [
            'clave' => $clave,
            'nombre' => $this->nombre($clave),
            'fija' => $fija,
            'icono' => $icono,
            'lat' => $u?->latitud,
            'lng' => $u?->longitud,
            'url' => $u?->url(),
            'modelo' => $u,
            'fecha' => $u?->registrado_at,
            'quien' => $u?->user?->username ?? $u?->user?->name,
            'editable' => $this->puedeEditarDireccion($u),
        ];
    }

    /**
     * Quién puede editar ESA dirección ahora (para pintar o no el lápiz): con
     * permiso de editar y, si ya tiene coordenadas, solo el día en que se
     * registraron salvo el director. El servidor lo vuelve a comprobar.
     */
    public function puedeEditarDireccion(?ClientUbicacion $u): bool
    {
        if (! $this->puedeEditar) {
            return false;
        }

        return ! $u?->tieneCoordenadas() || HorarioEliminacion::motivoBloqueo($u, null, null, 'editar') === null;
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

    /** Dirección adicional ('u:{id}') de este cliente, o null. */
    private function ubicacionExtra(string $clave): ?ClientUbicacion
    {
        if (! str_starts_with($clave, 'u:')) {
            return null;
        }

        return $this->client->ubicaciones()->where('tipo', 'otra')->find((int) substr($clave, 2));
    }

    /** La fila de cualquier clave: Casa/Negocio por tipo (puede no existir aún) o la adicional por id. */
    private function fila(string $clave): ?ClientUbicacion
    {
        if (array_key_exists($clave, self::TIPOS)) {
            return $this->client->ubicaciones()->where('tipo', $clave)->first();
        }

        return $this->ubicacionExtra($clave);
    }

    /** Nombre actual de una dirección: el puesto por el usuario o el de siempre. */
    private function nombre(string $clave): string
    {
        if (array_key_exists($clave, self::TIPOS)) {
            return $this->fila($clave)?->nombre ?: self::TIPOS[$clave];
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
        $fila = $this->fila($clave);

        return ['lat' => $fila?->latitud, 'lng' => $fila?->longitud];
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
