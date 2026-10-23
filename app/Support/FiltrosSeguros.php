<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Filtros de los listados que pintan tablas grandes (23/10/2026, pedido de
 * Antony tras los cuelgues del navegador en la oficina).
 *
 * - Las fechas se SANEAN: una fecha vacía, a medias ("2026-1") o imposible no
 *   es una fecha; nunca debe abrir la consulta a toda la historia.
 * - Un rango cruzado (inicio > fin) se intercambia.
 * - Cada listado tiene un TOPE de filas (config prestamos.tope_filas): si el
 *   filtro devuelve más, la pantalla no las pinta y avisa; el Excel sí trae
 *   todo (los exports marcan $sinTope en el componente).
 *
 * El reporte de pagos (App\Livewire\Reports\Payments::rangoSeguro) fue el
 * primero y conserva su propia versión con tope de 92 días.
 */
final class FiltrosSeguros
{
    /** 'Y-m-d' válida, o null si está vacía, a medias o es imposible. */
    public static function fecha(mixed $v): ?string
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    }

    /**
     * Rango SIEMPRE cerrado: una fecha inválida cae al otro extremo (o a hoy si
     * ninguna vale) y las cruzadas se intercambian.
     *
     * @return array{0: string, 1: string}
     */
    public static function rango(mixed $desde, mixed $hasta): array
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $fin = self::fecha($hasta) ?? self::fecha($desde) ?? $hoy;
        $inicio = self::fecha($desde) ?? $fin;
        if ($inicio > $fin) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        return [$inicio, $fin];
    }

    /**
     * Para los listados que heredan del legacy el modo "texto sin fechas = buscar
     * sin filtro de fecha": devuelve cada fecha válida o null (vacía o inválida),
     * intercambiando las cruzadas. Quién decide qué hacer con el null es el
     * listado; el tope de filas lo protege en cualquier caso.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function rangoLegacy(mixed $desde, mixed $hasta): array
    {
        $inicio = self::fecha($desde);
        $fin = self::fecha($hasta);
        if ($inicio !== null && $fin !== null && $inicio > $fin) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        return [$inicio, $fin];
    }

    /** Tope de filas en pantalla del listado indicado (config prestamos.tope_filas). */
    public static function tope(string $listado): int
    {
        return max(1, (int) config("prestamos.tope_filas.{$listado}", 1000));
    }

    /**
     * Aviso para la vista cuando el filtro supera el tope: null si cabe.
     *
     * @return array{total: int, tope: int}|null
     */
    public static function aviso(int $total, string $listado): ?array
    {
        $tope = self::tope($listado);

        return $total > $tope ? ['total' => $total, 'tope' => $tope] : null;
    }
}
