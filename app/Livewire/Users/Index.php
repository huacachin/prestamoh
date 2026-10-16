<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Support\Audit;
use App\Support\ConReglasDeEliminacion;
use App\Support\SesionesActivas;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use ConReglasDeEliminacion;

    #[Url(as: 'buscar', except: '')]
    public $search = '';

    #[Url(as: 'estado', except: 'active')]
    public $estado = 'active'; // active | inactive | all

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (! auth()->user()?->can('configuracion.usuarios')) {
            abort(403);
        }

        $user = User::findOrFail($id);
        // Director es el rol super: no se puede desactivar.
        if ($user->hasRole('director')) {
            abort(403);
        }
        if ($this->eliminacionBloqueada($user)) {
            return;
        }

        $user->sinAuditoriaAutomatica(fn () => $user->update(['status' => 'inactive']));
        // 16/10: al cesarlo se cierran sus sesiones abiertas (el login ya no lo deja entrar, pero una sesión
        // viva seguía sirviendo hasta caducar). Nunca la del navegador que desactiva (no es la suya).
        $cerradas = SesionesActivas::cerrarDeUsuario($user->id, conservarLaActual: true);
        Audit::log("Desactivó el usuario {$user->username} ({$user->name})", $user, $cerradas ? ['sesiones_cerradas' => $cerradas] : []);
        $this->dispatch('successAlert', ['message' => 'Usuario desactivado correctamente'.($cerradas ? " y {$cerradas} sesión(es) suya(s) cerrada(s)" : '')]);
    }

    public function reactivate(int $id): void
    {
        if (! auth()->user()?->can('configuracion.usuarios')) {
            abort(403);
        }

        $user = User::findOrFail($id);
        $user->sinAuditoriaAutomatica(fn () => $user->update(['status' => 'active']));
        Audit::log("Reactivó el usuario {$user->username} ({$user->name})", $user);
        $this->dispatch('successAlert', ['message' => 'Usuario reactivado correctamente']);
    }

    /**
     * El nombre lo resuelve el COMPONENTE, no el blade: pasarlo por el
     * atributo rompía con apellidos que llevan comilla (O'Brien) y metía
     * dato de la base en el HTML.
     */
    public function questionDelete(int $id): void
    {
        $user = User::find($id);
        if ($user && $this->eliminacionBloqueada($user)) {
            return;
        }

        $this->dispatch('questionDelete', [
            'id' => $id,
            'role' => 'usuario',
            'name' => $user ? trim($user->name.' ('.$user->username.')') : '',
            // La acción DESACTIVA (status inactive), no borra: que el modal
            // diga la verdad y que se sepa que es reversible.
            'accion' => 'desactivar',
            'nota' => 'Podrá reactivarlo después desde esta misma pantalla.',
        ]);
    }

    public function render()
    {
        $term = trim($this->search);

        $users = User::query()
            ->when($this->estado !== 'all', fn ($q) => $q->where('status', $this->estado))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('username', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
            )
            )
            // roles.permissions: el listado muestra los permisos EFECTIVOS (rol + directos) sin una consulta por fila.
            ->with(['headquarter', 'roles.permissions', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('livewire.users.index', compact('users'));
    }
}
