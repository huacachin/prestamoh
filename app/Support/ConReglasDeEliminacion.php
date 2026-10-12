<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Para los componentes Livewire que eliminan algo (09/10/2026). Al inicio de
 * cada método que elimina —y del questionDelete que abre la confirmación—,
 * con el registro ya cargado:
 *
 *     if ($this->eliminacionBloqueada($registro)) { return; }
 *
 * Aplica las reglas de HorarioEliminacion (quien no es director: solo de 6:00
 * a 11:00 y solo lo creado hoy), avisa en pantalla y deja rastro en la
 * auditoría. Es la guardia que de verdad protege: el hook de Livewire solo ve
 * las llamadas directas, no los métodos que llegan como evento
 * (register_destroy, attachment_destroy) después del SweetAlert.
 *
 * Desde el 12/10/2026 también para EDITAR (ubicaciones GPS de vehículos):
 * edicionBloqueada($registro) aplica solo la regla del mismo día, sin ventana
 * horaria, y deja el intento en la auditoría igual.
 */
trait ConReglasDeEliminacion
{
    /** true (y ya avisó) cuando quien está logueado no puede eliminar $registro ahora. */
    protected function eliminacionBloqueada(mixed $registro, ?string $accion = null): bool
    {
        return $this->accionBloqueada($registro, 'eliminar', $accion ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? '?');
    }

    /** true (y ya avisó) cuando quien está logueado no puede editar $registro: solo lo registrado hoy, salvo el director. */
    protected function edicionBloqueada(mixed $registro, ?string $accion = null): bool
    {
        return $this->accionBloqueada($registro, 'editar', $accion ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? '?');
    }

    private function accionBloqueada(mixed $registro, string $verbo, string $accion): bool
    {
        $motivo = HorarioEliminacion::motivoBloqueo($registro, null, null, $verbo);
        if ($motivo === null) {
            return false;
        }

        $porHorario = $motivo === HorarioEliminacion::mensaje();
        Audit::log(
            ($porHorario ? "Intentó {$verbo} fuera de horario: " : "Intentó {$verbo} un registro de otro día: ")
                .class_basename(static::class).'::'.$accion,
            $registro instanceof Model ? $registro : null,
            array_filter([
                'motivo' => $motivo,
                'registro_id' => is_object($registro) && ! ($registro instanceof \DateTimeInterface) ? ($registro->id ?? null) : null,
                'creado_el' => HorarioEliminacion::creadoEl($registro)?->toDateTimeString(),
            ], fn ($v) => $v !== null)
        );
        $this->dispatch('errorAlert', ['message' => $motivo]);

        return true;
    }
}
