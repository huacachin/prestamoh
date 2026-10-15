<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Para los registros cuya fecha "de registro" no es la de su fila (created_at)
 * sino un dato propio (15/10/2026, direcciones GPS del cliente: la fila de
 * Casa o Negocio vive para siempre, pero sus coordenadas se registran y se
 * vuelven a registrar; la regla del mismo día de HorarioEliminacion mira la
 * fecha en que se registraron las coordenadas de ahora, que es la que se ve en
 * la columna Fecha). Sin fecha (null) solo el director edita o elimina.
 */
interface ConFechaDeRegistro
{
    public function registradoEl(): ?Carbon;
}
