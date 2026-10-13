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
        // Grados, minutos y segundos tal como Maps los muestra de nombre del lugar
        // (13/10, Antony: 12°01'22.6"S 76°58'06.4"W se guardaba como 22.6, 6.4).
        if (($dms = self::deGradosMinutosSegundos($texto)) !== null) {
            return $dms;
        }
        // Decimales pegados a mano: "-12.014431, -76.824936" (también con espacio o
        // paréntesis). Tienen que ir JUNTOS, separados solo por coma o espacio: ya no
        // se toman dos números cualesquiera del texto, que es lo que colaba los
        // segundos del DMS ("22.6 … 06.4").
        if (preg_match('/(-?\d{1,3}\.\d+)\s*[,;]?\s*(-?\d{1,3}\.\d+)/', $texto, $m)) {
            return self::validas($m[1], $m[2]);
        }

        return null;
    }

    /**
     * 12°01'22.6"S 76°58'06.4"W → [-12.0229444, -76.9684444]. Acepta ° u º, ' o ′,
     * " o ″ o '' (o nada), espacios, y N/S/E/W/O (Oeste) en cualquier orden.
     *
     * @return array{0: float, 1: float}|null
     */
    private static function deGradosMinutosSegundos(string $texto): ?array
    {
        $patron = '/(\d{1,3})\s*[°º]\s*(\d{1,2})\s*[\'′’]\s*(\d{1,2}(?:\.\d+)?)\s*(?:"|″|”|\'\')?\s*([NSEWO])\b/iu';
        if (! preg_match_all($patron, $texto, $m, PREG_SET_ORDER) || count($m) < 2) {
            return null;
        }
        $lat = $lng = null;
        foreach ($m as $p) {
            $valor = (int) $p[1] + (int) $p[2] / 60 + (float) $p[3] / 3600;
            $hemisferio = strtoupper($p[4]);
            if (in_array($hemisferio, ['S', 'W', 'O'], true)) {
                $valor = -$valor;
            }
            if (in_array($hemisferio, ['N', 'S'], true)) {
                $lat ??= $valor;
            } else {
                $lng ??= $valor;
            }
        }
        if ($lat === null || $lng === null) {
            return null;
        }

        return self::validas((string) $lat, (string) $lng);
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
