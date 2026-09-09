<?php

namespace App\Support;

/**
 * Lista de cuotas en forma compacta: 19,20,21…48 → "19-48".
 *
 * Un cobro que toca 30 cuotas imprimía los 30 números separados por coma. En
 * la vista previa del recibo se salía del modal y en el papel (48 columnas)
 * ocupaba varias líneas de ruido. Se usa en los CUATRO sitios que muestran el
 * recibo —vista previa, ticket térmico, PDF y reimpresión— para que digan
 * exactamente lo mismo.
 */
class RangoCuotas
{
    /**
     * @param  array<int, int|string>  $nums
     */
    public static function texto(array $nums): string
    {
        // Cualquier valor no numérico (etiquetas del legacy): se deja tal cual.
        foreach ($nums as $n) {
            if (! is_numeric($n)) {
                return implode(',', $nums);
            }
        }

        $n = array_values(array_unique(array_map('intval', $nums)));
        sort($n);

        if ($n === []) {
            return '';
        }

        $tramos = [];
        $ini = $prev = $n[0];
        foreach (array_slice($n, 1) as $v) {
            if ($v === $prev + 1) {
                $prev = $v;

                continue;
            }
            $tramos[] = $ini === $prev ? (string) $ini : "{$ini}-{$prev}";
            $ini = $prev = $v;
        }
        $tramos[] = $ini === $prev ? (string) $ini : "{$ini}-{$prev}";

        return implode(',', $tramos);
    }
}
