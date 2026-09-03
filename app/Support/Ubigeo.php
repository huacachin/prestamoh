<?php

namespace App\Support;

/**
 * Ubigeo operativo de la financiera: LIMA y CALLAO (los dos únicos
 * departamentos donde opera — mismo par que Create::PROVINCIAS, que
 * gobierna la frase registral de los contratos).
 *
 * El distrito NO se restringe al catálogo: el desplegable es un datalist
 * (sugerencias con búsqueda al escribir) y sigue aceptando texto libre,
 * porque hay historial migrado y casos borde que no deben bloquearse.
 */
class Ubigeo
{
    public const DEPARTAMENTOS = ['LIMA', 'CALLAO'];

    /** Distritos por departamento: provincia de Lima (43) y Callao (7). */
    public const DISTRITOS = [
        'LIMA' => [
            'ANCÓN', 'ATE', 'BARRANCO', 'BREÑA', 'CARABAYLLO', 'CERCADO DE LIMA',
            'CHACLACAYO', 'CHORRILLOS', 'CIENEGUILLA', 'COMAS', 'EL AGUSTINO',
            'INDEPENDENCIA', 'JESÚS MARÍA', 'LA MOLINA', 'LA VICTORIA', 'LINCE',
            'LOS OLIVOS', 'LURIGANCHO', 'LURÍN', 'MAGDALENA DEL MAR', 'MIRAFLORES',
            'PACHACÁMAC', 'PUCUSANA', 'PUEBLO LIBRE', 'PUENTE PIEDRA',
            'PUNTA HERMOSA', 'PUNTA NEGRA', 'RÍMAC', 'SAN BARTOLO', 'SAN BORJA',
            'SAN ISIDRO', 'SAN JUAN DE LURIGANCHO', 'SAN JUAN DE MIRAFLORES',
            'SAN LUIS', 'SAN MARTÍN DE PORRES', 'SAN MIGUEL', 'SANTA ANITA',
            'SANTA MARÍA DEL MAR', 'SANTA ROSA', 'SANTIAGO DE SURCO', 'SURQUILLO',
            'VILLA EL SALVADOR', 'VILLA MARÍA DEL TRIUNFO',
        ],
        'CALLAO' => [
            'BELLAVISTA', 'CALLAO', 'CARMEN DE LA LEGUA REYNOSO', 'LA PERLA',
            'LA PUNTA', 'MI PERÚ', 'VENTANILLA',
        ],
    ];

    /** Opciones del select de departamento: agrega el histórico si no está en la lista. */
    public static function departamentosPara(?string $actual): array
    {
        $actual = mb_strtoupper(trim((string) $actual));
        $opciones = self::DEPARTAMENTOS;

        if ($actual !== '' && ! in_array($actual, $opciones, true)) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }

    /** Sugerencias de distrito para el departamento dado (desconocido → todas). */
    public static function distritosDe(?string $departamento): array
    {
        $dep = mb_strtoupper(trim((string) $departamento));

        return self::DISTRITOS[$dep] ?? array_merge(...array_values(self::DISTRITOS));
    }
}
