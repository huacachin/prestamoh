<?php

namespace App\Services\Chat;

/**
 * Costo en USD de una respuesta del chat (23/10/2026) según config('chat.precios').
 * La entrada que vino de la caché del prompt se cobra al 10 % y la que la
 * creó al 125 %; un modelo que no esté en la tabla se cuenta como Sonnet.
 */
final class Costos
{
    public static function usd(string $modelo, int $entrada, int $salida, int $cacheLectura = 0, int $cacheEscritura = 0): float
    {
        $precios = (array) config('chat.precios', []);
        [$porEntrada, $porSalida] = $precios[$modelo] ?? $precios['claude-sonnet-5-5'] ?? [2.00, 10.00];

        $usd = ($entrada * $porEntrada
            + $cacheLectura * $porEntrada * 0.10
            + $cacheEscritura * $porEntrada * 1.25
            + $salida * $porSalida) / 1_000_000;

        return round($usd, 6);
    }
}
