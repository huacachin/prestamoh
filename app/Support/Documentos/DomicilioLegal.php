<?php

namespace App\Support\Documentos;

use App\Models\Client;

/**
 * Domicilio legal en el formato de las maestras SIGM del área legal.
 *
 * Reemplaza a las TRES copias byte-idénticas que había (GeneradorContrato::
 * domicilio(), Documentos::domicilioDe() y el armado interno de
 * GeneradorAnexo1): con tres copias, el caso Callao habría que agregarlo tres
 * veces y la próxima corrección se olvidaría en alguna.
 *
 * Reglas, tomadas de "Modulo Contratos. Indicaciones.docx" (el tramo verde
 * del código de colores, que el área marca como dropdown Lima|Callao):
 *   - Callao          → "PROVINCIA CONSTITUCIONAL DEL CALLAO" (giro registral)
 *   - Lima = Lima     → "PROVINCIA Y DEPARTAMENTO DE LIMA"
 *   - distintos       → "PROVINCIA DE X, DEPARTAMENTO DE Y"
 *
 * Todo en mayúsculas, porque el contrato entero lo está.
 */
final class DomicilioLegal
{
    /** Provincias con giro registral propio: la frase no es la genérica. */
    private const GIRO_PROPIO = [
        'CALLAO' => 'PROVINCIA CONSTITUCIONAL DEL CALLAO',
    ];

    /** Domicilio legal de la ficha del cliente. */
    public static function deCliente(Client $client): string
    {
        return self::armar(
            $client->direccion,
            $client->distrito,
            $client->provincia,
            $client->departamento,
        );
    }

    /**
     * Domicilio legal a partir de sus tramos sueltos (empresa, gerente,
     * tercero: viven en el wizard y no en una ficha).
     */
    public static function armar(?string $direccion, ?string $distrito, ?string $provincia, ?string $departamento): string
    {
        $tramos = [];

        if (filled($direccion)) {
            $tramos[] = mb_strtoupper(trim($direccion));
        }
        if (filled($distrito)) {
            $tramos[] = 'DISTRITO DE '.mb_strtoupper(trim($distrito));
        }

        $prov = filled($provincia) ? mb_strtoupper(trim($provincia)) : null;
        $depa = filled($departamento) ? mb_strtoupper(trim($departamento)) : null;

        if ($prov !== null && isset(self::GIRO_PROPIO[$prov])) {
            $tramos[] = self::GIRO_PROPIO[$prov];
        } elseif ($prov !== null && $depa !== null && $prov === $depa) {
            $tramos[] = 'PROVINCIA Y DEPARTAMENTO DE '.$prov;
        } else {
            if ($prov !== null) {
                $tramos[] = 'PROVINCIA DE '.$prov;
            }
            if ($depa !== null) {
                $tramos[] = 'DEPARTAMENTO DE '.$depa;
            }
        }

        return implode(', ', $tramos);
    }
}
