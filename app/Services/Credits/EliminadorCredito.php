<?php

namespace App\Services\Credits;

use App\Models\Contrato;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Garantia;
use App\Models\Payment;
use App\Services\Documentos\CopiaImpresion;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Borrado REAL de un crédito (el "Eliminar" del listado) con todo lo suyo:
 * documentos emitidos (fila, PDF, copia de impresión y foto del voucher si
 * nadie más la usa), cuotas y pagos. Las moras van en cascada por la BD.
 *
 * Por qué existe (24/09/2026): el listado borraba cuotas, pagos y crédito
 * uno tras otro y sin transacción. Desde que hay documentos emitidos (clave
 * foránea sin cascada, a propósito: un contrato no se pisa), el borrado del
 * crédito fallaba al final y dejaba el crédito vivo pero SIN cronograma
 * (crédito 29429). Ahora va todo en una transacción y la traza de los
 * documentos borrados (tipo, versión, hash, correlativo) queda en la
 * auditoría. Los archivos se borran DESPUÉS de confirmar la transacción,
 * porque no se pueden deshacer.
 *
 * Las reglas de negocio (permiso, del día, propio, sin pagos aplicados) las
 * aplica quien llama; aquí solo se comprueba lo que la BD no dejaría borrar.
 */
final class EliminadorCredito
{
    /**
     * @param  array  $extra  Datos adicionales para la auditoría (p. ej. quién lo pidió).
     * @return array Resumen de lo borrado (el mismo que va a la auditoría).
     *
     * @throws CreditoNoEliminableException si tiene garantía o contrato del área legal
     */
    public static function eliminar(Credit $credit, array $extra = []): array
    {
        $garantias = Garantia::where('credit_id', $credit->id)->count();
        $contratos = Contrato::where('credit_id', $credit->id)->count();
        if ($garantias > 0 || $contratos > 0) {
            throw new CreditoNoEliminableException(
                "No se puede eliminar el crédito #{$credit->id}: tiene {$garantias} garantía(s) y {$contratos} contrato(s) "
                .'del área legal. Hay que resolverlos allí antes.'
            );
        }

        $archivos = [];

        $resumen = DB::transaction(function () use ($credit, $extra, &$archivos) {
            $detalle = [];
            foreach (DocumentoCliente::where('credit_id', $credit->id)->orderBy('id')->get() as $doc) {
                $detalle[] = [
                    'id' => $doc->id,
                    'tipo' => $doc->tipo,
                    'version' => $doc->version,
                    'estado' => $doc->estado,
                    'correlativo' => $doc->correlativo,
                    'sha256' => $doc->sha256,
                    'pdf_path' => $doc->pdf_path,
                    'generado_por' => $doc->generado_por,
                    'emitido' => (string) $doc->created_at,
                ];
                if ($doc->pdf_path) {
                    $archivos[] = $doc->pdf_path;
                    array_push($archivos, ...CopiaImpresion::copiasDe($doc->pdf_path));
                }
                // La foto del voucher del anexo 2 solo se va si ningún otro documento la usa.
                $imagen = $doc->snapshot['imagen_path'] ?? null;
                if ($imagen && ! DocumentoCliente::where('id', '<>', $doc->id)->where('snapshot', 'like', '%'.$imagen.'%')->exists()) {
                    $archivos[] = $imagen;
                }
                if ($doc->delete() !== true) {
                    throw new \RuntimeException("No se pudo borrar el documento #{$doc->id}");
                }
            }

            $cuotas = CreditInstallment::where('credit_id', $credit->id)->delete();
            $pagos = Payment::where('credit_id', $credit->id)->delete();

            $resumen = $extra + [
                'cliente_id' => $credit->client_id,
                'importe' => (float) $credit->importe,
                'fecha_prestamo' => $credit->fecha_prestamo?->format('Y-m-d'),
                'situacion' => $credit->situacion,
                'cuotas_borradas' => $cuotas,
                'pagos_borrados' => $pagos,
                'documentos' => $detalle,
            ];

            Audit::log(
                "Eliminó el crédito #{$credit->id}".($detalle !== [] ? ' con '.count($detalle).' documento(s) emitido(s)' : ''),
                $credit,
                $resumen
            );

            if ($credit->delete() !== true) {
                throw new \RuntimeException("No se pudo borrar el crédito #{$credit->id}");
            }

            return $resumen;
        });

        $disco = Storage::disk('public');
        foreach (array_unique($archivos) as $ruta) {
            if ($disco->exists($ruta) && ! $disco->delete($ruta)) {
                Log::warning("Crédito #{$credit->id} eliminado, pero no se pudo borrar el archivo {$ruta}");
            }
        }

        return $resumen;
    }
}
