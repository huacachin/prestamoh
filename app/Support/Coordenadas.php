<?php

namespace App\Support;

/**
 * Lectura de coordenadas pegadas por el usuario. Compartido por la pestaña
 * GPS del cliente (y antes por el listado): acepta "lat, lng", separados por
 * espacio o paréntesis, e incluso una URL de Google Maps.
 */
class Coordenadas
{
    /**
     * Normaliza un texto a [lat, lng]. Solo considera números con decimales
     * (así ignora el zoom "17z" o el número de una dirección). Devuelve null
     * si no hay dos coordenadas válidas en rango.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function parse(string $raw): ?array
    {
        preg_match_all('/-?\d+\.\d+/', trim($raw), $m);
        $nums = $m[0] ?? [];
        if (count($nums) < 2) {
            return null;
        }

        $lat = (float) $nums[0];
        $lng = (float) $nums[1];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [round($lat, 7), round($lng, 7)];
    }
}
