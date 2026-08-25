<?php

namespace App\Services\Legal;

use Exception;

/** Errores bloqueantes de la validación previa a generar un contrato. */
class ValidacionContratoException extends Exception
{
    /** @param list<string> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('El contrato no puede generarse: '.implode(' | ', $errores));
    }
}
