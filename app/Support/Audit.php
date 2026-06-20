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

        $logger->log($descripcion);
    }
}
