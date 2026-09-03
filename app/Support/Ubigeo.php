<?php

namespace App\Support;

/**
 * Ubigeo operativo de la financiera: departamentos LIMA y CALLAO completos
 * (Lima con sus 10 provincias y 171 distritos según INEI; Callao con la
 * Provincia Constitucional y sus 7 distritos).
 *
 * Los valores van en mayúscula/minúscula ("Cañete", "San Juan de
 * Lurigancho"): los contratos no se ven afectados porque DomicilioLegal
 * pasa todo a mayúsculas al armar la cláusula, y la frase registral ya
 * contempla el caso genérico ("PROVINCIA DE X, DEPARTAMENTO DE Y").
 *
 * Los lookups son case-insensitive porque el historial migrado guarda
 * "LIMA"/"CALLAO" en mayúsculas.
 */
class Ubigeo
{
    public const UBIGEO = [
        'Lima' => [
            'Lima' => [
                'Ancón', 'Ate', 'Barranco', 'Breña', 'Carabayllo', 'Cercado de Lima',
                'Chaclacayo', 'Chorrillos', 'Cieneguilla', 'Comas', 'El Agustino',
                'Independencia', 'Jesús María', 'La Molina', 'La Victoria', 'Lince',
                'Los Olivos', 'Lurigancho', 'Lurín', 'Magdalena del Mar', 'Miraflores',
                'Pachacámac', 'Pucusana', 'Pueblo Libre', 'Puente Piedra',
                'Punta Hermosa', 'Punta Negra', 'Rímac', 'San Bartolo', 'San Borja',
                'San Isidro', 'San Juan de Lurigancho', 'San Juan de Miraflores',
                'San Luis', 'San Martín de Porres', 'San Miguel', 'Santa Anita',
                'Santa María del Mar', 'Santa Rosa', 'Santiago de Surco', 'Surquillo',
                'Villa El Salvador', 'Villa María del Triunfo',
            ],
            'Barranca' => ['Barranca', 'Paramonga', 'Pativilca', 'Supe', 'Supe Puerto'],
            'Cajatambo' => ['Cajatambo', 'Copa', 'Gorgor', 'Huancapón', 'Manás'],
            'Canta' => ['Canta', 'Arahuay', 'Huamantanga', 'Huaros', 'Lachaqui', 'San Buenaventura', 'Santa Rosa de Quives'],
            'Cañete' => [
                'San Vicente de Cañete', 'Asia', 'Calango', 'Cerro Azul', 'Chilca',
                'Coayllo', 'Imperial', 'Lunahuaná', 'Mala', 'Nuevo Imperial',
                'Pacarán', 'Quilmaná', 'San Antonio', 'San Luis', 'Santa Cruz de Flores', 'Zúñiga',
            ],
            'Huaral' => [
                'Huaral', 'Atavillos Alto', 'Atavillos Bajo', 'Aucallama', 'Chancay',
                'Ihuarí', 'Lampián', 'Pacaraos', 'San Miguel de Acos',
                'Santa Cruz de Andamarca', 'Sumbilca', 'Veintisiete de Noviembre',
            ],
            'Huarochirí' => [
                'Matucana', 'Antioquia', 'Callahuanca', 'Carampoma', 'Chicla', 'Cuenca',
                'Huachupampa', 'Huanza', 'Huarochirí', 'Lahuaytambo', 'Langa', 'Laraos',
                'Mariatana', 'Ricardo Palma', 'San Andrés de Tupicocha', 'San Antonio',
                'San Bartolomé', 'San Damián', 'San Juan de Iris', 'San Juan de Tantaranche',
                'San Lorenzo de Quinti', 'San Mateo', 'San Mateo de Otao',
                'San Pedro de Casta', 'San Pedro de Huancayre', 'Sangallaya',
                'Santa Cruz de Cocachacra', 'Santa Eulalia', 'Santiago de Anchucaya',
                'Santiago de Tuna', 'Santo Domingo de los Olleros', 'Surco',
            ],
            'Huaura' => [
                'Huacho', 'Ámbar', 'Caleta de Carquín', 'Checras', 'Hualmay', 'Huaura',
                'Leoncio Prado', 'Paccho', 'Santa Leonor', 'Santa María', 'Sayán', 'Végueta',
            ],
            'Oyón' => ['Oyón', 'Andajes', 'Caujul', 'Cochamarca', 'Naván', 'Pachangara'],
            'Yauyos' => [
                'Yauyos', 'Alis', 'Ayauca', 'Ayaviri', 'Azángaro', 'Cacra', 'Carania',
                'Catahuasi', 'Chocos', 'Cochas', 'Colonia', 'Hongos', 'Huampará',
                'Huancaya', 'Huangáscar', 'Huantán', 'Huañec', 'Laraos', 'Lincha',
                'Madean', 'Miraflores', 'Omas', 'Putinza', 'Quinches', 'Quinocay',
                'San Joaquín', 'San Pedro de Pilas', 'Tanta', 'Tauripampa', 'Tomas',
                'Tupe', 'Viñac', 'Vitis',
            ],
        ],
        'Callao' => [
            'Callao' => [
                'Bellavista', 'Callao', 'Carmen de la Legua Reynoso', 'La Perla',
                'La Punta', 'Mi Perú', 'Ventanilla',
            ],
        ],
    ];

    /** @return string[] */
    public static function departamentos(): array
    {
        return array_keys(self::UBIGEO);
    }

    /** Clave real del catálogo para un valor en cualquier casing (o null). */
    public static function resolverDepartamento(?string $valor): ?string
    {
        return self::resolverEn(self::departamentos(), $valor);
    }

    /** @return string[] provincias del departamento (case-insensitive; desconocido → []) */
    public static function provinciasDe(?string $departamento): array
    {
        $dep = self::resolverDepartamento($departamento);

        return $dep !== null ? array_keys(self::UBIGEO[$dep]) : [];
    }

    public static function resolverProvincia(?string $departamento, ?string $valor): ?string
    {
        return self::resolverEn(self::provinciasDe($departamento), $valor);
    }

    /** @return string[] distritos de la provincia (case-insensitive; desconocido → []) */
    public static function distritosDe(?string $departamento, ?string $provincia): array
    {
        $dep = self::resolverDepartamento($departamento);
        $prov = self::resolverProvincia($departamento, $provincia);

        return ($dep !== null && $prov !== null) ? self::UBIGEO[$dep][$prov] : [];
    }

    /**
     * Ubica una provincia por nombre en TODO el catálogo (para el
     * autollenado de la API: "CAÑETE" → ['Lima', 'Cañete']).
     *
     * @return array{0: string, 1: string}|null [departamento, provincia]
     */
    public static function buscarProvincia(?string $valor): ?array
    {
        foreach (self::UBIGEO as $dep => $provincias) {
            $prov = self::resolverEn(array_keys($provincias), $valor);
            if ($prov !== null) {
                return [$dep, $prov];
            }
        }

        return null;
    }

    /** Opciones de select conservando un valor histórico fuera del catálogo. */
    public static function conHistorico(array $opciones, ?string $actual): array
    {
        $actual = trim((string) $actual);
        if ($actual !== '' && self::resolverEn($opciones, $actual) === null) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }

    private static function resolverEn(array $opciones, ?string $valor): ?string
    {
        $v = mb_strtoupper(trim((string) $valor));
        if ($v === '') {
            return null;
        }
        foreach ($opciones as $opcion) {
            if (mb_strtoupper($opcion) === $v) {
                return $opcion;
            }
        }

        return null;
    }
}
