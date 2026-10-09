<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lectura de coordenadas pegadas por el usuario. Compartido por la pestaña
 * GPS del cliente (y antes por el listado): acepta "lat, lng", separados por
 * espacio o paréntesis, e incluso una URL de Google Maps.
 *
 * 10/10/2026 (Antony, ficha 173): el enlace que da "Compartir" en el celular
 * es corto (https://maps.app.goo.gl/…) y NO lleva las coordenadas en el
 * texto, así que se rechazaba como "formato inválido". Ahora, si es un
 * enlace de Google Maps sin coordenadas, se sigue la redirección hasta el
 * enlace largo y se leen de ahí: primero el pin exacto del lugar
 * (!3d<lat>!4d<lng>), luego el centro del mapa (@lat,lng) y, si no, los dos
 * primeros números con decimales.
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
        $raw = trim($raw);
        $coords = self::deTexto($raw);
        if ($coords === null && self::esEnlaceDeMaps($raw)) {
            $largo = self::resolverEnlace($raw);
            $coords = $largo !== null ? self::deTexto($largo) : null;
        }

        return $coords;
    }

    /** Enlace de Google Maps (corto o largo), sin que importe si trae coordenadas. */
    public static function esEnlaceDeMaps(string $texto): bool
    {
        return (bool) preg_match('~^https?://(maps\.app\.goo\.gl|goo\.gl|g\.co|(www\.)?google\.[a-z.]+/maps|maps\.google\.[a-z.]+)~i', trim($texto));
    }

    /** @return array{0: float, 1: float}|null */
    private static function deTexto(string $texto): ?array
    {
        // Pin exacto del lugar en los enlaces largos de Maps: …!3d-12.0463731!4d-77.042754…
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $texto, $m)) {
            return self::validas($m[1], $m[2]);
        }
        // Centro del mapa: …/@-12.0464,-77.0428,17z
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $texto, $m)) {
            return self::validas($m[1], $m[2]);
        }
        preg_match_all('/-?\d+\.\d+/', $texto, $m);
        $nums = $m[0] ?? [];
        if (count($nums) < 2) {
            return null;
        }

        return self::validas($nums[0], $nums[1]);
    }

    /** @return array{0: float, 1: float}|null */
    private static function validas(string $lat, string $lng): ?array
    {
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [round($lat, 7), round($lng, 7)];
    }

    /**
     * Sigue las redirecciones de un enlace corto de Maps (hasta 4) y devuelve
     * la última URL, o null si no se pudo (sin red, enlace muerto…). Nunca
     * tumba la pantalla: el que llama muestra el aviso de formato.
     */
    public static function resolverEnlace(string $url): ?string
    {
        try {
            $actual = $url;
            for ($i = 0; $i < 4; $i++) {
                $resp = Http::withoutRedirecting()->timeout(6)->withHeaders(['User-Agent' => 'Mozilla/5.0 (prestamoh)'])->get($actual);
                $destino = $resp->header('Location');
                if ($resp->status() >= 300 && $resp->status() < 400 && $destino) {
                    $actual = str_starts_with($destino, '/') ? preg_replace('~^(https?://[^/]+).*$~', '$1', $actual).$destino : $destino;
                    if (self::deTexto($actual) !== null) {
                        return $actual;
                    }

                    continue;
                }

                return $actual === $url ? null : $actual;
            }

            return $actual;
        } catch (Throwable $e) {
            Log::warning('Coordenadas: no se pudo resolver el enlace de Maps', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
