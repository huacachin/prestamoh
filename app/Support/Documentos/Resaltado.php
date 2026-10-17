<?php

namespace App\Support\Documentos;

use Closure;
use Illuminate\Support\HtmlString;

/**
 * Datos VARIABLES de los documentos del cliente en la vista previa (pedido de
 * Antony, 16-17/10/2026): lo que viene de la ficha, el crédito, los vehículos
 * y el wizard sale en rojo fuerte y negrita SOLO en el medio 'previa'; en el
 * PDF y el Word sale el texto escapado tal cual, byte a byte igual que antes.
 *
 * El contrato lo usa a través de ContratoVm::v(); los anexos reciben el
 * marcador como `$dato` en los datos de la vista (RenderDocumento). Las
 * constantes (acreedor, apoderada, pie de página…) no pasan por aquí.
 */
final class Resaltado
{
    public static function dato(mixed $texto, bool $resaltar): HtmlString
    {
        $t = e((string) $texto);

        return new HtmlString($resaltar ? '<span class="variable">'.$t.'</span>' : $t);
    }

    /** Closure `$dato($texto)` para las vistas que renderizan desde el snapshot ($d). */
    public static function marcador(bool $resaltar): Closure
    {
        return fn (mixed $texto): HtmlString => self::dato($texto, $resaltar);
    }
}
