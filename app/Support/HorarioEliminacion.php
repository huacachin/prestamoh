<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Regla del MISMO DÍA para quien no es director (09/10/2026, pedido de Antony;
 * desde el 16/10/2026 es la ÚNICA regla: la ventana de 6:00 a 11:00 se quitó
 * a pedido suyo): eliminar, anular o editar solo lo que se registró ese mismo
 * día (hora de Lima); pasadas las 00:00 ya no. El director puede todo,
 * cualquier día.
 *
 * Dónde se aplica: en pantalla el botón se deshabilita (horario-eliminar.js,
 * que se reevalúa solo aunque la página lleve horas abierta y lee la fecha del
 * registro del atributo data-creado que pinta @creadoEl) y el servidor rechaza
 * la acción de todos modos con el trait ConReglasDeEliminacion dentro de cada
 * método que elimina o edita (único sitio que conoce el registro y que ve los
 * eliminados que llegan como evento register_destroy tras el SweetAlert).
 *
 * Qué cuenta como eliminar para el JS: los métodos Livewire que empiezan por
 * delete, destroy, eliminar, borrar, anular o questionDelete.
 *
 * La fecha que cuenta es la de la fila (created_at) o, si el registro
 * implementa ConFechaDeRegistro, la que él mismo da (las direcciones GPS:
 * cuándo se registraron las coordenadas de ahora). Sin fecha, solo el director.
 */
final class HorarioEliminacion
{
    public const METODOS = '/^(delete|destroy|eliminar|borrar|anular|questionDelete)/i';

    public static function mismoDiaActivo(): bool
    {
        return (bool) config('auditoria.eliminar_mismo_dia.activo', true);
    }

    public static function esDirector(?User $usuario): bool
    {
        return $usuario !== null && method_exists($usuario, 'hasRole') && $usuario->hasRole('director');
    }

    /**
     * Fecha de registro de lo que se va a eliminar o editar: un modelo o fila
     * (created_at, o su propia fecha si implementa ConFechaDeRegistro), una
     * fecha suelta, o null cuando no se conoce.
     */
    public static function creadoEl(mixed $registro): ?Carbon
    {
        if ($registro instanceof \DateTimeInterface) {
            return Carbon::instance($registro);
        }
        if ($registro instanceof ConFechaDeRegistro) {
            return $registro->registradoEl();
        }
        if ($registro instanceof Model || is_object($registro)) {
            $registro = $registro->created_at ?? null;
        }
        if ($registro === null || $registro === '') {
            return null;
        }

        return $registro instanceof \DateTimeInterface ? Carbon::instance($registro) : Carbon::parse($registro);
    }

    public static function esDeHoy(?Carbon $creadoEl, ?Carbon $ahora = null): bool
    {
        if ($creadoEl === null) {
            return false;
        }
        $zona = config('app.timezone', 'America/Lima');

        return $creadoEl->copy()->setTimezone($zona)->toDateString()
            === ($ahora ?? now())->copy()->setTimezone($zona)->toDateString();
    }

    /**
     * Por qué ESTE usuario no puede eliminar/editar ESE registro ahora, o null
     * si puede. Sin fecha de registro conocida (null) solo el director.
     */
    public static function motivoBloqueo(mixed $registro, ?User $usuario = null, ?Carbon $ahora = null, string $accion = 'eliminar'): ?string
    {
        $usuario ??= auth()->user();
        if (self::esDirector($usuario) || ! self::mismoDiaActivo()) {
            return null;
        }
        $creadoEl = self::creadoEl($registro);
        if ($creadoEl === null) {
            return self::mensajeSinFecha($accion);
        }

        return self::esDeHoy($creadoEl, $ahora) ? null : self::mensajeMismoDia($accion);
    }

    public static function esMetodoDeEliminar(string $metodo): bool
    {
        return (bool) preg_match(self::METODOS, $metodo);
    }

    public static function mensajeMismoDia(string $accion = 'eliminar'): string
    {
        return "Solo se puede {$accion} el mismo día en que se registró. Pasado ese día, pídeselo al director.";
    }

    public static function mensajeSinFecha(string $accion = 'eliminar'): string
    {
        return "Este dato no tiene fecha de registro: solo el director puede {$accion}lo.";
    }

    /** Atributo para el botón de eliminar (directiva @creadoEl): la fecha que lee horario-eliminar.js. */
    public static function atributoCreado(mixed $registro): HtmlString
    {
        $fecha = self::creadoEl($registro)?->setTimezone(config('app.timezone', 'America/Lima'))->toDateString() ?? '';

        return new HtmlString('data-creado="'.e($fecha).'"');
    }

    /** Lo que necesita horario-eliminar.js para deshabilitar los botones en pantalla. */
    public static function paraJs(?User $usuario = null): array
    {
        return [
            'director' => self::esDirector($usuario ?? auth()->user()),
            'mismoDia' => self::mismoDiaActivo(),
            'mensajeDia' => self::mensajeMismoDia(),
            'mensajeSinFecha' => self::mensajeSinFecha(),
            'metodos' => '^(?:\\s*\\$captura\\(\\s*[\'"])?(delete|destroy|eliminar|borrar|anular|questionDelete)',
        ];
    }
}
