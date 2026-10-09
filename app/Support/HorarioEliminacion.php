<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

/**
 * Ventana horaria para ELIMINAR (09/10/2026, pedido de Antony): quien no es
 * director solo puede eliminar de 6:00 a 11:00 de la mañana (hora de Lima).
 * Fuera de eso el botón se deshabilita en pantalla (horario-eliminar.js, que
 * se reevalúa solo aunque la página lleve horas abierta) y el servidor rechaza
 * la acción de todos modos (hook BloquearEliminacionFueraDeHorario).
 *
 * Qué cuenta como eliminar: los métodos Livewire que empiezan por delete,
 * destroy, eliminar, borrar o anular (el mismo criterio en PHP y en el JS).
 */
final class HorarioEliminacion
{
    /** Ventana permitida por defecto: [DESDE, HASTA) en horas, zona config('app.timezone'). */
    public const DESDE = 6;

    public const HASTA = 11;

    public static function activo(): bool
    {
        return (bool) config('auditoria.eliminar_horario.activo', true);
    }

    public static function desde(): int
    {
        return (int) config('auditoria.eliminar_horario.desde', self::DESDE);
    }

    public static function hasta(): int
    {
        return (int) config('auditoria.eliminar_horario.hasta', self::HASTA);
    }

    public const METODOS = '/^(delete|destroy|eliminar|borrar|anular)/i';

    public static function esDirector(?User $usuario): bool
    {
        return $usuario !== null && method_exists($usuario, 'hasRole') && $usuario->hasRole('director');
    }

    public static function enVentana(?Carbon $ahora = null): bool
    {
        $hora = (int) ($ahora ?? now())->copy()->setTimezone(config('app.timezone', 'America/Lima'))->format('G');

        return $hora >= self::desde() && $hora < self::hasta();
    }

    /** true cuando ESTE usuario no puede eliminar en este momento. */
    public static function bloqueado(?User $usuario = null, ?Carbon $ahora = null): bool
    {
        if (! self::activo()) {
            return false;
        }
        $usuario ??= auth()->user();

        return ! self::esDirector($usuario) && ! self::enVentana($ahora);
    }

    public static function esMetodoDeEliminar(string $metodo): bool
    {
        return (bool) preg_match(self::METODOS, $metodo);
    }

    public static function mensaje(): string
    {
        return sprintf('Eliminar solo está habilitado de %d:00 a %d:00 de la mañana (hora de Lima). Fuera de ese horario, pídeselo al director.', self::desde(), self::hasta());
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
            'metodos' => '^(?:\\s*\\$captura\\(\\s*[\'"])?(delete|destroy|eliminar|borrar|anular)',
        ];
    }
}
