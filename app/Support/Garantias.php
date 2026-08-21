<?php

namespace App\Support;

/**
 * Tipo de garantía del cliente, derivado del prefijo de clients.zona — el
 * campo "T. Crédito" de la ficha (el legacy reutilizó "zona" para esto).
 * Regla de negocio (Antony, 21/08):
 *   SIGM / SIGM.S / SIGM.M          -> vehicular (garantía mobiliaria SIGM)
 *   Gar. Hip.S / Gar. Hip.M         -> hipotecaria
 *   Cred. Vehicular                 -> vehicular (por nombre propio)
 *   todo lo demás (Sin Garantia, Demandado*, Alq.Ven*, Empeño, vacío...) -> otra
 *
 * "Demandado *" queda en "otra" a propósito: ya está en manos legales y el
 * requerimiento automático final no le corresponde.
 */
class Garantias
{
    public const VEHICULAR = 'vehicular';

    public const HIPOTECARIA = 'hipotecaria';

    public const OTRA = 'otra';

    public static function de(?string $zona): string
    {
        $z = mb_strtoupper(trim((string) $zona));

        if (str_starts_with($z, 'SIGM') || str_starts_with($z, 'CRED. VEHICULAR')) {
            return self::VEHICULAR;
        }
        if (str_starts_with($z, 'GAR. HIP')) {
            return self::HIPOTECARIA;
        }

        return self::OTRA;
    }
}
