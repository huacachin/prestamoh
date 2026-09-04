<?php

namespace App\Support;

/**
 * Catálogos de la ficha del vehículo (listas pedidas el 03/09). Select2 en
 * modo tags: son sugerencias, se puede escribir/agregar otro valor — la API
 * de placa y el historial migrado traen variantes libres. Combustible tiene
 * su propia clase (App\Support\Combustibles), del mismo pedido.
 */
class VehiculoCatalogos
{
    public const COLORES = ['Rojo', 'Gris', 'Blanco', 'Negro', 'Plata'];

    public const CATEGORIAS = ['M1', 'M2', 'M2-C3', 'N1'];

    public const CARROCERIAS = [
        'SEDAN', 'SUV', 'MINIBUS', 'MICROBUS', 'MULTIPROPÓSITO',
        'ÓMNIBUS', 'FURGON', 'STATION WAGON',
    ];

    /** Opciones del select conservando un valor guardado fuera del catálogo. */
    public static function paraValor(array $opciones, ?string $actual): array
    {
        $actual = trim((string) $actual);
        $mayusculas = array_map('mb_strtoupper', $opciones);

        if ($actual !== '' && ! in_array(mb_strtoupper($actual), $mayusculas, true)) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }
}
