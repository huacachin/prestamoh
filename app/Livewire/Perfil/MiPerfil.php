<?php

namespace App\Livewire\Perfil;

use App\Models\User;
use App\Support\AgenteNavegador;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Mi perfil (16/10/2026, Antony): cada usuario ve sus datos (solo lectura:
 * los cambia quien administra usuarios), cambia su contraseña confirmando la
 * actual y ve sus sesiones activas (navegador, sistema, IP y última actividad)
 * con la opción de cerrar todas las demás. Las sesiones salen de la tabla
 * `sessions` (SESSION_DRIVER=database, como Jetstream): con otro driver se
 * avisa y la lista no se muestra.
 */
class MiPerfil extends Component
{
    public string $passwordActual = '';

    public string $passwordNueva = '';

    public string $passwordNueva_confirmation = '';

    /** Contraseña actual para confirmar el cierre de las otras sesiones. */
    public string $passwordSesiones = '';

    private function usuario(): User
    {
        /** @var User $u */
        $u = auth()->user();

        return $u;
    }

    public function cambiarPassword(): void
    {
        $u = $this->usuario();
        $this->validate([
            'passwordActual' => ['required', 'current_password'],
            'passwordNueva' => ['required', 'string', 'min:8', 'confirmed', 'different:passwordActual'],
        ], ['passwordActual.current_password' => 'La contraseña actual no es correcta.'], [
            'passwordActual' => 'contraseña actual', 'passwordNueva' => 'contraseña nueva',
        ]);

        $u->sinAuditoriaAutomatica(fn () => $u->update(['password' => Hash::make($this->passwordNueva)]));
        Audit::log('Cambió su contraseña', $u);

        $this->reset('passwordActual', 'passwordNueva', 'passwordNueva_confirmation');
        $this->dispatch('successAlert', ['message' => 'Contraseña cambiada.']);
    }

    /** Las sesiones viven en la base de datos (si no, no hay lista que mostrar). */
    public static function sesionesDisponibles(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Sesiones abiertas del usuario, la actual primero y luego por última actividad.
     *
     * @return Collection<int, array{id: string, actual: bool, ip: ?string, agente: array{navegador: string, plataforma: string, dispositivo: string, texto: string}, ultima: Carbon}>
     */
    public function sesiones(): Collection
    {
        if (! self::sesionesDisponibles()) {
            return collect();
        }
        $actual = (string) session()->getId();

        return $this->tablaSesiones()
            ->where('user_id', $this->usuario()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                'id' => (string) $s->id,
                'actual' => (string) $s->id === $actual,
                'ip' => $s->ip_address,
                'agente' => AgenteNavegador::describir($s->user_agent),
                'ultima' => Carbon::createFromTimestamp((int) $s->last_activity, config('app.timezone')),
            ])
            ->sortByDesc(fn ($s) => [$s['actual'], $s['ultima']->timestamp])
            ->values();
    }

    /** Cierra todas las sesiones del usuario menos esta, confirmando con su contraseña. */
    public function cerrarOtrasSesiones(): void
    {
        $this->validate(
            ['passwordSesiones' => ['required', 'current_password']],
            ['passwordSesiones.current_password' => 'La contraseña no es correcta.'],
            ['passwordSesiones' => 'contraseña']
        );
        $this->reset('passwordSesiones');

        if (! self::sesionesDisponibles()) {
            $this->dispatch('errorAlert', ['message' => 'Las sesiones no se guardan en la base de datos: no se pueden cerrar desde aquí.']);

            return;
        }

        $u = $this->usuario();
        $cerradas = $this->tablaSesiones()
            ->where('user_id', $u->id)
            ->where('id', '!=', (string) session()->getId())
            ->delete();

        Audit::log("Cerró sus otras sesiones ({$cerradas})", $u, ['sesiones_cerradas' => $cerradas]);
        $this->dispatch('successAlert', ['message' => $cerradas === 0 ? 'No había otras sesiones abiertas.' : "Se cerraron {$cerradas} sesión(es) más."]);
    }

    private function tablaSesiones(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
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
