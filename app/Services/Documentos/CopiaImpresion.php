<?php

namespace App\Services\Documentos;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copia de IMPRESIÓN de un PDF emitido: el mismo documento rasterizado con
 * Ghostscript (una imagen por hoja, sin fuentes), que la fotocopiadora de la
 * oficina imprime de corrido.
 *
 * Por qué existe (22/09/2026): los PDF de dompdf llevan todo el texto en
 * fuentes Type0/CID y el driver de la fotocopiadora se demora 15-20 s POR
 * HOJA procesándolas; ni el font subsetting ni pasarlas a TrueType simple lo
 * arreglaron, y las hojas como imagen (300 ppp, sin pérdida) salen rápido.
 *
 * El PDF emitido NO se toca: sigue siendo el registro legal (hash, snapshot,
 * texto seleccionable). La copia se genera a pedido la primera vez que alguien
 * pulsa "Imprimir" y queda guardada al lado del original con el sufijo
 * `-impresion-g300` / `-impresion-c300` (gris o color, y resolución: si la
 * config cambia, se regenera sola), así sirve también para lo emitido antes.
 *
 * Gris o color por tipo (config documentos.impresion.color): la fotocopiadora
 * cobra el color; el contrato es texto negro, los anexos llevan cabeceras de
 * color y la foto del voucher.
 */
final class CopiaImpresion
{
    public const DISCO = 'public';

    /** Un temporal más viejo que esto quedó huérfano (un worker muerto a mitad de gs); se barre. */
    private const TEMPORAL_HUERFANO_SEG = 600;

    /** Ruta en el disco public de la copia de impresión; la genera si no existe o quedó vieja. */
    public static function ruta(string $pdfPath, bool $color): string
    {
        $disco = Storage::disk(self::DISCO);
        $destino = self::rutaCache($pdfPath, $color);

        if (! self::vigente($disco->path($pdfPath), $disco->path($destino))) {
            self::rasterizar($disco->path($pdfPath), $disco->path($destino), $color);
        }

        return $destino;
    }

    /** `documentos/cliente-1/contrato-credito-2-v1.pdf` → `…-v1-impresion-g300.pdf` (gris, 300 ppp). */
    public static function rutaCache(string $pdfPath, bool $color): string
    {
        $sufijo = (string) config('documentos.impresion.sufijo', '-impresion');
        $marca = ($color ? 'c' : 'g').self::dpi();

        return preg_replace('/\.pdf$/i', '', $pdfPath)."{$sufijo}-{$marca}.pdf";
    }

    /** Color según el tipo de documento; lo que no esté en la tabla sale a color (no pierde nada). */
    public static function colorPara(string $tipo): bool
    {
        return (bool) (config('documentos.impresion.color')[$tipo] ?? true);
    }

    private static function dpi(): int
    {
        return max(72, (int) config('documentos.impresion.dpi', 300));
    }

    private static function vigente(string $origen, string $destino): bool
    {
        return is_file($destino)
            && filesize($destino) > 0
            && is_file($origen)
            && filemtime($destino) >= filemtime($origen);
    }

    private static function rasterizar(string $origen, string $destino, bool $color): void
    {
        if (! is_file($origen)) {
            throw new CopiaImpresionException("No existe el PDF de origen: {$origen}");
        }

        self::barrerTemporalesHuerfanos($destino);

        $cfg = (array) config('documentos.impresion', []);
        $binario = (string) ($cfg['ghostscript'] ?? 'gs');
        // Se escribe a un temporal en la misma carpeta y se renombra al final:
        // nadie puede recibir una copia a medio escribir.
        $tmp = $destino.'.tmp-'.bin2hex(random_bytes(4));

        $comando = [
            $binario,
            '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE='.($color ? 'pdfimage24' : 'pdfimage8'), // RGB o gris, sin pérdida (Flate)
            '-r'.self::dpi(),
            '-sOutputFile='.$tmp,
            $origen,
        ];

        try {
            $resultado = Process::timeout((int) ($cfg['timeout'] ?? 90))->run($comando);
        } catch (Throwable $e) { // no se pudo lanzar, o se pasó del tiempo
            @unlink($tmp);
            throw new CopiaImpresionException('Ghostscript no pudo ejecutarse: '.$e->getMessage(), 0, $e);
        }

        if (! $resultado->successful()) {
            @unlink($tmp);
            $detalle = $resultado->exitCode() === 127
                ? "no se encontró el binario «{$binario}» (¿está instalado ghostscript?)"
                : 'código '.$resultado->exitCode().': '.trim($resultado->errorOutput().' '.$resultado->output());
            throw new CopiaImpresionException("Ghostscript falló ({$detalle})");
        }

        // Ghostscript devuelve 0 aunque no haya podido dibujar alguna hoja (disco
        // lleno, PDF raro): solo lo dice en los mensajes. Una copia con hojas de
        // menos NO puede quedar guardada como buena.
        if ($motivo = self::defecto($origen, $tmp, $resultado->output().$resultado->errorOutput())) {
            @unlink($tmp);
            throw new CopiaImpresionException("La copia de impresión salió defectuosa ({$motivo})");
        }

        if (! @rename($tmp, $destino)) {
            @unlink($tmp);
            throw new CopiaImpresionException("No se pudo guardar la copia de impresión en {$destino}");
        }
    }

    /** Motivo por el que la copia recién producida no sirve, o null si está bien. */
    private static function defecto(string $origen, string $tmp, string $mensajes): ?string
    {
        if (! is_file($tmp) || filesize($tmp) < 16) {
            return 'no se produjo el archivo';
        }
        if (preg_match('/\*\*\*\* Error|page will be missing|^ERROR:/mi', $mensajes)) {
            return 'Ghostscript avisó de hojas sin dibujar: '.trim(preg_replace('/\s+/', ' ', substr($mensajes, 0, 300)));
        }
        $tam = filesize($tmp);
        if ((string) file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
            return 'no es un PDF';
        }
        if (! str_contains((string) file_get_contents($tmp, false, null, max(0, $tam - 1024)), '%%EOF')) {
            return 'el PDF quedó truncado';
        }
        $esperadas = self::paginas($origen);
        $obtenidas = self::paginas($tmp);
        if ($esperadas !== null && $obtenidas !== null && $esperadas !== $obtenidas) {
            return "tiene {$obtenidas} hojas y el original {$esperadas}";
        }

        return null;
    }

    /** Hojas de un PDF contando sus objetos /Page (null si no se puede saber). */
    private static function paginas(string $archivo): ?int
    {
        $n = preg_match_all('#/Type\s*/Page\b#', (string) file_get_contents($archivo));

        return $n > 0 ? $n : null;
    }

    private static function barrerTemporalesHuerfanos(string $destino): void
    {
        foreach (glob($destino.'.tmp-*') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - self::TEMPORAL_HUERFANO_SEG) {
                @unlink($viejo);
            }
        }
    }
}
