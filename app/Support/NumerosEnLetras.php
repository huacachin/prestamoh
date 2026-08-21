<?php

namespace App\Support;

/**
 * Números a letras en MAYÚSCULAS, estilo bancario peruano, para los
 * requerimientos legales de cobranza:
 *   monto(2500.00)  -> "DOS MIL QUINIENTOS CON 00/100"
 *   monto(1236.50)  -> "MIL DOSCIENTOS TREINTA Y SEIS CON 50/100"
 *   conteo(3)       -> "TRES (03)"
 */
class NumerosEnLetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
    ];

    private const DECENAS = [
        2 => 'VEINTI', 3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    public static function entero(int $n): string
    {
        if ($n < 0) {
            return 'MENOS '.self::entero(-$n);
        }
        if ($n === 0) {
            return 'CERO';
        }
        if ($n <= 20) {
            return self::UNIDADES[$n];
        }
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($d === 2) {
                return 'VEINTI'.self::UNIDADES[$u]; // VEINTIUNO..VEINTINUEVE
            }

            return self::DECENAS[$d].($u > 0 ? ' Y '.self::UNIDADES[$u] : '');
        }
        if ($n === 100) {
            return 'CIEN';
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;

            return self::CENTENAS[$c].($r > 0 ? ' '.self::entero($r) : '');
        }
        if ($n < 1000000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $miles = $m === 1 ? 'MIL' : self::entero($m).' MIL';

            return $miles.($r > 0 ? ' '.self::entero($r) : '');
        }

        $mm = intdiv($n, 1000000);
        $r = $n % 1000000;
        $millones = $mm === 1 ? 'UN MILLON' : self::entero($mm).' MILLONES';

        return $millones.($r > 0 ? ' '.self::entero($r) : '');
    }

    /** "DOS MIL QUINIENTOS CON 00/100" */
    public static function monto(float $monto): string
    {
        $monto = round($monto, 2);
        $enteros = (int) floor($monto);
        $centimos = (int) round(($monto - $enteros) * 100);

        return self::entero($enteros).' CON '.str_pad((string) $centimos, 2, '0', STR_PAD_LEFT).'/100';
    }

    /** "TRES (03)" */
    public static function conteo(int $n): string
    {
        return self::entero($n).' ('.str_pad((string) $n, 2, '0', STR_PAD_LEFT).')';
    }
}
