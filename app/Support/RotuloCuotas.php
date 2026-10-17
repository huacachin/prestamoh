<?php

namespace App\Support;

/**
 * Rótulo corto de un conjunto de cuotas para la pantalla de cobro (17/10/2026):
 * "cuota 1", "cuotas 1 y 2", "cuotas 1 a 4" (consecutivas) o "cuotas 1, 2 y 5".
 */
final class RotuloCuotas
{
    /** @param  iterable<int|string>  $numeros */
    public static function de(iterable $numeros): string
    {
        $nums = [];
        foreach ($numeros as $n) {
            if (is_numeric($n)) {
                $nums[(int) $n] = (int) $n;
            }
        }
        sort($nums);

        if ($nums === []) {
            return '';
        }
        if (count($nums) === 1) {
            return "cuota {$nums[0]}";
        }

        $primera = $nums[0];
        $ultima = $nums[count($nums) - 1];
        if (count($nums) > 2 && $nums === range($primera, $ultima)) {
            return "cuotas {$primera} a {$ultima}";
        }

        array_pop($nums);

        return 'cuotas '.implode(', ', $nums).' y '.$ultima;
    }
}
