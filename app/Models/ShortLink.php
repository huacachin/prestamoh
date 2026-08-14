<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Link corto propio: /s/{code} → destino. Ver la migración para el porqué.
 */
class ShortLink extends Model
{
    protected $fillable = ['code', 'destino', 'hash', 'hits'];

    /**
     * Acorta una URL PROPIA (mismo host que APP_URL). Idempotente: la misma
     * URL devuelve siempre el mismo código. Si la URL es de otro host se
     * devuelve tal cual — este acortador no redirige a destinos ajenos
     * (sería un open-redirect con nuestro dominio de por medio).
     */
    public static function para(string $url): string
    {
        $hostPropio = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (parse_url($url, PHP_URL_HOST) !== $hostPropio) {
            return $url;
        }

        $hash = hash('sha256', $url);

        $existente = self::where('hash', $hash)->first();
        if ($existente) {
            return url('/s/'.$existente->code);
        }

        // 6 chars alfanuméricos ≈ 57 mil millones de combinaciones; ante la
        // remota colisión se reintenta con uno nuevo.
        for ($i = 0; $i < 5; $i++) {
            $code = Str::random(6);
            try {
                self::create(['code' => $code, 'destino' => $url, 'hash' => $hash]);

                return url('/s/'.$code);
            } catch (UniqueConstraintViolationException $e) {
                // choque de code (o carrera del mismo hash): si el hash ya
                // quedó insertado por otra petición, reutilizarlo.
                $existente = self::where('hash', $hash)->first();
                if ($existente) {
                    return url('/s/'.$existente->code);
                }
            }
        }

        // Sin suerte tras los reintentos: mejor el link largo que ninguno.
        return $url;
    }
}
