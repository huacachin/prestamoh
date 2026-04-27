<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->is('livewire/*', 'up', 'assets/*', 'build/*', 'storage/*', 'favicon.ico')
            && auth()->check()
        ) {
            $user = auth()->user();
            $route = $request->route();
            Log::channel('audit')->info('module_access', [
                'user_id'   => $user->id,
                'username'  => $user->username ?? $user->email ?? null,
                'name'      => $user->name ?? null,
                'route'     => $route?->getName(),
                'path'      => $request->path(),
                'ip'        => $request->ip(),
                'agent'     => substr((string) $request->userAgent(), 0, 200),
            ]);
        }

        return $next($request);
    }
}
