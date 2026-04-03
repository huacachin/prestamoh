<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Illuminate\Http\Request;

class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'username' => ['required','string'],
            'password' => ['required','string'],
            'remember' => ['boolean'],
        ];
    }

    public function mount(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended('/dashboard');
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

        if (! Auth::attempt(
            ['username' => $this->username, 'password' => $this->password],
            $this->remember
        )) {
            RateLimiter::hit($this->throttleKey(), 60); // 60s por intento fallido
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        return redirect()->intended('/departures');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
