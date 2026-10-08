<?php

namespace App\Support\Auditoria;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Captura de pantalla que acompaña a una acción auditada (08/10/2026).
 *
 * El navegador manda la foto (data URL JPEG) con la petición de Livewire; el
 * trait ConCapturaDeAuditoria la deja aquí "pendiente" y GuardarActividad la
 * guarda en disco la PRIMERA vez que se escribe una fila de auditoría en esa
 * petición (si la acción falla antes de auditar, no queda archivo huérfano).
 * Todas las filas de la misma petición comparten la misma ruta.
 *
 * Va al disco privado (config auditoria.capturas.disco), nunca a /storage
 * público: la sirve AuditCapturaController solo al director.
 */
final class CapturaAuditoria
{
    private static ?string $pendiente = null;

    private static ?string $ruta = null;

    private static bool $intentada = false;

    public static function pendiente(?string $dataUrl): void
    {
        self::$pendiente = $dataUrl;
        self::$ruta = null;
        self::$intentada = false;
    }

    public static function hayPendiente(): bool
    {
        return self::$pendiente !== null;
    }

    /** Ruta de la captura guardada (la guarda en el primer uso); null si no hay o no es válida. */
    public static function ruta(): ?string
    {
        if (self::$intentada) {
            return self::$ruta;
        }
        self::$intentada = true;

        $jpeg = self::decodificar(self::$pendiente);
        if ($jpeg === null) {
            return null;
        }

        $ruta = 'auditoria/capturas/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
        try {
            if (! self::disco()->put($ruta, $jpeg)) {
                return null;
            }
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return self::$ruta = $ruta;
    }

    public static function limpiar(): void
    {
        self::$pendiente = null;
        self::$ruta = null;
        self::$intentada = false;
    }

    public static function disco(): Filesystem
    {
        return Storage::disk(config('auditoria.capturas.disco', 'local'));
    }

    /** Solo acepta un JPEG real, de hasta auditoria.capturas.max_bytes. */
    private static function decodificar(?string $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > (int) config('auditoria.capturas.max_bytes', 1_500_000)) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ($info['mime'] ?? '') !== 'image/jpeg') {
            return null;
        }

        return $bytes;
    }
}
