<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use App\Support\Audit;
use App\Support\Documentos\DomicilioLegal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
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
    /** Acepta un Vehiculo suelto, una colección/array o null → siempre colección. */
    private static function comoColeccion(Vehiculo|iterable|null $vehiculo): Collection
    {
        if ($vehiculo === null) {
            return collect();
        }

        return $vehiculo instanceof Vehiculo ? collect([$vehiculo]) : collect($vehiculo)->filter();
    }

    public static function construirSnapshot(Client $client, Credit $credit, Vehiculo|iterable|null $vehiculo, array $overrides = []): array
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

        // Varios vehículos por anexo (28/08). Los valores llegan en
        // $overrides['valores_vehiculo'] indexados por id del vehículo.
        $valores = $overrides['valores_vehiculo'] ?? [];
        $vehiculosDatos = self::comoColeccion($vehiculo)->map(function (Vehiculo $v) use ($valores) {
            $valor = array_key_exists($v->id, $valores) ? $valores[$v->id] : $v->valor;

            return [
                'placa' => mb_strtoupper(trim((string) $v->placa)),
                'marca' => mb_strtoupper(trim((string) $v->marca)),
                'modelo' => mb_strtoupper(trim((string) $v->modelo)),
                'nro_serie' => mb_strtoupper(trim((string) $v->nro_serie)),
                'valor' => ($valor !== null && $valor !== '') ? (float) $valor : null,
            ];
        })->values()->all();

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
            // 'vehiculo' (singular) se conserva para que los documentos ya
            // emitidos y las vistas antiguas sigan resolviendo igual.
            'vehiculo' => $vehiculosDatos[0] ?? null,
            'vehiculos' => $vehiculosDatos,
            'credito' => [
                'numero' => $credit->id,
                'moneda' => 'SOLES',
                'monto' => (float) $credit->importe,
                'frecuencia' => mb_strtoupper($credit->tipoPlanillaLabel()),
                'cuotas' => $filas->count(),
                'cuota' => $cuota,
                // Maestro del área legal (04/09): plazo en unidades del tipo,
                // fecha de inicio y TIM (5% semanal/mensual; el diario mantiene
                // su mora1 histórico en soles por día).
                'plazo' => $filas->count().' '.match ((int) $credit->tipo_planilla) {
                    1 => 'semanas', 3 => 'meses', 4 => 'días', default => 'cuotas',
                },
                'fecha_inicio' => $credit->fecha_prestamo?->format('d/m/Y') ?? '',
                'tim' => (int) $credit->tipo_planilla === 4
                    ? 'S/ '.number_format((float) $credit->mora1, 2, ',', '.').' por día'
                    : Credit::TASA_MORA_PCT.'%',
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
    public static function generar(Client $client, Credit $credit, Vehiculo|iterable|null $vehiculo, array $overrides = []): DocumentoCliente
    {
        return DB::transaction(function () use ($client, $credit, $vehiculo, $overrides) {
            // El valor tipeado se persiste en la ficha de CADA vehículo (el
            // contrato lo reutiliza después).
            $valores = $overrides['valores_vehiculo'] ?? [];
            foreach (self::comoColeccion($vehiculo) as $v) {
                if (array_key_exists($v->id, $valores)) {
                    $valor = $valores[$v->id];
                    $v->update(['valor' => ($valor !== null && $valor !== '') ? (float) $valor : null]);
                }
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
    public static function previsualizar(Client $client, Credit $credit, Vehiculo|iterable|null $vehiculo, array $overrides = []): string
    {
        $snapshot = self::construirSnapshot($client, $credit, $vehiculo, $overrides);

        return view(
            RenderDocumento::vista('anexo1'),
            RenderDocumento::datosDesdeSnapshot($snapshot, 'anexo1', 'previa')
        )->render();
    }

    /** Domicilio legal en el formato de las maestras (ver DomicilioLegal). */
    private static function domicilio(Client $client): string
    {
        return DomicilioLegal::deCliente($client);
    }
}
