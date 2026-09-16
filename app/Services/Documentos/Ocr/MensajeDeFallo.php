<?php

namespace App\Services\Documentos\Ocr;

use Throwable;

/**
 * Traduce el fallo de la lectura a algo que el operador entienda Y pueda
 * resolver. Sin esto le llegaría el error en inglés de la API, que no le dice
 * si el problema es su foto, el saldo o la red — y eso decide si reintenta,
 * transcribe a mano o avisa a alguien.
 *
 * Se decide por el TEXTO del error antes que por la clase de excepción: el
 * saldo agotado llega como un 400 genérico, igual que un parámetro mal puesto,
 * así que la clase sola no alcanza para distinguirlos.
 */
class MensajeDeFallo
{
    public static function para(Throwable $e): string
    {
        $texto = mb_strtolower($e->getMessage());
        $clase = $e::class;

        return match (true) {
            // Lo más probable en el día a día, y no es culpa del operador ni
            // de la foto: que sepa a quién avisar.
            str_contains($texto, 'credit balance'),
            str_contains($texto, 'insufficient'),
            str_contains($texto, 'billing') => 'La lectura automática se quedó sin saldo: avisa a administración para que recargue.',

            str_contains($texto, 'x-api-key'),
            str_contains($texto, 'authentication'),
            str_contains($texto, 'invalid api key'),
            str_contains($clase, 'AuthenticationException') => 'La lectura automática no está bien configurada (clave rechazada): avisa a sistemas.',

            str_contains($texto, 'rate limit'),
            str_contains($texto, 'overloaded'),
            str_contains($clase, 'RateLimitException') => 'La lectura automática está saturada ahora mismo: espera un momento y vuelve a intentar.',

            str_contains($texto, 'timed out'),
            str_contains($texto, 'timeout'),
            str_contains($texto, 'could not resolve'),
            str_contains($clase, 'APITimeoutException'),
            str_contains($clase, 'APIConnectionException') => 'No hubo conexión con el servicio de lectura.',

            // Imagen que la API no aceptó (tamaño, formato, corrupta).
            str_contains($texto, 'image') => 'El servicio no pudo procesar esta imagen: prueba con una foto más nítida o más liviana.',

            default => 'No se pudo leer el voucher.',
        };
    }
}
