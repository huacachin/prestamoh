<?php

namespace App\Livewire\Auth;

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
        auth()->logout();
        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.logout');
    }
}
