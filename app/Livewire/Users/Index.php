<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $search = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->can('configuracion.usuarios')) {
            abort(403);
        }

        $user = User::findOrFail($id);
        // Director es el rol super: no se puede desactivar.
        if ($user->hasRole('director')) {
            abort(403);
        }

        $user->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Usuario desactivado correctamente']);
    }

    public function questionDelete(int $id, string $name = ''): void
    {
        $this->dispatch('questionDelete', ['id' => $id, 'name' => $name]);
    }

    public function render()
    {
        $term = trim($this->search);

        $users = User::query()
            ->where('status', 'active')
            ->when($term !== '', fn ($q) =>
                $q->where(fn ($w) =>
                    $w->where('username', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                )
            )
            ->with(['headquarter', 'roles', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('livewire.users.index', compact('users'));
    }
}
