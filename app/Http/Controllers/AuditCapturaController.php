<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Auditoria\CapturaAuditoria;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve la captura de pantalla de una fila de auditoría (08/10/2026). Las
 * capturas viven en el disco privado, nunca bajo /storage: solo pasan por aquí
 * y la ruta exige el rol director, igual que el visor.
 */
class AuditCapturaController extends Controller
{
    public function __invoke(int $id): Response
    {
        $actividad = Activity::query()->where('log_name', Audit::LOG)->findOrFail($id);
        $ruta = $actividad->properties['captura'] ?? null;
        $disco = CapturaAuditoria::disco();

        abort_unless(is_string($ruta) && str_starts_with($ruta, 'auditoria/capturas/') && $disco->exists($ruta), 404);

        return $disco->response($ruta, "auditoria-{$id}.jpg", [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
