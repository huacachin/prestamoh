<?php

namespace App\Support;

use App\Models\VehiculoOpcion;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos de la ficha del vehículo: categoría, carrocería, color y
 * combustible (listas pedidas el 03/09). Select2 en modo tags: se puede
 * escribir otro valor y, desde el 13/10/2026 (Antony: "que se vayan guardando
 * para que salgan una próxima vez"), lo nuevo se guarda en vehiculo_opciones y
 * aparece en todos los formularios de vehículo. Las listas BASE son el
 * arranque; la tabla manda después.
 */
class VehiculoCatalogos
{
    public const TIPOS = ['categoria', 'carroceria', 'color', 'combustible'];

    /** @var array<string, list<string>> */
    public const BASE = [
        'categoria' => ['M1', 'M2', 'M2-C3', 'N1'],
        'carroceria' => [
            'SEDAN', 'SUV', 'MINIBUS', 'MICROBUS', 'MULTIPROPÓSITO',
            'ÓMNIBUS', 'FURGON', 'STATION WAGON',
            'PICK UP', // 26/09: faltaba (así la nombra la tarjeta de propiedad)
        ],
        'color' => ['Rojo', 'Gris', 'Blanco', 'Negro', 'Plata'],
        'combustible' => ['DIESEL', 'GASOLINA', 'BI-COMBUSTIBLE GLP', 'BI-COMBUSTIBLE GNV', 'GLP', 'GNV'],
    ];

    /**
     * Opciones de un catálogo: las BASE en su orden y después las guardadas,
     * por orden alfabético, sin repetir (sin distinguir mayúsculas).
     *
     * @return list<string>
     */
    public static function opciones(string $tipo): array
    {
        self::validarTipo($tipo);
        $guardadas = Schema::hasTable('vehiculo_opciones')
            ? VehiculoOpcion::where('tipo', $tipo)->orderBy('valor')->pluck('valor')->all()
            : [];

        return self::sinRepetidos(array_merge(self::BASE[$tipo], $guardadas));
    }

    /** Opciones del select conservando un valor guardado fuera del catálogo (API de placa, histórico). */
    public static function paraValor(string $tipo, ?string $actual): array
    {
        $opciones = self::opciones($tipo);
        $actual = trim((string) $actual);
        if ($actual !== '' && ! in_array(mb_strtoupper($actual), array_map('mb_strtoupper', $opciones), true)) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }

    /**
     * Guarda un valor nuevo en el catálogo para que salga la próxima vez. Lo
     * que ya existe (aunque en otra caja) o viene vacío no se toca.
     */
    public static function recordar(string $tipo, ?string $valor): void
    {
        self::validarTipo($tipo);
        $valor = mb_substr(trim((string) $valor), 0, 60);
        if ($valor === '') {
            return;
        }
        $existe = in_array(mb_strtoupper($valor), array_map('mb_strtoupper', self::opciones($tipo)), true);
        if (! $existe) {
            VehiculoOpcion::firstOrCreate(['tipo' => $tipo, 'valor' => $valor]);
        }
    }

    /** Los cuatro catálogos de una vez, a partir de los campos de un vehículo. */
    public static function recordarDe(array $vehiculo): void
    {
        foreach (self::TIPOS as $tipo) {
            self::recordar($tipo, $vehiculo[$tipo] ?? null);
        }
    }

    /** @return list<string> */
    private static function sinRepetidos(array $valores): array
    {
        $vistos = [];
        $unicos = [];
        foreach ($valores as $v) {
            $v = trim((string) $v);
            $clave = mb_strtoupper($v);
            if ($v === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $unicos[] = $v;
        }

        return $unicos;
    }

    private static function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Catálogo de vehículo desconocido: {$tipo}");
        }
    }
}
