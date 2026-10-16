<?php

namespace App\Support;

/**
 * Lectura básica del user agent para la lista de sesiones activas de Mi perfil
 * (16/10/2026): navegador con versión mayor, sistema y tipo de dispositivo.
 * Sin dependencias: cubre Chrome, Edge, Firefox, Safari y Opera en Windows,
 * macOS, Linux, Android, iPhone y iPad, que es lo que entra al sistema.
 */
final class AgenteNavegador
{
    /** @return array{navegador: string, plataforma: string, dispositivo: string, texto: string} */
    public static function describir(?string $ua): array
    {
        $ua = (string) $ua;
        $plataforma = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Sistema desconocido',
        };

        [$navegador, $version] = match (true) {
            (bool) preg_match('~Edg[A-Za-z]*/(\d+)~', $ua, $m) => ['Edge', $m[1]],
            (bool) preg_match('~(?:OPR|Opera)/(\d+)~', $ua, $m) => ['Opera', $m[1]],
            (bool) preg_match('~(?:Chrome|CriOS)/(\d+)~', $ua, $m) => ['Chrome', $m[1]],
            (bool) preg_match('~(?:Firefox|FxiOS)/(\d+)~', $ua, $m) => ['Firefox', $m[1]],
            str_contains($ua, 'Safari/') && preg_match('~Version/(\d+)~', $ua, $m) => ['Safari', $m[1]],
            str_contains($ua, 'Safari/') => ['Safari', ''],
            $ua === '' => ['Navegador desconocido', ''],
            default => ['Otro navegador', ''],
        };

        $dispositivo = match (true) {
            $plataforma === 'iPad' || str_contains($ua, 'Tablet') => 'Tablet',
            $plataforma === 'iPhone' || $plataforma === 'Android' || str_contains($ua, 'Mobile') => 'Celular',
            default => 'Computadora',
        };

        $nombre = trim("{$navegador} {$version}");

        return [
            'navegador' => $nombre,
            'plataforma' => $plataforma,
            'dispositivo' => $dispositivo,
            'texto' => "{$nombre} · {$plataforma}",
        ];
    }
}
