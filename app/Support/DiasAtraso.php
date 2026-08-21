<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Conteo ÚNICO de días de atraso para la mora. Antes vivía copiado en 4
 * sitios (Payments/Create::moraCalcAt, MoraPagada, MoraExonerada y
 * Credits/Schedule) con el riesgo de que divergieran; ahora todos consumen
 * este helper. Regla: mensual (tipo_planilla 3) cuenta días calendario;
 * semanal/diario excluye sábados y domingos. Devuelve 0 si aún no vence.
 */
class DiasAtraso
{
    public static function entre(int $tipoPlanilla, Carbon|string $desde, Carbon|string $hasta): int
    {
        $desde = $desde instanceof Carbon ? $desde : Carbon::parse($desde);
        $hasta = $hasta instanceof Carbon ? $hasta : Carbon::parse($hasta);

        $diff = (int) floor($desde->diffInDays($hasta, false));
        if ($diff <= 0) {
            return 0;
        }
        if ($tipoPlanilla === 3) {
            return $diff;
        }

        $dias = 0;
        $cur = $desde->copy();
        for ($i = 1; $i <= $diff; $i++) {
            $cur->addDay();
            if (! in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $dias++;
            }
        }

        return $dias;
    }
}
