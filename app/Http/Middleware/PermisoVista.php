<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Reemplaza el PermissionMiddleware de Spatie para que las rutas pasen por el
 * Gate (Gate::before en AppServiceProvider): así los permisos de visualización
 * por usuario también bloquean el acceso directo por URL. Spatie consultaba
 * hasAnyPermission (unión rol+directos) y se saltaba el Gate.
 */
class PermisoVista
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        foreach (explode('|', $permission) as $p) {
            if ($user->can($p)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes acceso a este módulo.');
    }
}
