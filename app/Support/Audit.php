<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Helper centralizado de auditoría (sobre spatie/laravel-activitylog).
 * Registra acciones clave del sistema en el log 'auditoria', con el usuario
 * que la ejecutó (causer). El visor del módulo de auditoría (solo director)
 * lee de la tabla activity_log filtrando por log_name = 'auditoria'.
 */
class Audit
{
    public const LOG = 'auditoria';

    /**
     * Registra una acción de auditoría.
     *
     * @param  string      $descripcion  Texto legible de la acción.
     * @param  Model|null  $subject      Modelo afectado (opcional).
     * @param  array       $props        Propiedades extra (opcional).
     */
    public static function log(string $descripcion, ?Model $subject = null, array $props = []): void
    {
        $logger = activity(self::LOG)->causedBy(auth()->user());

        if ($subject) {
            $logger->performedOn($subject);
        }
        if (! empty($props)) {
            $logger->withProperties($props);
        }

        // El contexto (IP, navegador, usuario, ruta) lo añade GuardarActividad a
        // todo registro del log, sea manual o automático (trait Auditable).
        $logger->log($descripcion);
    }

    /**
     * Nombre de la ruta; en las peticiones de Livewire (todas van a
     * livewire.update) se guarda la página desde la que se hizo la acción.
     */
    private static function rutaDePeticion($request): ?string
    {
        if (! $request) {
            return null;
        }
        $nombre = $request->route()?->getName();
        if ($nombre === null || str_contains($nombre, 'livewire')) {
            $pagina = parse_url((string) $request->header('referer'), PHP_URL_PATH);
            if ($pagina) {
                return 'página '.$pagina;
            }
        }

        return $nombre ?? $request->path();
    }

    /**
     * Contexto de la petición que acompaña a cada registro (25/09, traído de
     * newtaxivan): IP, navegador, ruta y una copia del usuario con su rol, para
     * que el registro se entienda aunque el usuario cambie o se borre después.
     *
     * @return array{ip: ?string, agente: ?string, ruta: ?string, usuario: ?array{id: int, username: ?string, nombre: ?string, rol: ?string}}
     */
    public static function contexto(): array
    {
        $usuario = auth()->user();
        $enConsola = app()->runningInConsole() && ! app()->runningUnitTests();
        $request = $enConsola ? null : request();

        return [
            'ip' => $request?->ip(),
            'agente' => $request ? mb_substr((string) $request->userAgent(), 0, 200) : null,
            'ruta' => $enConsola
                ? 'consola: '.(($_SERVER['argv'][1] ?? '') ?: 'artisan')
                : self::rutaDePeticion($request),
            'usuario' => $usuario ? [
                'id' => $usuario->id,
                'username' => $usuario->username ?? null,
                'nombre' => $usuario->name ?? null,
                'rol' => method_exists($usuario, 'getRoleNames') ? ($usuario->getRoleNames()->first() ?? null) : null,
            ] : null,
        ];
    }
}
