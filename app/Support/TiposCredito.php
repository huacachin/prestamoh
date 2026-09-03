<?php

namespace App\Support;

/**
 * Opciones de "T. Crédito" (columna clients.zona). Este valor NO es
 * decorativo: App\Support\Garantias lo lee para decidir la plantilla legal
 * (SIGM* / Cred. Vehicular → vehicular, Gar. Hip* → hipotecaria), así que
 * escribirlo a mano con un typo rompía la notificación. Lista pedida el
 * 28/08; los clientes migrados del legacy tienen otros valores históricos
 * ("Demandado Casa", "SIGM.S-Rojo 14/07", …) que se conservan al editar.
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
