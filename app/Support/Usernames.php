<?php

namespace App\Support;

use App\Models\User;

/**
 * Resuelve el username a partir del nombre completo. Varias tablas históricas
 * (mass_deletions.user/advisor/performed_by, payments.usuario, ...) guardan el
 * NOMBRE de la persona; las vistas muestran el username. La comparación va con
 * TRIM en ambos lados (hay nombres con espacio final) y si nada calza —datos
 * del legacy con otro formato— se devuelve el valor original tal cual.
 */
class Usernames
{
    /** @var array<string, string>|null nombre (trim) → username */
    private static ?array $mapa = null;

    public static function de(?string $nombre): ?string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }

        self::$mapa ??= User::query()->pluck('username', 'name')
            ->mapWithKeys(fn ($u, $n) => [trim((string) $n) => (string) $u])->all();

        return self::$mapa[$nombre] ?? $nombre;
    }
}
