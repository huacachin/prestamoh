<?php

namespace App\Support\Legal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acceso cacheado a las constantes de negocio del Área Legal (tabla
 * legal_settings): acreedor, apoderada, representantes, cuentas, abogada.
 * El caché se invalida al guardar desde la pantalla legal/settings
 * (hook en el modelo LegalSetting).
 */
class LegalSettings
{
    private const CACHE_KEY = 'legal_settings';

    public static function get(string $clave, mixed $default = null): mixed
    {
        return self::todas()[$clave] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function todas(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return DB::table('legal_settings')
                ->pluck('valor', 'clave')
                ->map(fn ($v) => json_decode((string) $v, true))
                ->all();
        });
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
