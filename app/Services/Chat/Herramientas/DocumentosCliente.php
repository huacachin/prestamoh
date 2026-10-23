<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Client;
use App\Models\DocumentoCliente;

/**
 * Documentos emitidos a un cliente (23/10/2026): contrato, Anexo 1
 * (cronograma) y Anexo 2 (constancia de entrega), vigentes y anulados.
 */
final class DocumentosCliente implements Herramienta
{
    public function nombre(): string
    {
        return 'documentos_cliente';
    }

    public function descripcion(): string
    {
        return 'Documentos emitidos a un cliente: tipo (Contrato, Anexo 1 cronograma, Anexo 2 constancia de entrega), crédito al que pertenece, modelo de contrato, versión, correlativo, estado (emitido o anulado), fecha, quién lo generó y enlace al PDF. Úsala para "tiene contrato", "qué documentos se le generaron".';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'client_id' => ['type' => 'integer', 'description' => 'Id del cliente'],
            ],
            'required' => ['client_id'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $id = (int) ($argumentos['client_id'] ?? 0);
        $cliente = Client::find($id);
        if ($cliente === null) {
            return ['error' => "No existe el cliente {$id}."];
        }

        $documentos = DocumentoCliente::with('generadoPor:id,name,username')
            ->where('client_id', $cliente->id)->orderByDesc('credit_id')->orderBy('tipo')->orderByDesc('version')->get();

        return [
            'client_id' => (int) $cliente->id,
            'cliente' => $cliente->fullName(),
            'documentos' => $documentos->map(fn (DocumentoCliente $d) => [
                'id' => (int) $d->id,
                'tipo' => $d->tipoLabel(),
                'credito' => (int) $d->credit_id,
                'modelo' => $d->modelo,
                'version' => (int) $d->version,
                'correlativo' => $d->correlativo,
                'estado' => $d->estado,
                'fecha' => $d->created_at?->format('d/m/Y H:i'),
                'generado_por' => $d->generadoPor?->username ?? $d->generadoPor?->name,
                'pdf' => route('clients.documentos.pdf', $d->id),
            ])->values()->all(),
            'enlace' => route('clients.documentos', $cliente->id),
        ];
    }
}
