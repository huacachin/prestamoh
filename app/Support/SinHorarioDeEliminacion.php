<?php

namespace App\Support;

/**
 * Marca (09/10/2026, Antony: "el rol de Licet debe poder eliminar adjuntos de
 * ingresos y egresos del mismo día") para lo que se puede eliminar a CUALQUIER
 * hora, sin la ventana de 6:00 a 11:00 de HorarioEliminacion. La regla del
 * mismo día sigue: solo lo registrado hoy, y solo con el permiso de siempre.
 *
 * Se pone en el modelo (para ConReglasDeEliminacion y el data-sin-horario que
 * pinta @creadoEl) y en el componente Livewire que lo elimina (para que el
 * hook BloquearEliminacionFueraDeHorario no corte la llamada directa).
 */
interface SinHorarioDeEliminacion {}
