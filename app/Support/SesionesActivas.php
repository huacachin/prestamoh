<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sesiones abiertas leídas de la tabla `sessions` (SESSION_DRIVER=database,
 * como hace Jetstream). Las usan Mi perfil (las propias) y Monitoreo (todas,
 * solo director). Con otro driver no hay lista: disponibles() avisa.
 *
 * Cada sesión se entrega como:
 *   {id, user_id, actual, ip, agente: AgenteNavegador::describir(), ultima: Carbon, activa: bool}
 * donde `actual` es la sesión del navegador que consulta y `activa` = con
 * actividad en los últimos MINUTOS_ACTIVA minutos.
 */
final class SesionesActivas
{
    public const MINUTOS_ACTIVA = 5;

    public static function disponibles(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return Collection<int, array{id: string, user_id: ?int, actual: bool, ip: ?string, agente: array{navegador: string, plataforma: string, dispositivo: string, texto: string}, ultima: Carbon, activa: bool}> */
    public static function deUsuario(int $userId): Collection
    {
        return self::leer(fn (Builder $q) => $q->where('user_id', $userId));
    }

    /**
     * Todas las sesiones con usuario, agrupadas por user_id (la del navegador
     * que consulta primero, luego por última actividad).
     *
     * @return Collection<int, Collection<int, array{id: string, user_id: ?int, actual: bool, ip: ?string, agente: array{navegador: string, plataforma: string, dispositivo: string, texto: string}, ultima: Carbon, activa: bool}>>
     */
    public static function porUsuario(): Collection
    {
        return self::leer(fn (Builder $q) => $q->whereNotNull('user_id'))->groupBy('user_id');
    }

    /** Cierra las sesiones de un usuario (todas, o todas menos la del navegador que consulta). Devuelve cuántas. */
    public static function cerrarDeUsuario(int $userId, bool $conservarLaActual = true): int
    {
        if (! self::disponibles()) {
            return 0;
        }
        $q = self::tabla()->where('user_id', $userId);
        if ($conservarLaActual) {
            $q->where('id', '!=', (string) session()->getId());
        }

        return $q->delete();
    }

    /** Cierra UNA sesión (nunca la del navegador que consulta). true si existía y se borró. */
    public static function cerrarUna(string $id, ?int $userId = null): bool
    {
        if (! self::disponibles() || $id === (string) session()->getId()) {
            return false;
        }
        $q = self::tabla()->where('id', $id);
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }

        return $q->delete() > 0;
    }

    /** @param  callable(Builder): Builder  $filtro */
    private static function leer(callable $filtro): Collection
    {
        if (! self::disponibles()) {
            return collect();
        }
        $actual = (string) session()->getId();
        $corte = now()->subMinutes(self::MINUTOS_ACTIVA)->timestamp;

        return $filtro(self::tabla())
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                'id' => (string) $s->id,
                'user_id' => $s->user_id === null ? null : (int) $s->user_id,
                'actual' => (string) $s->id === $actual,
                'ip' => $s->ip_address,
                'agente' => AgenteNavegador::describir($s->user_agent),
                'ultima' => Carbon::createFromTimestamp((int) $s->last_activity, config('app.timezone')),
                'activa' => (int) $s->last_activity >= $corte,
            ])
            ->sortByDesc(fn ($s) => [$s['actual'], $s['ultima']->timestamp])
            ->values();
    }

    private static function tabla(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
