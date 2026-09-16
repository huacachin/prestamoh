<?php

namespace App\Http\Controllers;

use App\Models\DocumentoCliente;
use App\Services\Documentos\RenderDocumento;
use App\Support\DocResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Descargas de los documentos del cliente. El PDF se sirve desde el archivo
 * guardado al emitir (versión fiel de impresión); el Word se genera al vuelo
 * desde el snapshot congelado con el MISMO render — ambos formatos siempre
 * idénticos en contenido.
 */
class DocumentoClienteController extends Controller
{
    public function pdf(int $id)
    {
        $doc = DocumentoCliente::findOrFail($id);
        $this->autorizarCartera($doc);

        abort_unless($doc->pdf_path && Storage::disk('public')->exists($doc->pdf_path), 404);

        return Storage::disk('public')->download($doc->pdf_path, $doc->nombreArchivo().'.pdf');
    }

    public function word(int $id)
    {
        $doc = DocumentoCliente::findOrFail($id);
        $this->autorizarCartera($doc);

        return DocResponse::desdeHtml(
            RenderDocumento::html($doc->snapshot, $doc->tipo, 'word'),
            $doc->nombreArchivo().'.doc'
        );
    }

    /** Analista (scope-propio): solo documentos de SUS clientes (los ids son enumerables). */
    private function autorizarCartera(DocumentoCliente $doc): void
    {
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $doc->client?->asesor_id !== (int) auth()->id(),
            403, 'Este documento no pertenece a tu cartera.'
        );
    }
}
