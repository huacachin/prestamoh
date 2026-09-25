<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Fecha "larga" de las cabeceras de día de los reportes de caja, con el
 * formato del legacy (flarga() en funcion.php): "Viernes 24 de Febrero del 2012".
 * Día y mes con mayúscula inicial (25/09: salían en minúscula).
 */
class FechaLarga
{
    public static function etiqueta(Carbon|string $fecha): string
    {
        $c = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return self::capital($c->translatedFormat('l')).' '.$c->format('d').' de '.self::capital($c->translatedFormat('F')).' del '.$c->format('Y');
    }

    private static function capital(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}
