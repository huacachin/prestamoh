<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Generador del Anexo 1 — Cronograma de pagos. Arma el snapshot congelado
 * (contrato de datos que consumen la vista, el Word y los tests), renderiza
 * el PDF con dompdf y emite el DocumentoCliente versionado.
 *
 * REGLA DE ORO: el cronograma sale SIEMPRE de credit_installments (ordenado
 * por num_cuota, sin filas de monto 0), jamás recalculado. La cuota "del
 * documento" es la moda de las cuotas del cronograma, contada con clave
 * string (countBy con float trunca a entero).
 */
class GeneradorAnexo1
{
    /**
     * Arma el snapshot del Anexo 1 según el contrato de datos.
     *
     * @param  array  $overrides  Campos editables del modal: 'fecha' (d/m/Y)
     *                            pisa la fecha del documento; 'valor_vehiculo'
     *                            pisa vehiculo.valor en el snapshot.
     */
    public static function construirSnapshot(Client $client, Credit $credit, ?Vehiculo $vehiculo, array $overrides = []): array
    {
        // Cronograma ÍNTEGRO desde credit_installments (la relación no ordena).
        $filas = $credit->installments()
            ->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes', 'importe_excedente'])
            ->map(fn ($i) => [
                'n' => (int) $i->num_cuota,
                'fecha' => $i->fecha_vencimiento?->format('d/m/Y') ?? '',
                'monto' => round((float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente, 2),
            ])
            ->filter(fn ($f) => $f['monto'] > 0)
            ->values();

        // Cuota del documento = moda; clave STRING para que countBy no trunque.
        $cuota = (float) $filas
            ->countBy(fn ($f) => number_format($f['monto'], 2, '.', ''))
            ->sortDesc()
            ->keys()
            ->first();

        $vehiculoDatos = null;
        if ($vehiculo) {
            $valor = array_key_exists('valor_vehiculo', $overrides)
                ? $overrides['valor_vehiculo']
                : $vehiculo->valor;

            $vehiculoDatos = [
                'placa' => mb_strtoupper(trim((string) $vehiculo->placa)),
                'marca' => mb_strtoupper(trim((string) $vehiculo->marca)),
                'modelo' => mb_strtoupper(trim((string) $vehiculo->modelo)),
                'nro_serie' => mb_strtoupper(trim((string) $vehiculo->nro_serie)),
                'valor' => ($valor !== null && $valor !== '') ? (float) $valor : null,
            ];
        }

        return [
            'marca' => config('documentos.marca'),
            'fecha' => filled($overrides['fecha'] ?? null) ? $overrides['fecha'] : now()->format('d/m/Y'),
            'cliente' => [
                'nombre' => mb_strtoupper($client->fullName()),
                'documento_tipo' => mb_strtoupper(trim((string) $client->tipo_documento)) ?: 'DNI',
                'documento' => trim((string) $client->documento),
                'domicilio' => self::domicilio($client),
                'celular' => trim((string) $client->celular1),
                'correo' => trim((string) $client->email),
            ],
            'vehiculo' => $vehiculoDatos,
            'credito' => [
                'numero' => $credit->id,
                'moneda' => 'SOLES',
                'monto' => (float) $credit->importe,
                'frecuencia' => mb_strtoupper($credit->tipoPlanillaLabel()),
                'cuotas' => $filas->count(),
                'cuota' => $cuota,
            ],
            'cronograma' => [
                'filas' => $filas->all(),
                'total' => round($filas->sum('monto'), 2),
            ],
        ];
    }

    /**
     * Emite el Anexo 1: versiona, renderiza el PDF, lo guarda en el disco
     * public y crea el DocumentoCliente con el snapshot congelado. Si
     * $overrides trae 'valor_vehiculo' y hay vehículo, además persiste ese
     * valor en el Vehiculo (el dato sirve para el contrato después).
     */
    public static function generar(Client $client, Credit $credit, ?Vehiculo $vehiculo, array $overrides = []): DocumentoCliente
    {
        return DB::transaction(function () use ($client, $credit, $vehiculo, $overrides) {
            if ($vehiculo && array_key_exists('valor_vehiculo', $overrides)) {
                $valor = $overrides['valor_vehiculo'];
                $vehiculo->update(['valor' => ($valor !== null && $valor !== '') ? (float) $valor : null]);
            }

            $snapshot = self::construirSnapshot($client, $credit, $vehiculo, $overrides);

            $version = (int) DocumentoCliente::where('client_id', $client->id)
                ->where('credit_id', $credit->id)
                ->where('tipo', 'anexo1')
                ->lockForUpdate()
                ->max('version') + 1;

            $contenido = Pdf::loadView(
                RenderDocumento::vista('anexo1'),
                RenderDocumento::datosDesdeSnapshot($snapshot, 'anexo1', 'pdf')
            )->setPaper('a4')->output();

            $path = "documentos/cliente-{$client->id}/anexo1-credito-{$credit->id}-v{$version}.pdf";
            Storage::disk('public')->put($path, $contenido);

            $doc = DocumentoCliente::create([
                'client_id' => $client->id,
                'credit_id' => $credit->id,
                'tipo' => 'anexo1',
                'modelo' => null, // solo aplica al contrato
                'version' => $version,
                'snapshot' => $snapshot,
                'pdf_path' => $path,
                'sha256' => hash('sha256', $contenido),
                'estado' => 'emitido',
                'generado_por' => auth()->id(),
            ]);

            Audit::log("Generó el Anexo 1 v{$version} del crédito #{$credit->id} ({$snapshot['cliente']['nombre']})", $doc);

            return $doc;
        });
    }

    /**
     * HTML del documento con medio 'previa' (iframe del modal), sin persistir
     * nada — mismo snapshot y misma vista que el PDF emitido.
     */
    public static function previsualizar(Client $client, Credit $credit, ?Vehiculo $vehiculo, array $overrides = []): string
    {
        $snapshot = self::construirSnapshot($client, $credit, $vehiculo, $overrides);

        return view(
            RenderDocumento::vista('anexo1'),
            RenderDocumento::datosDesdeSnapshot($snapshot, 'anexo1', 'previa')
        )->render();
    }

    /**
     * Domicilio legal: direccion + tramos ubigeo presentes, en mayúsculas.
     * Cuando provincia y departamento coinciden se colapsa al giro registral
     * "PROVINCIA Y DEPARTAMENTO DE X" (mismo formato que config/documentos).
     */
    private static function domicilio(Client $client): string
    {
        $tramos = [];

        if (filled($client->direccion)) {
            $tramos[] = mb_strtoupper(trim($client->direccion));
        }
        if (filled($client->distrito)) {
            $tramos[] = 'DISTRITO DE '.mb_strtoupper(trim($client->distrito));
        }

        $provincia = filled($client->provincia) ? mb_strtoupper(trim($client->provincia)) : null;
        $departamento = filled($client->departamento) ? mb_strtoupper(trim($client->departamento)) : null;

        if ($provincia && $departamento && $provincia === $departamento) {
            $tramos[] = 'PROVINCIA Y DEPARTAMENTO DE '.$provincia;
        } else {
            if ($provincia) {
                $tramos[] = 'PROVINCIA DE '.$provincia;
            }
            if ($departamento) {
                $tramos[] = 'DEPARTAMENTO DE '.$departamento;
            }
        }

        return implode(', ', $tramos);
    }
}
