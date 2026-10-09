<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Reglas para ELIMINAR cuando no se es director (09/10/2026, pedido de Antony):
 *
 *  1. Horario: solo de 6:00 a 11:00 de la mañana (hora de Lima).
 *  2. Mismo día: solo lo que se registró ese mismo día; al día siguiente ya no.
 *
 * El director elimina a cualquier hora y cualquier registro. Fuera de regla el
 * botón se deshabilita en pantalla (horario-eliminar.js, que se reevalúa solo
 * aunque la página lleve horas abierta; lee la fecha del registro del atributo
 * data-creado que pinta @creadoEl) y el servidor rechaza la acción de todos
 * modos: el hook BloquearEliminacionFueraDeHorario para las llamadas directas
 * y el trait ConReglasDeEliminacion dentro de cada método que elimina (que es
 * el único sitio donde se conoce el registro y, además, el único que ve los
 * eliminados que llegan como evento register_destroy tras el SweetAlert).
 *
 * Qué cuenta como eliminar: los métodos Livewire que empiezan por delete,
 * destroy, eliminar, borrar, anular o questionDelete (el mismo criterio en PHP
 * y en el JS).
 */
final class HorarioEliminacion
{
    /** Ventana permitida por defecto: [DESDE, HASTA) en horas, zona config('app.timezone'). */
    public const DESDE = 6;

    public const HASTA = 11;

    public const METODOS = '/^(delete|destroy|eliminar|borrar|anular|questionDelete)/i';

    public static function activo(): bool
    {
        return (bool) config('auditoria.eliminar_horario.activo', true);
    }

    public static function mismoDiaActivo(): bool
    {
        return (bool) config('auditoria.eliminar_mismo_dia.activo', true);
    }

    public static function desde(): int
    {
        return (int) config('auditoria.eliminar_horario.desde', self::DESDE);
    }

    public static function hasta(): int
    {
        return (int) config('auditoria.eliminar_horario.hasta', self::HASTA);
    }

    public static function esDirector(?User $usuario): bool
    {
        return $usuario !== null && method_exists($usuario, 'hasRole') && $usuario->hasRole('director');
    }

    public static function enVentana(?Carbon $ahora = null): bool
    {
        $hora = (int) ($ahora ?? now())->copy()->setTimezone(config('app.timezone', 'America/Lima'))->format('G');

        return $hora >= self::desde() && $hora < self::hasta();
    }

    /** true cuando ESTE usuario no puede eliminar en este momento por el horario. */
    public static function bloqueado(?User $usuario = null, ?Carbon $ahora = null): bool
    {
        if (! self::activo()) {
            return false;
        }
        $usuario ??= auth()->user();

        return ! self::esDirector($usuario) && ! self::enVentana($ahora);
    }

    /**
     * Fecha de creación de lo que se va a eliminar: un modelo o fila (created_at),
     * una fecha suelta, o null cuando no se conoce.
     */
    public static function creadoEl(mixed $registro): ?Carbon
    {
        if ($registro instanceof \DateTimeInterface) {
            return Carbon::instance($registro);
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
     * Por qué ESTE usuario no puede eliminar ESE registro ahora, o null si puede.
     * Sin fecha de creación conocida (null) no se elimina: solo el director.
     */
    public static function motivoBloqueo(mixed $registro, ?User $usuario = null, ?Carbon $ahora = null): ?string
    {
        $usuario ??= auth()->user();
        if (self::esDirector($usuario)) {
            return null;
        }
        if (self::bloqueado($usuario, $ahora)) {
            return self::mensaje();
        }
        if (! self::mismoDiaActivo()) {
            return null;
        }
        $creadoEl = self::creadoEl($registro);
        if ($creadoEl === null) {
            return self::mensajeSinFecha();
        }

        return self::esDeHoy($creadoEl, $ahora) ? null : self::mensajeMismoDia();
    }

    public static function esMetodoDeEliminar(string $metodo): bool
    {
        return (bool) preg_match(self::METODOS, $metodo);
    }

    public static function mensaje(): string
    {
        return sprintf('Eliminar solo está habilitado de %d:00 a %d:00 de la mañana (hora de Lima). Fuera de ese horario, pídeselo al director.', self::desde(), self::hasta());
    }

    public static function mensajeMismoDia(): string
    {
        return 'Solo se puede eliminar el mismo día en que se registró. Pasado ese día, pídeselo al director.';
    }

    public static function mensajeSinFecha(): string
    {
        return 'Este dato no tiene fecha de registro: solo el director puede eliminarlo.';
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
            'activo' => self::activo(),
            'director' => self::esDirector($usuario ?? auth()->user()),
            'desde' => self::desde(),
            'hasta' => self::hasta(),
            'mensaje' => self::mensaje(),
            'mismoDia' => self::mismoDiaActivo(),
            'mensajeDia' => self::mensajeMismoDia(),
            'mensajeSinFecha' => self::mensajeSinFecha(),
            'metodos' => '^(?:\\s*\\$captura\\(\\s*[\'"])?(delete|destroy|eliminar|borrar|anular|questionDelete)',
        ];
    }
}
