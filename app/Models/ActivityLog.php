<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * Registro de auditoría (tabla activity_log de spatie) con las columnas
 * propias traídas de newtaxivan (26/09): user_name, user_role, module,
 * old_data, new_data, changed_fields, ip_address, user_agent.
 * Es el activity_model de config/activitylog.php.
 */
class ActivityLog extends Activity
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'old_data' => 'array',
            'new_data' => 'array',
            'changed_fields' => 'array',
        ]);
    }
}
