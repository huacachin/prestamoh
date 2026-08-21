<?php

namespace App\Providers;

use App\Models\User;
use App\Support\PermisosVista;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Reemplaza el Gate::before de Spatie (register_permission_check_method=false).
        // Visualización de módulos (checkboxes de /users/{id}/perms): manda el
        // permiso DIRECTO del usuario — desmarcado = no ve el módulo aunque su
        // rol lo tenga. Director exento (siempre todo). El resto de permisos
        // mantiene la unión rol + directos de Spatie.
        Gate::before(function ($user, $ability) {
            if (! $user instanceof User || ! is_string($ability)) {
                return null;
            }

            try {
                if (in_array($ability, PermisosVista::LISTA, true)) {
                    return $user->hasRole('director') || $user->hasDirectPermission($ability);
                }

                return $user->checkPermissionTo($ability) ?: null;
            } catch (PermissionDoesNotExist) {
                return null;
            }
        });

        Event::listen(Login::class, function (Login $event) {
            Log::channel('audit')->info('login', [
                'user_id' => $event->user->id ?? null,
                'username' => $event->user->username ?? $event->user->email ?? null,
                'name' => $event->user->name ?? null,
                'ip' => request()->ip(),
                'agent' => substr((string) request()->userAgent(), 0, 200),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            Log::channel('audit')->info('logout', [
                'user_id' => $event->user->id ?? null,
                'username' => $event->user->username ?? $event->user->email ?? null,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            Log::channel('audit')->warning('login_failed', [
                'username' => $event->credentials['username'] ?? $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
                'agent' => substr((string) request()->userAgent(), 0, 200),
            ]);
        });
    }
}
