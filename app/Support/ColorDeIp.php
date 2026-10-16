<?php

namespace App\Support;

/**
 * Colores para distinguir IPs a primera vista (16/10/2026, Monitoreo): a cada
 * IP distinta de la página le toca un tono diferente, repartidos con el
 * ángulo áureo para que queden lo más separados posible. Se asignan en el
 * orden en que llegan (el que llame debe pasar las IPs por primera aparición,
 * la más antigua primero): así, cuando aparece una IP nueva, las de antes
 * conservan su color.
 */
final class ColorDeIp
{
    private const ANGULO_AUREO = 137.508;

    private const TONO_INICIAL = 210.0;

    /**
     * @param  iterable<int, string|null>  $ips
     * @return array<string, array{bg: string, borde: string, texto: string}>
     */
    public static function paleta(iterable $ips): array
    {
        $paleta = [];
        $i = 0;
        foreach ($ips as $ip) {
            $ip = trim((string) $ip);
            if ($ip === '' || isset($paleta[$ip])) {
                continue;
            }
            $tono = fmod(self::TONO_INICIAL + $i * self::ANGULO_AUREO, 360);
            // A partir de la segunda vuelta se oscurece un poco para seguir distinguiendo.
            $vuelta = intdiv($i, 8);
            $paleta[$ip] = [
                'bg' => sprintf('hsl(%d, 85%%, %d%%)', $tono, max(78, 92 - $vuelta * 6)),
                'borde' => sprintf('hsl(%d, 60%%, %d%%)', $tono, max(50, 70 - $vuelta * 8)),
                'texto' => sprintf('hsl(%d, 70%%, %d%%)', $tono, max(18, 28 - $vuelta * 4)),
            ];
            $i++;
        }

        return $paleta;
    }

    /** Variables CSS para el chip de una IP (gris si la IP no tiene color, p. ej. filas viejas sin IP). */
    public static function estilo(?array $color): string
    {
        $c = $color ?? ['bg' => '#f1f3f6', 'borde' => '#d5d9df', 'texto' => '#6b7280'];

        return "--ip-bg:{$c['bg']};--ip-bd:{$c['borde']};--ip-tx:{$c['texto']}";
    }
}
