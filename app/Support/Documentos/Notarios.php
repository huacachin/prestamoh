<?php

namespace App\Support\Documentos;

/**
 * Notarios del bien futuro (observación 3.4 del Área Legal, 29/09/2026):
 * lista cerrada en este orden, más "Otro" con texto libre. Antes el campo
 * era texto libre y cada asesor lo escribía distinto. El contrato lo imprime
 * en mayúsculas ("ANTE EL NOTARIO PÚBLICO …"), por eso el catálogo ya va así.
 *
 * El área mandó a Mónica Cecilia Salvatierra Saldaña dos veces; aquí una.
 */
final class Notarios
{
    public const OTRO = '__otro__';

    public const LISTA = [
        'PAÚL JHON HINOJOSA CARRILLO',
        'ROQUE ALBERTO DÍAZ DELGADO',
        'MARIO CÉSAR ROMERO VALDIVIESO',
        'MÓNICA CECILIA SALVATIERRA SALDAÑA',
        'LUCIO ALFREDO ZAMBRANO RODRÍGUEZ',
    ];

    public static function enCatalogo(?string $nombre): bool
    {
        return in_array(trim((string) $nombre), self::LISTA, true);
    }

    /**
     * Notario final de un slot del wizard: el del catálogo, o el tecleado
     * cuando se eligió "Otro". Null si no hay nada.
     *
     * @param  array{notario?: string|null, notario_otro?: string|null}  $slot
     */
    public static function deSlot(array $slot): ?string
    {
        $sel = trim((string) ($slot['notario'] ?? ''));
        $final = $sel === self::OTRO ? trim((string) ($slot['notario_otro'] ?? '')) : $sel;

        return $final !== '' ? $final : null;
    }
}
