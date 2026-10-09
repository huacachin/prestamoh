<?php

namespace App\Livewire\Hooks;

use App\Support\Audit;
use App\Support\HorarioEliminacion;
use App\Support\SinHorarioDeEliminacion;
use Livewire\ComponentHook;

/**
 * Guardia genérica del servidor (09/10/2026): intercepta toda llamada DIRECTA
 * a un método de eliminar de cualquier componente Livewire (delete*, destroy,
 * eliminar*, borrar, anular, questionDelete) y, si quien la hace no es
 * director y está fuera de la ventana de 6:00 a 11:00, no la ejecuta: avisa en
 * pantalla y deja rastro en la auditoría. Cubre el caso de la página abierta
 * desde antes de las 11, en la que el botón todavía se ve habilitado. Se
 * registra en AppServiceProvider.
 *
 * Límite: Livewire ejecuta los métodos que llegan como EVENTO (register_destroy,
 * attachment_destroy tras el SweetAlert) dentro de su propio hook de eventos,
 * con $method = '__dispatch', así que este hook no los ve. Por eso la guardia
 * de verdad —y la única que conoce el registro para la regla del mismo día— es
 * ConReglasDeEliminacion::eliminacionBloqueada() dentro de cada método.
 */
class BloquearEliminacionFueraDeHorario extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        // Las galerías de adjuntos de caja eliminan a cualquier hora (SinHorarioDeEliminacion);
        // su regla del mismo día la aplica el propio método con ConReglasDeEliminacion.
        if ($this->component instanceof SinHorarioDeEliminacion) {
            return;
        }
        if (! HorarioEliminacion::esMetodoDeEliminar((string) $method) || ! HorarioEliminacion::bloqueado()) {
            return;
        }

        Audit::log(
            'Intentó eliminar fuera de horario: '.class_basename($this->component).'::'.$method,
            null,
            ['parametros' => $params, 'ventana' => HorarioEliminacion::desde().':00-'.HorarioEliminacion::hasta().':00']
        );
        $this->component->dispatch('errorAlert', ['message' => HorarioEliminacion::mensaje()]);
        $returnEarly();
    }
}
