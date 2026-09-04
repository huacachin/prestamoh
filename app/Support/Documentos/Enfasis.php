<?php

namespace App\Support\Documentos;

/**
 * Negritas del contrato (05/09). Las maestras del área legal resaltan los
 * TÉRMINOS DEFINIDOS (EL DEUDOR, EL ACREEDOR, EL CONSTITUYENTE) y las
 * etiquetas que abren párrafo ("VALOR DEL BIEN AFECTADO:", "INSERTO:"…)
 * en cada una de sus apariciones — verificado extrayendo los runs en
 * negrita del .docx a.1: 119 de 136 son de este puñado de expresiones.
 *
 * Se aplica sobre el HTML ya renderizado, y no término por término en las
 * 20 vistas, para que exista UNA lista auditable: agregar un término aquí
 * lo resalta en todo el contrato, en los tres medios.
 *
 * Solo toca NODOS DE TEXTO: el HTML se parte por etiquetas y los tramos
 * `<...>` se dejan intactos, así ningún atributo se corrompe.
 */
final class Enfasis
{
    /**
     * Etiquetas que abren párrafo. Van como PATRONES porque el sistema las
     * flexiona en tiempo de render ("DATOS DEL CONSTITUYENTE Y EL DEUDOR:",
     * "DATOS DE LOS CONSTITUYENTES Y LAS DEUDORAS:"…): enumerar las
     * combinaciones una por una se rompía a la primera flexión nueva.
     */
    private const PATRONES = [
        'DATOS (?:DEL|DE L[AO]S?) CONSTITUYENTES? Y (?:EL|LA|LOS|LAS) DEUDOR(?:ES|AS|A)?:',
        'DATOS DEL ACREEDOR A CUYO FAVOR SE CONSTITUYE LA GARANTÍA MOBILIARIA:',
        'MONTO DE LA OBLIGACIÓN PRINCIPAL:',
        'MONTO MÁXIMO DE LA GARANTÍA:',
        'VALOR DEL BIEN AFECTADO:',
        'INSERTO:',
    ];

    /**
     * Términos definidos, con todas las flexiones que produce Genero.
     * Ordenados de más largo a más corto: preg_replace resuelve la
     * alternancia de izquierda a derecha y el largo debe ganar.
     */
    private const TERMINOS = [
        'LOS CONSTITUYENTES',
        'LAS CONSTITUYENTES',
        'EL CONSTITUYENTE',
        'LA CONSTITUYENTE',
        'LOS DEUDORES',
        'LAS DEUDORAS',
        'EL ACREEDOR',
        'LA DEUDORA',
        'EL DEUDOR',
    ];

    public static function aplicar(string $html): string
    {
        // Patrones primero (son los más largos y contienen términos dentro).
        $alternativas = array_merge(
            self::PATRONES,
            array_map(fn ($t) => preg_quote($t, '/'), self::TERMINOS),
        );
        $patron = '/'.implode('|', $alternativas).'/u';

        // Partir por etiquetas conservando los delimitadores: los índices
        // impares son `<...>` y no se tocan.
        $tramos = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($tramos as $i => $tramo) {
            if ($i % 2 === 1 || $tramo === '') {
                continue;
            }
            $tramos[$i] = preg_replace($patron, '<b>$0</b>', $tramo);
        }

        return implode('', $tramos);
    }
}
