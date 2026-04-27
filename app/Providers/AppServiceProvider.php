<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Event::listen(Login::class, function (Login $event) {
            Log::channel('audit')->info('login', [
                'user_id'  => $event->user->id ?? null,
                'username' => $event->user->username ?? $event->user->email ?? null,
                'name'     => $event->user->name ?? null,
                'ip'       => request()->ip(),
                'agent'    => substr((string) request()->userAgent(), 0, 200),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            Log::channel('audit')->info('logout', [
                'user_id'  => $event->user->id ?? null,
                'username' => $event->user->username ?? $event->user->email ?? null,
                'ip'       => request()->ip(),
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            Log::channel('audit')->warning('login_failed', [
                'username' => $event->credentials['username'] ?? $event->credentials['email'] ?? null,
                'ip'       => request()->ip(),
                'agent'    => substr((string) request()->userAgent(), 0, 200),
            ]);
        });
    }
}
