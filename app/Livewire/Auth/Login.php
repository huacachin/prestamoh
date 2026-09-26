<?php

namespace App\Livewire\Auth;

use App\Support\Audit;
use App\Support\MenuLateral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function mount(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended(route(MenuLateral::rutaInicio(auth()->user())));
        }
    }

    protected function throttleKey(): string
    {
        // Limitar por username + IP
        return strtolower($this->username).'|'.request()->ip();
    }

    public function authenticate()
    {
        $this->validate();

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());
            throw ValidationException::withMessages([
                'username' => "Demasiados intentos. Intenta en {$seconds} segundos.",
            ]);
        }

        // Solo usuarios activos pueden iniciar sesión (los cesados quedan bloqueados)
        if (! Auth::attempt(
            ['username' => $this->username, 'password' => $this->password, 'status' => 'active'],
            $this->remember
        )) {
            RateLimiter::hit($this->throttleKey(), 60); // 60s por intento fallido
            // 25/09: el intento fallido también queda en la base (antes solo en el archivo audit-*.log).
            Audit::log('Intento de inicio de sesión fallido', null, ['username' => (string) $this->username]);

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        Audit::log('Inicio de sesión');

        return redirect()->intended(route(MenuLateral::rutaInicio(auth()->user())));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
