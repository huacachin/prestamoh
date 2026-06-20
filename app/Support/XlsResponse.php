<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * Genera un Excel al estilo LEGACY: una tabla HTML servida con el header
 * application/vnd.ms-excel (Excel la abre y la renderiza tal cual). Reemplaza a
 * PhpSpreadsheet para que el resultado sea idéntico al sistema viejo.
 */
class XlsResponse
{
    /**
     * @param  string  $view      Vista Blade (en resources/views/exports/...) que imprime la <table>.
     * @param  array   $data      Datos para la vista.
     * @param  string  $filename  Nombre del archivo .xls.
     */
    public static function make(string $view, array $data, string $filename): Response
    {
        // BOM UTF-8 para que Excel respete las tildes.
        $html = "\xEF\xBB\xBF" . view($view, $data)->render();

        return new Response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
