<?php

namespace App\Support;

use App\Livewire\Clients\Create;

/**
 * Correo en dos partes, como en Nuevo cliente (16/10): el usuario escribe lo
 * que va antes de la @ y elige el dominio (Gmail, Hotmail u otro escrito a
 * mano). Para los formularios de copropietario (Trello, tarjeta 4, 05/11),
 * que guardan las partes en su arreglo como email_usuario, email_dominio y
 * email_dominio_otro, junto al email entero que va a la ficha.
 */
final class CorreoEnPartes
{
    /** @return array{email_usuario: string, email_dominio: string, email_dominio_otro: string} */
    public static function partir(?string $correo): array
    {
        [$usuario, $dominio] = array_pad(explode('@', trim((string) $correo), 2), 2, '');
        $dominio = mb_strtolower($dominio);
        $conocido = $dominio !== '' && $dominio !== 'otro' && array_key_exists($dominio, Create::EMAIL_DOMINIOS);

        return [
            'email_usuario' => $usuario,
            'email_dominio' => $conocido ? $dominio : ($dominio === '' ? '' : 'otro'),
            'email_dominio_otro' => $conocido ? '' : $dominio,
        ];
    }

    /**
     * Arma el email entero (en minúsculas, vacío si falta una parte). Si solo
     * llegó el email entero, primero lo reparte en sus partes.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function completar(array $datos): array
    {
        $sinPartes = trim((string) ($datos['email_usuario'] ?? '')) === '' && ($datos['email_dominio'] ?? '') === '';
        if ($sinPartes && str_contains((string) ($datos['email'] ?? ''), '@')) {
            $datos = array_merge($datos, self::partir($datos['email']));
        }

        $usuario = trim((string) ($datos['email_usuario'] ?? ''));
        $dominio = match ($datos['email_dominio'] ?? '') {
            'otro' => mb_strtolower(trim((string) ($datos['email_dominio_otro'] ?? ''))),
            '' => '',
            default => (string) $datos['email_dominio'],
        };
        $datos['email'] = ($usuario !== '' && $dominio !== '') ? mb_strtolower($usuario.'@'.$dominio) : '';

        return $datos;
    }

    /**
     * Las reglas de Nuevo cliente para las partes, dentro del arreglo $prefijo.
     *
     * @return array<string, mixed>
     */
    public static function reglas(string $prefijo): array
    {
        return [
            "{$prefijo}.email_usuario" => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._%+\-]+$/'],
            "{$prefijo}.email_dominio" => 'required|in:'.implode(',', array_keys(Create::EMAIL_DOMINIOS)),
            "{$prefijo}.email_dominio_otro" => ["exclude_unless:{$prefijo}.email_dominio,otro", 'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/'],
        ];
    }

    /** @return array<string, string> nombres de las partes para los mensajes */
    public static function atributos(string $prefijo): array
    {
        return [
            "{$prefijo}.email_usuario" => 'usuario del correo',
            "{$prefijo}.email_dominio" => 'dominio del correo',
            "{$prefijo}.email_dominio_otro" => 'dominio del correo',
        ];
    }
}
