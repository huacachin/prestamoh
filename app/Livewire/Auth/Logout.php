<?php

namespace App\Livewire\Auth;

use App\Support\Audit;
use Livewire\Attributes\On;
use Livewire\Component;

class Logout extends Component
{
    public function questionLogout()
    {
        $this->dispatch('questionLogout');
    }

    #[On('logout')]
    public function logout()
    {
        Audit::log('Cerró sesión'); // antes del logout, para que quede el usuario
        auth()->logout();

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.logout');
    }
}
