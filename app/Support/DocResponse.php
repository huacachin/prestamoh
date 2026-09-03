<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * Genera un Word EDITABLE al estilo de XlsResponse: el mismo HTML del
 * documento (la vista Blade que también alimenta el PDF) servido con el
 * header application/msword — Word lo abre, lo renderiza y permite editarlo.
 * El PDF (dompdf) sigue siendo la versión fiel de impresión (márgenes y
 * numeración exactos); el .doc es la copia editable del mismo contenido.
 */
class DocResponse
{
    /**
     * @param  string  $view  Vista Blade del documento (resources/views/documentos/...).
     * @param  array  $data  Datos para la vista.
     * @param  string  $filename  Nombre del archivo .doc.
     */
    public static function make(string $view, array $data, string $filename): Response
    {
        // BOM UTF-8 para que Word respete las tildes; el wrapper mso-* fija
        // vista de impresión y tamaño A4 al abrirlo.
        $html = "\xEF\xBB\xBF"
            .'<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">'
            .'<head><meta charset="utf-8">'
            .'<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->'
            .'<style>@page { size: A4; margin: 2.2cm 2cm 2.4cm 2.8cm; }</style>'
            .'</head><body>'
            .view($view, $data)->render()
            .'</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
