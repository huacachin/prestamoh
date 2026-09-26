<?php

namespace App\Support\Auditoria;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Actions\LogActivityAction;
use Throwable;

/**
 * Acción de spatie/activitylog que persiste cada registro de auditoría (25/09).
 *
 * Traído de newtaxivan: a TODO registro (automático por modelo o manual vía
 * Audit::log) se le añade el contexto de la petición en properties.contexto
 * (IP, navegador, ruta y una copia del usuario con su rol), y un fallo al
 * insertar nunca tumba la operación de negocio: se reporta y sigue.
 */
class GuardarActividad extends LogActivityAction
{
    protected function transformChanges(Model $activity): void
    {
        if ($activity->log_name !== Audit::LOG) {
            return;
        }
        $props = collect($activity->properties ?? [])->toArray();
        $props['contexto'] = Audit::contexto();
        $activity->properties = $props;
    }

    protected function save(Model $activity): void
    {
        try {
            parent::save($activity);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
