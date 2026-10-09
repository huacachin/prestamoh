<?php

namespace App\Livewire\Hooks;

use App\Support\Audit;
use App\Support\HorarioEliminacion;
use Livewire\ComponentHook;

/**
 * Guardia del servidor (09/10/2026): intercepta TODA llamada a un método de
 * eliminar de cualquier componente Livewire (delete*, destroy, eliminar*,
 * borrar, anular) y, si quien la hace no es director y está fuera de la
 * ventana de 6:00 a 11:00, no la ejecuta: avisa en pantalla y deja rastro en
 * la auditoría. Cubre el caso de la página abierta desde antes de las 11, en
 * la que el botón todavía se ve habilitado. Se registra en AppServiceProvider.
 */
class BloquearEliminacionFueraDeHorario extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
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
