<?php

namespace App\Services\Documentos;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Support\Audit;
use App\Support\Documentos\BancosVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Generador del Anexo 2 — Constancia de entrega del monto de la obligación
 * principal. Se emite cuando YA se transfirió el dinero al cliente: transcribe
 * el voucher bancario (catálogo banco × modalidad de BancosVoucher) y embebe
 * la foto del comprobante. Arma el snapshot congelado y emite el
 * DocumentoCliente versionado, calcando el patrón de GeneradorAnexo1/Contrato.
 *
 * VALIDACIÓN BLOQUEANTE A PROPÓSITO: además del combo y los campos requeridos
 * del voucher, el monto transcrito DEBE cuadrar con credit->importe (±0.01).
 * Esta verificación ya salvó al área de emitir una constancia de S/ 8,000
 * sobre un desembolso de S/ 5,000 — no es una validación suave.
 *
 * La imagen del comprobante NO es obligatoria: el área a veces la adjunta
 * después de transcribir. Con imagen_path null la previa muestra un
 * recuadro placeholder y el PDF/Word emitido simplemente omite el bloque.
 *
 * Contrato de datos de $datos:
 *  - banco (clave de BancosVoucher::BANCOS)
 *  - modalidad (clave de BancosVoucher::MODALIDADES)
 *  - campos (assoc clave => valor según BancosVoucher::campos(banco, modalidad))
 *  - imagen_path (string|null, relativa al disk public)
 *  - fecha ('d/m/Y'; default hoy)
 */
class GeneradorAnexo2
{
    /** Arma el snapshot del Anexo 2 según el contrato de datos. */
    public static function construirSnapshot(Client $client, Credit $credit, array $datos): array
    {
        $banco = trim((string) ($datos['banco'] ?? ''));
        $modalidad = trim((string) ($datos['modalidad'] ?? ''));
        $campos = $datos['campos'] ?? [];

        return [
            'marca' => config('documentos.marca'),
            'fecha' => filled($datos['fecha'] ?? null) ? $datos['fecha'] : now()->format('d/m/Y'),
            'cliente' => [
                'nombre' => mb_strtoupper($client->fullName()),
                'documento_tipo' => mb_strtoupper(trim((string) $client->tipo_documento)) ?: 'DNI',
                'documento' => trim((string) $client->documento),
            ],
            'credito' => [
                'numero' => $credit->id,
                'monto' => (float) $credit->importe,
            ],
            'banco' => $banco,
            'modalidad' => $modalidad,
            'titulo' => BancosVoucher::titulo($banco, $modalidad),
            'banco_legal' => BancosVoucher::nombreLegal($banco),
            'transcripcion' => BancosVoucher::transcripcion($banco, $modalidad, $campos),
            'imagen_path' => filled($datos['imagen_path'] ?? null) ? $datos['imagen_path'] : null,
        ];
    }

    /**
     * Bloqueantes: combo banco/modalidad fuera del catálogo, campos requeridos
     * del voucher sin valor y el CUADRE DEL MONTO contra credit->importe.
     * La imagen NO bloquea (puede adjuntarse después).
     *
     * @return list<string> errores bloqueantes
     */
    public static function validar(Client $client, Credit $credit, array $datos = []): array
    {
        $banco = trim((string) ($datos['banco'] ?? ''));
        $modalidad = trim((string) ($datos['modalidad'] ?? ''));

        if (! BancosVoucher::esComboValido($banco, $modalidad)) {
            return ["La combinación banco/modalidad '{$banco}/{$modalidad}' no está en el catálogo de vouchers."];
        }

        $errores = [];
        $campos = $datos['campos'] ?? [];

        $faltantes = BancosVoucher::faltantes($banco, $modalidad, $campos);
        if ($faltantes !== []) {
            $errores[] = 'Faltan campos requeridos del voucher: '.implode(', ', $faltantes).'.';
        }

        // ─── Cuadre del monto: la constancia debe reproducir el desembolso ───
        $montoTexto = trim((string) ($campos['monto'] ?? ''));
        if ($montoTexto !== '') {
            $montoVoucher = self::parsearMonto($montoTexto);

            if ($montoVoucher === null) {
                $errores[] = "El monto del voucher ('{$montoTexto}') no se puede interpretar como un número.";
            } else {
                $montoCredito = round((float) $credit->importe, 2);
                if (abs($montoVoucher - $montoCredito) > 0.01) {
                    $errores[] = sprintf(
                        'El monto del voucher (S/ %s) no coincide con el monto del crédito (S/ %s) — la constancia debe reproducir el desembolso exacto.',
                        number_format($montoVoucher, 2),
                        number_format($montoCredito, 2)
                    );
                }
            }
        }

        return $errores;
    }

    /** @throws \InvalidArgumentException con la lista de bloqueantes si la validación falla */
    private static function asegurarValido(Client $client, Credit $credit, array $datos): void
    {
        $errores = self::validar($client, $credit, $datos);
        if ($errores !== []) {
            throw new \InvalidArgumentException('El Anexo 2 no puede generarse: '.implode(' | ', $errores));
        }
    }

    /**
     * HTML de la constancia con medio 'previa' (iframe del modal), sin
     * persistir nada — mismo snapshot y misma vista que el PDF emitido.
     * Con imagen_path null la vista muestra el recuadro placeholder.
     */
    public static function previsualizar(Client $client, Credit $credit, array $datos = []): string
    {
        self::asegurarValido($client, $credit, $datos);

        $snapshot = self::construirSnapshot($client, $credit, $datos);

        return view(
            RenderDocumento::vista('anexo2'),
            RenderDocumento::datosDesdeSnapshot($snapshot, 'anexo2', 'previa')
        )->render();
    }

    /**
     * Emite el Anexo 2: versiona por (cliente, crédito, tipo 'anexo2'),
     * renderiza el PDF, lo guarda en el disco public y crea el
     * DocumentoCliente con el snapshot congelado.
     */
    public static function generar(Client $client, Credit $credit, array $datos = []): DocumentoCliente
    {
        self::asegurarValido($client, $credit, $datos);

        return DB::transaction(function () use ($client, $credit, $datos) {
            $snapshot = self::construirSnapshot($client, $credit, $datos);

            $version = (int) DocumentoCliente::where('client_id', $client->id)
                ->where('credit_id', $credit->id)
                ->where('tipo', 'anexo2')
                ->lockForUpdate()
                ->max('version') + 1;

            $contenido = Pdf::loadView(
                RenderDocumento::vista('anexo2'),
                RenderDocumento::datosDesdeSnapshot($snapshot, 'anexo2', 'pdf')
            )->setPaper('a4')->output();

            $path = "documentos/cliente-{$client->id}/anexo2-credito-{$credit->id}-v{$version}.pdf";
            Storage::disk('public')->put($path, $contenido);

            $doc = DocumentoCliente::create([
                'client_id' => $client->id,
                'credit_id' => $credit->id,
                'tipo' => 'anexo2',
                'modelo' => null, // solo aplica al contrato
                'version' => $version,
                'snapshot' => $snapshot,
                'pdf_path' => $path,
                'sha256' => hash('sha256', $contenido),
                'estado' => 'emitido',
                'generado_por' => auth()->id(),
            ]);

            Audit::log("Generó el Anexo 2 v{$version} del crédito #{$credit->id} ({$snapshot['cliente']['nombre']})", $doc);

            return $doc;
        });
    }

    /**
     * Parsea el monto tal como se transcribe del voucher: tolera 'S/.', 'S/',
     * separador de miles con coma y espacios ("S/ 8,000.00" → 8000.00).
     * Devuelve null si tras limpiar no queda un número.
     */
    private static function parsearMonto(string $valor): ?float
    {
        // 'S/.' antes que 'S/': quitar primero 'S/' dejaría un '.' suelto
        // adelante y "S/. 8000" se leería como 0.8000.
        $limpio = str_ireplace(['s/.', 's/', ','], '', $valor);
        $limpio = preg_replace('/\s+/u', '', (string) $limpio) ?? '';

        return is_numeric($limpio) ? round((float) $limpio, 2) : null;
    }
}
