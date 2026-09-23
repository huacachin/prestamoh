<?php

namespace App\Http\Controllers;

use App\Models\DocumentoCliente;
use App\Services\Documentos\CopiaImpresion;
use App\Services\Documentos\CopiaImpresionException;
use App\Services\Documentos\RenderDocumento;
use App\Support\DocResponse;
use Illuminate\Support\Facades\Log;
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

    /**
     * Copia para IMPRIMIR: el mismo PDF emitido pero con las hojas como imagen
     * (Ghostscript), que es lo único que la fotocopiadora imprime rápido. Se
     * abre en pestaña nueva (inline) para mandarlo a imprimir desde ahí. Si
     * la copia no se puede producir, se AVISA en pantalla (con el enlace al
     * PDF normal) y se deja rastro en el log: nadie se queda sin imprimir,
     * pero tampoco imprime lento sin saber por qué.
     */
    public function imprimir(int $id)
    {
        $doc = DocumentoCliente::findOrFail($id);
        $this->autorizarCartera($doc);

        abort_unless($doc->pdf_path && Storage::disk('public')->exists($doc->pdf_path), 404);

        $nombre = $doc->nombreArchivo().'-impresion.pdf';

        try {
            $ruta = CopiaImpresion::ruta($doc->pdf_path, CopiaImpresion::colorPara($doc->tipo));
        } catch (CopiaImpresionException $e) {
            Log::warning("Copia de impresión no disponible para el documento #{$doc->id}", ['error' => $e->getMessage()]);

            return response()
                ->view('documentos.impresion-no-disponible', [
                    'nombre' => $nombre,
                    'urlPdf' => route('clients.documentos.pdf', $doc->id),
                ])
                ->header('X-Copia-Impresion', 'no-disponible');
        }

        return Storage::disk('public')->response($ruta, $nombre, ['X-Copia-Impresion' => 'imagen']);
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
