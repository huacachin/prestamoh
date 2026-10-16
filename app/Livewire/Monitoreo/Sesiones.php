<?php

namespace App\Livewire\Monitoreo;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AgenteNavegador;
use App\Support\Audit;
use App\Support\ColorDeIp;
use App\Support\SesionesActivas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Monitoreo (16/10/2026, Antony): SOLO el director ve las sesiones abiertas
 * de todos los usuarios —un bloque por usuario con su nombre, sus sesiones
 * activas (dispositivo, navegador, IP, última actividad) y sus últimos
 * inicios de sesión (de la auditoría)— y puede cerrar una sesión o todas las
 * de un usuario. La sesión del propio director desde la que mira nunca se
 * cierra desde aquí. Cada cierre queda en la auditoría. Solo usuarios activos
 * (un cesado sale solo mientras le quede una sesión abierta).
 */
class Sesiones extends Component
{
    public string $buscar = '';

    public bool $soloConSesion = false;

    /** Rango de fechas de los inicios de sesión (Y-m-d, vacío = sin límite). 16/10 (Antony): datepicker del sistema. */
    public string $desde = '';

    public string $hasta = '';

    /** Filas de auditoría de inicios, leídas una sola vez por petición. */
    private ?Collection $filasInicio = null;

    public function mount(): void
    {
        $this->soloDirector();
    }

    private function soloDirector(): void
    {
        abort_unless(auth()->user()?->hasRole('director') ?? false, 403, 'Monitoreo es solo para el director.');
    }

    public function limpiarFechas(): void
    {
        $this->desde = '';
        $this->hasta = '';
    }

    /**
     * Rango efectivo [desde 00:00, hasta 23:59:59] en hora de Lima, o null en
     * cada extremo si está vacío o mal escrito; si vienen al revés, se invierten.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function rango(): array
    {
        $leer = function (string $v): ?Carbon {
            $v = trim($v);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $v, config('app.timezone'));
            } catch (\Throwable) {
                return null;
            }
        };
        $d = $leer($this->desde)?->startOfDay();
        $h = $leer($this->hasta)?->endOfDay();
        if ($d && $h && $d->gt($h)) {
            [$d, $h] = [$h->copy()->startOfDay(), $d->copy()->endOfDay()];
        }

        return [$d, $h];
    }

    /** Cierra UNA sesión de un usuario (nunca la del director que mira). */
    public function cerrarSesion(string $id): void
    {
        $this->soloDirector();
        $sesion = SesionesActivas::porUsuario()->flatten(1)->firstWhere('id', $id);
        if (! $sesion || $sesion['actual']) {
            $this->dispatch('errorAlert', ['message' => $sesion ? 'Esa es tu sesión actual: ciérrala desde Cerrar Sesión.' : 'Esa sesión ya estaba cerrada.']);

            return;
        }
        $u = User::find($sesion['user_id']);
        if (! SesionesActivas::cerrarUna($id, $sesion['user_id'])) {
            $this->dispatch('errorAlert', ['message' => 'Esa sesión ya estaba cerrada.']);

            return;
        }
        Audit::log('Cerró una sesión de '.($u?->username ?? "usuario #{$sesion['user_id']}")." ({$sesion['agente']['texto']}, IP {$sesion['ip']})", $u, [
            'sesion' => ['ip' => $sesion['ip'], 'agente' => $sesion['agente']['texto'], 'ultima_actividad' => $sesion['ultima']->toDateTimeString()],
        ]);
        $this->dispatch('successAlert', ['message' => 'Sesión cerrada: '.($u?->username ?? '').' · '.$sesion['agente']['texto'].'.']);
    }

    /** Cierra TODAS las sesiones de un usuario (si es el propio director, todas menos la que mira). */
    public function cerrarTodas(int $userId): void
    {
        $this->soloDirector();
        $u = User::find($userId);
        if (! $u) {
            return;
        }
        $cerradas = SesionesActivas::cerrarDeUsuario($userId, conservarLaActual: true);
        if ($cerradas === 0) {
            $this->dispatch('errorAlert', ['message' => "{$u->username} no tenía sesiones abiertas."]);

            return;
        }
        Audit::log("Cerró las sesiones de {$u->username} ({$cerradas})", $u, ['sesiones_cerradas' => $cerradas]);
        $this->dispatch('successAlert', ['message' => "Se cerraron {$cerradas} sesión(es) de {$u->username}."]);
    }

    /**
     * Usuarios con sus sesiones e inicios recientes: primero los que tienen
     * sesión abierta (más sesiones arriba), luego por nombre.
     *
     * @return Collection<int, array{usuario: User, rol: ?string, sesiones: Collection, inicios: Collection, ips: Collection, fallidos: int}>
     */
    public function bloques(): Collection
    {
        $sesiones = SesionesActivas::porUsuario();
        $inicios = $this->inicios();
        $fallidos = $this->intentosFallidos();
        $termino = mb_strtolower(trim($this->buscar));

        // 16/10 (Antony): solo usuarios activos (status 1); un cesado aparece únicamente mientras conserve
        // alguna sesión abierta, para poder cerrársela.
        return User::with('roles')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $sesiones->keys()->all()))
            ->when($termino !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', ["%{$termino}%"])->orWhereRaw('lower(username) like ?', ["%{$termino}%"])))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'usuario' => $u,
                'rol' => $u->roles->first()?->name,
                'sesiones' => $sesiones->get($u->id, collect()),
                'inicios' => $inicios->get($u->id, collect()),
                // IPs distintas de sus inicios con cuántas veces entró desde cada una (la más usada primero).
                'ips' => $inicios->get($u->id, collect())->pluck('ip')->filter()->countBy()->sortDesc(),
                'fallidos' => (int) ($fallidos[mb_strtolower((string) $u->username)] ?? 0),
            ])
            ->filter(fn (array $b) => ! $this->soloConSesion || $b['sesiones']->isNotEmpty())
            ->sortBy(fn (array $b) => [-$b['sesiones']->count(), mb_strtolower((string) $b['usuario']->name)])
            ->values();
    }

    /**
     * TODOS los inicios de sesión por usuario (auditoría "Inicio de sesión",
     * del más reciente al más viejo): fecha, dispositivo/navegador e IP.
     * 16/10 (Antony): antes eran los 8 últimos; ahora se listan todos (en prod
     * son unos cientos en total) y se pintan en un recuadro con scroll.
     *
     * @return Collection<int, Collection<int, array{fecha: Carbon, ip: ?string, agente: string}>>
     */
    private function inicios(): Collection
    {
        [$desde, $hasta] = $this->rango();

        return $this->filasDeInicio()
            ->when($desde, fn (Collection $f) => $f->filter(fn (ActivityLog $a) => $a->created_at && $a->created_at->gte($desde)))
            ->when($hasta, fn (Collection $f) => $f->filter(fn (ActivityLog $a) => $a->created_at && $a->created_at->lte($hasta)))
            ->groupBy('causer_id')
            ->map(fn (Collection $filas) => $filas->map(function (ActivityLog $a) {
                // Las filas anteriores al 25/09 no traen IP ni navegador: se muestra "—" en vez de "desconocido".
                $ua = $a->user_agent ?? ($a->properties['contexto']['agente'] ?? null);

                return [
                    'fecha' => $a->created_at,
                    'ip' => $a->ip_address ?? ($a->properties['contexto']['ip'] ?? null),
                    'agente' => $ua ? AgenteNavegador::describir($ua)['texto'] : '—',
                ];
            })->values());
    }

    /** @return Collection<int, ActivityLog> */
    private function filasDeInicio(): Collection
    {
        return $this->filasInicio ??= ActivityLog::query()
            ->where('log_name', Audit::LOG)
            ->where('description', 'Inicio de sesión')
            ->whereNotNull('causer_id')
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'causer_id', 'ip_address', 'user_agent', 'properties', 'created_at']);
    }

    /**
     * Un color por IP distinta en toda la página, asignado por orden de primera
     * aparición (la más antigua primero: una IP nueva no cambia los colores de
     * las de antes). Así se ve a primera vista si alguien entró desde IPs distintas.
     *
     * @return array<string, array{bg: string, borde: string, texto: string}>
     */
    private function paletaDeIps(): array
    {
        // Sobre TODOS los inicios, no solo los del rango filtrado: así una IP conserva su color al cambiar las fechas.
        $ips = $this->filasDeInicio()
            ->sortBy(fn (ActivityLog $a) => $a->created_at?->timestamp ?? 0)
            ->map(fn (ActivityLog $a) => $a->ip_address ?? ($a->properties['contexto']['ip'] ?? null));

        return ColorDeIp::paleta($ips);
    }

    /** Intentos fallidos de los últimos 7 días por username (en minúsculas). */
    private function intentosFallidos(): array
    {
        return ActivityLog::query()
            ->where('log_name', Audit::LOG)
            ->where('description', 'Intento de inicio de sesión fallido')
            ->where('created_at', '>=', now()->subDays(7))
            ->get(['properties'])
            ->countBy(fn (ActivityLog $a) => mb_strtolower((string) ($a->properties['username'] ?? '')))
            ->all();
    }

    public function render()
    {
        $bloques = $this->bloques();

        return view('livewire.monitoreo.sesiones', [
            'bloques' => $bloques,
            'paleta' => $this->paletaDeIps(),
            'rango' => $this->rango(),
            'disponibles' => SesionesActivas::disponibles(),
            'totalSesiones' => $bloques->sum(fn (array $b) => $b['sesiones']->count()),
            'usuariosConSesion' => $bloques->filter(fn (array $b) => $b['sesiones']->isNotEmpty())->count(),
            // 16/10 (look & feel): indicadores de actividad reciente y de intentos fallidos de la semana.
            'activasAhora' => $bloques->sum(fn (array $b) => $b['sesiones']->where('activa', true)->count()),
            'fallidosSemana' => $bloques->sum(fn (array $b) => $b['fallidos']),
        ]);
    }
}
