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
 * (IP, navegador, ruta y una copia del usuario con su rol) y se rellenan las
 * columnas propias de la tabla (user_name, user_role, module, old_data,
 * new_data, changed_fields, ip_address, user_agent), y un fallo al insertar
 * nunca tumba la operación de negocio: se reporta y sigue.
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
        // Aquí ya pasó el enmascarado del trait (beforeActivityLogged), así que
        // old_data/new_data salen con la contraseña tapada.
        if ($activity->log_name === Audit::LOG) {
            $this->rellenarColumnas($activity);
        }

        try {
            parent::save($activity);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Columnas con la estructura de newtaxivan (26/09), a partir del contexto y los cambios. */
    private function rellenarColumnas(Model $activity): void
    {
        $ctx = collect($activity->properties ?? [])->get('contexto', []);
        $cambios = collect($activity->attribute_changes ?? [])->toArray();
        $nuevo = $cambios['attributes'] ?? null;
        $viejo = $cambios['old'] ?? null;
        $sujeto = $activity->subject_type;

        $activity->user_name = $ctx['usuario']['nombre'] ?? null;
        $activity->user_role = $ctx['usuario']['rol'] ?? null;
        $activity->ip_address = $ctx['ip'] ?? null;
        $activity->user_agent = $ctx['agente'] ?? null;
        $activity->module = $sujeto
            ? (config('auditoria.modulos')[$sujeto] ?? (method_exists($sujeto, 'auditModulo') ? $sujeto::auditModulo() : class_basename($sujeto)))
            : null;
        $activity->old_data = $viejo;
        $activity->new_data = $nuevo;
        $activity->changed_fields = ($activity->event === 'updated' && is_array($nuevo)) ? array_keys($nuevo) : null;
    }
}
