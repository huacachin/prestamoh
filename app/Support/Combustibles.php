<?php

namespace App\Support;

/**
 * Combustibles de vehículo (lista pedida el 03/09). Select2 en modo tags:
 * el catálogo son sugerencias y se puede escribir/agregar uno nuevo —
 * la API de placa y el historial migrado traen variantes libres.
 */
class Combustibles
{
    public const OPCIONES = [
        'DIESEL',
        'GASOLINA',
        'BI-COMBUSTIBLE GLP',
        'BI-COMBUSTIBLE GNV',
        'GLP',
        'GNV',
    ];

    /** Opciones del select conservando un valor guardado fuera del catálogo. */
    public static function paraValor(?string $actual): array
    {
        $actual = trim((string) $actual);
        $opciones = self::OPCIONES;

        if ($actual !== '' && ! in_array(mb_strtoupper($actual), $opciones, true)) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }
}
