<?php

namespace App\Support\Legal;

use App\Support\NumerosEnLetras;
use Carbon\Carbon;

/**
 * Fechas en formato de documento legal peruano. El contrato consigna la fecha
 * dos veces (cláusula de obligación y línea de firma) y ambas deben salir del
 * MISMO valor — hoy se tipean por separado y hay plantillas donde no cuadran.
 */
final class FechaEnLetras
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo',
        6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
        10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /** Nombre del mes en minúsculas: mes(8) → "agosto" */
    public static function mes(int $mes): string
    {
        return self::MESES[$mes];
    }

    /** "24 de agosto de 2026" */
    public static function simple(Carbon|string $fecha): string
    {
        $f = Carbon::parse($fecha);

        return $f->day.' de '.self::MESES[$f->month].' de '.$f->year;
    }

    /** "a los veinticuatro días del mes de agosto del año dos mil veintiséis" */
    public static function larga(Carbon|string $fecha): string
    {
        $f = Carbon::parse($fecha);

        $dia = mb_strtolower(NumerosEnLetras::entero($f->day));
        $anio = mb_strtolower(NumerosEnLetras::entero($f->year));

        // "dieciseis" y "veintiseis" llevan tilde en minúsculas
        $dia = str_replace(['dieciseis', 'veintiseis', 'veintidos', 'veintitres'], ['dieciséis', 'veintiséis', 'veintidós', 'veintitrés'], $dia);
        $anio = str_replace(['dieciseis', 'veintiseis', 'veintidos', 'veintitres'], ['dieciséis', 'veintiséis', 'veintidós', 'veintitrés'], $anio);

        $prefijo = $f->day === 1 ? 'al primer día' : "a los {$dia} días";

        return "{$prefijo} del mes de ".self::MESES[$f->month]." del año {$anio}";
    }
}
