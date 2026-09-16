<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * Genera un Word EDITABLE al estilo de XlsResponse: el mismo HTML del
 * documento (la vista Blade que también alimenta el PDF) servido con el
 * header application/msword — Word lo abre, lo renderiza y permite editarlo.
 * El PDF (dompdf) sigue siendo la versión fiel de impresión (márgenes y
 * numeración exactos); el .doc es la copia editable del mismo contenido.
 *
 * 16/09 — POR QUÉ SE INYECTA Y NO SE ENVUELVE. Las vistas de documentos
 * (anexo1, contrato, anexo2) emiten un documento HTML COMPLETO: su propio
 * <!DOCTYPE>, <html>, <head> con TODO el CSS, y <body>. Envolver eso en otro
 * <html><head>…</head><body> dejaba dos documentos anidados y el <style> del
 * documento caía DENTRO del <body> exterior. El importador de Word se queda
 * con el <head> de afuera y descarta el de adentro: se perdía la hoja de
 * estilos ENTERA y los tres documentos salían deformes. Ahora los bits de
 * Word (espacios de nombres y el bloque mso) se inyectan en el <head> que la
 * vista ya trae, y el documento llega con un solo <html>.
 *
 * Los MÁRGENES (@page) NO se fijan aquí: los emite estilos.blade.php, que es
 * quien sabe si el documento es el contrato (márgenes apretados de $compacto)
 * o un anexo. Fijarlos aquí le imponía al contrato los márgenes del anexo.
 */
class DocResponse
{
    /** Espacios de nombres que Word espera en la etiqueta <html>. */
    private const NS = ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        .' xmlns:w="urn:schemas-microsoft-com:office:word"';

    /** Abre el documento en vista de impresión y al 100%. */
    private const MSO = '<!--[if gte mso 9]><xml><w:WordDocument>'
        .'<w:View>Print</w:View><w:Zoom>100</w:Zoom>'
        .'</w:WordDocument></xml><![endif]-->';

    /**
     * @param  string  $view  Vista Blade del documento (resources/views/documentos/...).
     * @param  array  $data  Datos para la vista.
     * @param  string  $filename  Nombre del archivo .doc.
     */
    public static function make(string $view, array $data, string $filename): Response
    {
        return self::desdeHtml(view($view, $data)->render(), $filename);
    }

    /**
     * Igual que make() pero con el HTML ya renderizado: lo usa el contrato,
     * cuyo HTML pasa antes por Enfasis (negritas de términos definidos).
     */
    public static function desdeHtml(string $contenido, string $filename): Response
    {
        return new Response(self::documento($contenido), 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Documento listo para Word. El BOM UTF-8 es lo que hace que respete las
     * tildes; sin él Word asume la codificación del sistema y rompe los
     * acentos aunque el <meta charset> diga otra cosa.
     */
    private static function documento(string $html): string
    {
        // Caso normal: la vista ya emitió un documento completo.
        if (preg_match('/<head\b[^>]*>/i', $html)) {
            $html = preg_replace('/<html\b([^>]*)>/i', '<html$1'.self::NS.'>', $html, 1);
            $html = preg_replace('/(<head\b[^>]*>)/i', '$1'.self::MSO, $html, 1);
            $html = preg_replace('/(<body\b[^>]*>)(.*)(<\/body>)/is', '$1<div class="WordSection1">$2</div>$3', $html, 1);

            return "\xEF\xBB\xBF".$html;
        }

        // Fragmento suelto (sin <head> propio): aquí sí hay que armar el
        // documento. No lleva @page: un fragmento no trae estilos de página.
        return "\xEF\xBB\xBF"
            .'<html'.self::NS.'>'
            .'<head><meta charset="utf-8">'.self::MSO.'</head>'
            .'<body>'.$html.'</body></html>';
    }
}
