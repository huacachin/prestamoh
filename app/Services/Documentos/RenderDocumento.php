<?php

namespace App\Services\Documentos;

use App\Models\DocumentoCliente;
use App\Support\Documentos\Enfasis;
use App\Support\Documentos\Resaltado;

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
        // Solo la previa del wizard pinta en rojo los datos variables (16/10).
        if ($tipo === 'contrato') {
            return [
                'vm' => GeneradorContrato::vmDesdeSnapshot($snapshot, resaltar: $medio === 'previa'),
                'medio' => $medio,
            ];
        }

        // Los anexos renderizan desde el snapshot; $dato marca cada dato
        // variable (rojo solo en la previa, 17/10): {{ $dato($c['nombre']) }}.
        // (No se llama $v: anexo2.blade recorre los vouchers como $v.)
        return [
            'd' => $snapshot,
            'medio' => $medio,
            'dato' => Resaltado::marcador($medio === 'previa'),
        ];
    }

    public static function datos(DocumentoCliente $doc, bool $paraPdf): array
    {
        return self::datosDesdeSnapshot($doc->snapshot, $doc->tipo, $paraPdf ? 'pdf' : 'word');
    }

    /**
     * HTML final del documento, con las negritas de los términos definidos
     * ya aplicadas (solo el contrato las lleva). Punto único de render:
     * PDF, Word y previa pasan por aquí y salen idénticos.
     *
     * @param  'pdf'|'word'|'previa'  $medio
     */
    public static function html(array $snapshot, string $tipo, string $medio): string
    {
        $html = view(self::vista($tipo), self::datosDesdeSnapshot($snapshot, $tipo, $medio))->render();

        return $tipo === 'contrato' ? Enfasis::aplicar($html) : $html;
    }
}
