<?php

namespace App\Support;

/**
 * Opciones de "T. Crédito" (columna clients.zona). Este valor NO es
 * decorativo: App\Support\Garantias lo lee para decidir la plantilla legal
 * (SIGM* / Cred. Vehicular → vehicular, Gar. Hip* → hipotecaria), así que
 * escribirlo a mano con un typo rompía la notificación. Lista pedida el
 * 28/08; los clientes migrados del legacy tienen otros valores históricos
 * ("Demandado Casa", "SIGM.S-Rojo 14/07", …) que se conservan al editar.
 *
 * Los estados "en ejecución" van con la garantía DELANTE
 * ("SIGM.S-Ejecución") y no como "Ejecución" a secas: Garantias clasifica
 * por PREFIJO, así que un valor sin garantía caería en "otra" y la
 * notificación legal de ese cliente pasaría al comunicado genérico —
 * perdería justo el requerimiento SIGM/hipotecario que su situación exige.
 * Mismo patrón que traen los valores del legacy ("SIGM.S-Ejecucion 14/01").
 */
class TiposCredito
{
    public const OPCIONES = [
        'SIGM.M',
        'SIGM.S',
        'Gar. Hip.M',
        'Gar. Hip.S',
        'Alq.Ven.D.',
        'Alquiler V.S', // pedido 02/09; Garantias lo clasifica "otra", igual que Alq.Ven.D.
        'Cred. Vehicular',
        // En ejecución (05/09). Llevan la garantía DELANTE a propósito: así
        // Garantias (que clasifica por prefijo) les sigue dando el
        // requerimiento SIGM / hipotecario, igual que los valores del legacy
        // ("SIGM.S-Ejecucion 14/01").
        'SIGM.M-Ejecución',
        'SIGM.S-Ejecución',
        'Gar. Hip.M-Ejecución',
        'Gar. Hip.S-Ejecución',
    ];

    /** Opciones del select para un valor ya guardado: agrega el histórico si no está en la lista. */
    public static function paraValor(?string $actual): array
    {
        $actual = trim((string) $actual);
        $opciones = self::OPCIONES;

        if ($actual !== '' && ! in_array($actual, $opciones, true)) {
            array_unshift($opciones, $actual);
        }

        return $opciones;
    }
}
