<?php

namespace App\Services\Documentos;

use App\Models\DocumentoCliente;

/**
 * Mapa central tipo de documento → vista Blade y datos de render. Todo
 * documento se renderiza SIEMPRE desde su snapshot congelado, por cualquiera
 * de los tres medios: 'pdf' (dompdf, fiel de impresión), 'word' (DocResponse,
 * editable) o 'previa' (HTML en iframe antes de emitir).
 */
class RenderDocumento
{
    public static function vista(string $tipo): string
    {
        return match ($tipo) {
            'anexo1' => 'documentos.pdf.anexo1',
            'contrato' => 'documentos.pdf.contrato',
            'anexo2' => 'documentos.pdf.anexo2',
            default => abort(404),
        };
    }

    /** @param  'pdf'|'word'|'previa'  $medio */
    public static function datosDesdeSnapshot(array $snapshot, string $tipo, string $medio): array
    {
        // El contrato se renderiza con view-model (los partials consumen $vm, no $d).
        if ($tipo === 'contrato') {
            return [
                'vm' => GeneradorContrato::vmDesdeSnapshot($snapshot),
                'medio' => $medio,
            ];
        }

        return [
            'd' => $snapshot,
            'medio' => $medio,
        ];
    }

    public static function datos(DocumentoCliente $doc, bool $paraPdf): array
    {
        return self::datosDesdeSnapshot($doc->snapshot, $doc->tipo, $paraPdf ? 'pdf' : 'word');
    }
}
