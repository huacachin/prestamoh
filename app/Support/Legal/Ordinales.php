<?php

namespace App\Support\Legal;

use InvalidArgumentException;

/**
 * Numeración ordinal de cláusulas contractuales por LISTA DE CLAVES ACTIVAS
 * (no contador incremental): el contrato con GPS inserta la cláusula 'gps' en
 * la posición 9 y todas las posteriores se renumeran solas; y una cláusula
 * temprana puede citar una posterior ("conforme a la cláusula {{ $ord->de('x') }}"),
 * cosa que un contador no resuelve. Hoy esa renumeración (NOVENO→DÉCIMO
 * SÉPTIMO al quitar GPS) se hace a mano en Word.
 */
final class Ordinales
{
    /** @var array<string, int> clave => posición (1-based) */
    private array $posicion = [];

    /** @param list<string> $clavesActivas cláusulas del contrato, en su orden final */
    public function __construct(array $clavesActivas)
    {
        foreach (array_values($clavesActivas) as $i => $clave) {
            $this->posicion[$clave] = $i + 1;
        }
    }

    public function tiene(string $clave): bool
    {
        return isset($this->posicion[$clave]);
    }

    public function numero(string $clave): int
    {
        if (! $this->tiene($clave)) {
            throw new InvalidArgumentException("Cláusula '{$clave}' no está activa en este contrato.");
        }

        return $this->posicion[$clave];
    }

    /** 'gps' → 'NOVENO' (según la posición que ocupe en ESTE contrato) */
    public function de(string $clave): string
    {
        return self::ordinal($this->numero($clave));
    }

    /** @return list<string> claves en orden (para iterar los partials) */
    public function claves(): array
    {
        return array_keys($this->posicion);
    }

    /** 1 → 'PRIMERO' ... 17 → 'DÉCIMO SÉPTIMO' ... 39 → 'TRIGÉSIMO NOVENO' */
    public static function ordinal(int $n): string
    {
        $unidades = [
            1 => 'PRIMERO', 2 => 'SEGUNDO', 3 => 'TERCERO', 4 => 'CUARTO', 5 => 'QUINTO',
            6 => 'SEXTO', 7 => 'SÉPTIMO', 8 => 'OCTAVO', 9 => 'NOVENO',
        ];
        $decenas = [1 => 'DÉCIMO', 2 => 'VIGÉSIMO', 3 => 'TRIGÉSIMO'];

        if ($n < 1 || $n > 39) {
            throw new InvalidArgumentException("Ordinal fuera de rango: {$n}");
        }
        if ($n < 10) {
            return $unidades[$n];
        }

        $d = intdiv($n, 10);
        $u = $n % 10;

        return $decenas[$d].($u > 0 ? ' '.$unidades[$u] : '');
    }
}
