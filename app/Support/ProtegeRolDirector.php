<?php

namespace App\Support;

use App\Models\ClaveSeguridad;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Para los componentes Livewire que asignan roles (alta y edición de usuarios,
 * 16/10/2026, Antony): "cada vez que alguien quiera cambiar el rol de director
 * a alguien, o de otro rol a director, hay que pedir una contraseña que solo
 * el gerente maneja". Antes de guardar, con el rol actual, el nuevo y la clave
 * tal como llegó del formulario:
 *
 *     if ($this->cambioDeRolBloqueado($rolActual, $rolNuevo, $usuario, $clave)) { return; }
 *
 * Si el cambio entra o sale de Director, exige la clave ROL_DIRECTOR
 * (ClaveSeguridad): sin clave configurada, vacía o incorrecta, avisa en el
 * campo claveDirector, deja rastro del intento en la auditoría y frena tras
 * cinco fallos en diez minutos. Los cambios entre otros roles no la piden.
 * Al terminar, registrarCambioDeRol() deja el cambio en la auditoría.
 *
 * La clave NO es estado del componente: llega como parámetro de la acción
 * (desde el DOM) y nunca vuelve al navegador en el snapshot de Livewire.
 */
trait ProtegeRolDirector
{
    public const INTENTOS_CLAVE = 5;

    public const SEGUNDOS_BLOQUEO_CLAVE = 600;

    /** El cambio entra o sale de Director (null = sin rol). */
    public static function cambioProtegido(?string $rolActual, ?string $rolNuevo): bool
    {
        return $rolActual !== $rolNuevo && ($rolActual === 'director' || $rolNuevo === 'director');
    }

    /** true (y ya avisó) cuando el cambio necesita la clave del gerente y no se dio la correcta. */
    protected function cambioDeRolBloqueado(?string $rolActual, ?string $rolNuevo, ?Model $afectado, string $clave): bool
    {
        if (! self::cambioProtegido($rolActual, $rolNuevo)) {
            return false;
        }
        $intento = trim($clave);

        if (! ClaveSeguridad::definida(ClaveSeguridad::ROL_DIRECTOR)) {
            $this->addError('claveDirector', 'No hay clave de seguridad configurada para dar o quitar el rol Director. Hay que crearla con el comando usuarios:clave-director.');

            return true;
        }

        $llave = 'clave-director:'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS_CLAVE)) {
            $minutos = max(1, (int) ceil(RateLimiter::availableIn($llave) / 60));
            $this->addError('claveDirector', "Demasiados intentos con la clave de seguridad. Espera {$minutos} minuto(s).");

            return true;
        }

        if ($intento === '') {
            $this->addError('claveDirector', 'Para dar o quitar el rol Director hace falta la clave de seguridad del gerente.');

            return true;
        }

        if (! ClaveSeguridad::verificar(ClaveSeguridad::ROL_DIRECTOR, $intento)) {
            RateLimiter::hit($llave, self::SEGUNDOS_BLOQUEO_CLAVE);
            Audit::log(
                'Intentó '.self::textoCambio($afectado instanceof User ? $afectado->username : null, $rolActual, $rolNuevo, false).' con una clave de seguridad incorrecta',
                $afectado,
                ['rol' => ['antes' => $rolActual, 'despues' => $rolNuevo], 'intentos' => RateLimiter::attempts($llave)]
            );
            $this->addError('claveDirector', 'Clave de seguridad incorrecta.');

            return true;
        }

        RateLimiter::clear($llave);

        return false;
    }

    /** Deja el cambio de rol en la auditoría (nada si el rol no cambió). */
    protected function registrarCambioDeRol(User $usuario, ?string $rolActual, ?string $rolNuevo): void
    {
        if ($rolActual === $rolNuevo) {
            return;
        }
        Audit::log(self::textoCambio($usuario->username, $rolActual, $rolNuevo, true), $usuario, [
            'rol' => ['antes' => $rolActual, 'despues' => $rolNuevo],
            'clave_seguridad' => self::cambioProtegido($rolActual, $rolNuevo),
        ]);
    }

    /**
     * "Cambió el rol de Guilmer de director a administrador" / "Asignó el rol director a Nicefaro" /
     * "Quitó el rol director a …"; en infinitivo ("cambiar el rol …") para la frase "Intentó …".
     */
    private static function textoCambio(?string $username, ?string $rolActual, ?string $rolNuevo, bool $pasado): string
    {
        $quien = $username !== null && $username !== '' ? $username : 'un usuario';
        if ($rolActual === null) {
            return ($pasado ? 'Asignó' : 'asignar')." el rol {$rolNuevo} a {$quien}";
        }
        if ($rolNuevo === null) {
            return ($pasado ? 'Quitó' : 'quitar')." el rol {$rolActual} a {$quien}";
        }

        return ($pasado ? 'Cambió' : 'cambiar')." el rol de {$quien} de {$rolActual} a {$rolNuevo}";
    }
}
