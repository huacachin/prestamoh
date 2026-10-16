<?php

namespace App\Support\Documentos;

use App\Services\Documentos\ContratoVm;

/**
 * Previa del contrato (16/10/2026, pedido de Antony): "colocar en rojo los
 * cambios que se han realizado". Lo que el wizard cambió respecto de los datos
 * del sistema —ficha, crédito, vehículos; GeneradorContrato anota las rutas en
 * el snapshot bajo 'cambios'— se envuelve en <span class="cambio"> para que
 * quien revisa la previa lo vea de un vistazo antes de emitir.
 *
 * Trabaja sobre el HTML ya renderizado y SOLO en el medio 'previa': las vistas
 * del contrato quedan intactas y el PDF y el Word nunca llevan el span. Igual
 * que Enfasis, parte el HTML por etiquetas y toca solo los nodos de texto;
 * busca el texto tal como lo imprime la vista (mayúsculas, escapado HTML,
 * estado civil ya flexionado por género, montos en cifra y en letras).
 */
final class ResaltarCambios
{
    public static function aplicar(string $html, ContratoVm $vm): string
    {
        $textos = self::textos($vm);
        if ($textos === []) {
            return $html;
        }

        // Del más largo al más corto: la alternancia resuelve de izquierda a
        // derecha y el largo debe ganar ("S/ 7,000.00 (SIETE MIL…)" antes que "S/ 7,000.00").
        usort($textos, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $alternativas = array_map(
            fn (string $t) => implode('\s+', array_map(fn ($p) => preg_quote($p, '/'), preg_split('/\s+/u', e($t)))),
            $textos
        );
        $patron = '/(?<![\p{L}\p{N}])(?:'.implode('|', $alternativas).')(?![\p{L}\p{N}])/u';

        $tramos = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($tramos as $i => $tramo) {
            if ($i % 2 === 1 || $tramo === '') {
                continue;
            }
            $tramos[$i] = preg_replace($patron, '<span class="cambio">$0</span>', $tramo);
        }

        return implode('', $tramos);
    }

    /**
     * Textos a pintar, tal como los imprime la vista.
     *
     * @return list<string>
     */
    private static function textos(ContratoVm $vm): array
    {
        $textos = [];
        foreach ($vm->cambios as $ruta) {
            $p = explode('.', (string) $ruta);
            $nuevos = match ($p[0]) {
                'montos' => [$vm->monto($p[1] ?? ''), $vm->montoCifra($p[1] ?? '')],
                'fecha' => [$vm->fechaLarga, $vm->fechaSimple],
                'clausulasAdicionales' => [mb_strtoupper((string) $vm->clausulasAdicionales)],
                'deudores' => self::deudor($vm, (int) ($p[1] ?? 0), $p[2] ?? ''),
                default => [],
            };
            foreach ($nuevos as $t) {
                $t = trim((string) $t);
                if ($t !== '' && $t !== '—') {
                    $textos[$t] = $t;
                }
            }
        }

        return array_values($textos);
    }

    /** @return list<string> */
    private static function deudor(ContratoVm $vm, int $i, string $campo): array
    {
        $d = $vm->deudores[$i] ?? null;
        // El sexo cambia las flexiones (EL/LA, IDENTIFICADO/A) por todo el
        // texto: no hay un tramo concreto que pintar.
        if ($d === null || $campo === '' || $campo === 'sexo') {
            return [];
        }
        $valor = trim((string) ($d[$campo] ?? ''));
        if ($valor === '') {
            return [];
        }

        // El estado civil se imprime flexionado (CASADO → CASADA para la deudora).
        return $campo === 'estadoCivil' && isset($d['g']) ? [$valor, $d['g']->flex($valor)] : [$valor];
    }
}
