<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas del Área Legal (11/10/2026): si el módulo está oculto
 * (config legal.activo = false) responden 404, aunque el usuario conserve
 * los permisos legal.*. Alias 'legal.activo' en bootstrap/app.php.
 */
class ModuloLegalActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('legal.activo'), 404);

        return $next($request);
    }
}
