<?php

namespace App\Livewire\Perfil;

use App\Models\User;
use App\Support\Audit;
use App\Support\SesionesActivas;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Mi perfil (16/10/2026, Antony): cada usuario ve sus datos (solo lectura:
 * los cambia quien administra usuarios) y sus sesiones activas (navegador,
 * sistema, IP y última actividad) con la opción de cerrar todas las demás
 * confirmando con su contraseña. Sin cambio de contraseña (Antony, 16/10:
 * "solo quiero que el card de cambiar contraseña no aparezca"). Las sesiones salen de la tabla
 * `sessions` (SESSION_DRIVER=database, como Jetstream): con otro driver se
 * avisa y la lista no se muestra.
 *
 * La contraseña de confirmación NO es estado del componente: llega como
 * parámetro de la acción (desde el DOM) y nunca vuelve al navegador (Livewire
 * devuelve el estado en cada respuesta, wire:snapshot en el HTML, y con
 * `wire:model` quedaría a la vista tras un intento fallido).
 */
class MiPerfil extends Component
{
    private function usuario(): User
    {
        /** @var User $u */
        $u = auth()->user();

        return $u;
    }

    /** Las sesiones viven en la base de datos (si no, no hay lista que mostrar). */
    public static function sesionesDisponibles(): bool
    {
        return SesionesActivas::disponibles();
    }

    /** Sesiones abiertas del usuario, la actual primero y luego por última actividad. */
    public function sesiones(): Collection
    {
        return SesionesActivas::deUsuario($this->usuario()->id);
    }

    /** Cierra todas las sesiones del usuario menos esta, confirmando con su contraseña. */
    public function cerrarOtrasSesiones(string $password = ''): void
    {
        validator(
            ['passwordSesiones' => $password],
            ['passwordSesiones' => ['required', 'current_password']],
            ['passwordSesiones.current_password' => 'La contraseña no es correcta.'],
            ['passwordSesiones' => 'contraseña']
        )->validate();

        if (! self::sesionesDisponibles()) {
            $this->dispatch('errorAlert', ['message' => 'Las sesiones no se guardan en la base de datos: no se pueden cerrar desde aquí.']);

            return;
        }

        $u = $this->usuario();
        $cerradas = SesionesActivas::cerrarDeUsuario($u->id, conservarLaActual: true);

        Audit::log("Cerró sus otras sesiones ({$cerradas})", $u, ['sesiones_cerradas' => $cerradas]);
        $this->dispatch('perfil-sesiones-cerradas');
        $this->dispatch('successAlert', ['message' => $cerradas === 0 ? 'No había otras sesiones abiertas.' : "Se cerraron {$cerradas} sesión(es) más."]);
    }

    public function render()
    {
        $u = $this->usuario();

        return view('livewire.perfil.mi-perfil', [
            'usuario' => $u,
            'rol' => $u->roles->first()?->name,
            'sede' => $u->headquarter?->name,
            'sesiones' => $this->sesiones(),
            'sesionesDisponibles' => self::sesionesDisponibles(),
        ]);
    }
}
