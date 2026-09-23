<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\Documentos\CopiaImpresion;
use App\Services\Documentos\CopiaImpresionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador thin del Área Legal: cada método devuelve la página contenedora
 * del componente Livewire correspondiente (patrón del resto de módulos).
 */
class LegalController extends Controller
{
    public function vehiculos()
    {
        return view('legal.vehiculos');
    }

    public function garantias()
    {
        return view('legal.garantias-index');
    }

    public function garantiaCreate(?int $creditId = null)
    {
        return view('legal.garantias-create', ['creditId' => $creditId]);
    }

    public function garantiaShow(int $id)
    {
        return view('legal.garantias-show', ['garantiaId' => $id]);
    }

    public function notaria()
    {
        return view('legal.notaria');
    }

    public function papeletas()
    {
        return view('legal.papeletas');
    }

    public function caja()
    {
        return view('legal.caja');
    }

    public function expedientes()
    {
        return view('legal.expedientes-index');
    }

    public function expedienteCreate()
    {
        return view('legal.expedientes-create');
    }

    public function expedienteShow(int $id)
    {
        return view('legal.expedientes-show', ['expedienteId' => $id]);
    }

    public function settings()
    {
        return view('legal.settings');
    }

    public function contratoForm(int $id)
    {
        return view('legal.contrato-form', ['garantiaId' => $id]);
    }

    public function contratoPdf(int $id)
    {
        $contrato = Contrato::findOrFail($id);

        abort_unless($contrato->pdf_path && Storage::disk('public')->exists($contrato->pdf_path), 404);

        $nombre = str_replace(' ', '-', $contrato->numero)."-v{$contrato->version}.pdf";

        return Storage::disk('public')->download($contrato->pdf_path, $nombre);
    }

    /** Copia para IMPRIMIR del contrato legal (hojas como imagen, en gris). Ver DocumentoClienteController::imprimir. */
    public function contratoImprimir(int $id)
    {
        $contrato = Contrato::findOrFail($id);

        abort_unless($contrato->pdf_path && Storage::disk('public')->exists($contrato->pdf_path), 404);

        $nombre = str_replace(' ', '-', $contrato->numero)."-v{$contrato->version}-impresion.pdf";

        try {
            $ruta = CopiaImpresion::ruta($contrato->pdf_path, CopiaImpresion::colorPara('contrato'));
        } catch (CopiaImpresionException $e) {
            Log::warning("Copia de impresión no disponible para el contrato legal #{$contrato->id}", ['error' => $e->getMessage()]);

            return response()
                ->view('documentos.impresion-no-disponible', [
                    'nombre' => $nombre,
                    'urlPdf' => route('legal.contratos.pdf', $contrato->id),
                ])
                ->header('X-Copia-Impresion', 'no-disponible');
        }

        return Storage::disk('public')->response($ruta, $nombre, ['X-Copia-Impresion' => 'imagen']);
    }
}
