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
    /**
     * Vouchers del desembolso (obs. 5.1 del Área Legal, 29/09): hasta dos
     * operaciones que entre las dos suman el monto. $datos['vouchers'] trae la
     * lista; sin ella, el voucher único de siempre viaja en las claves de
     * primer nivel (banco, modalidad, transcripcion, monto, imagen_path).
     *
     * @return list<array{banco: string, modalidad: string, transcripcion: string, monto: string, imagen_path: ?string}>
     */
    public static function vouchersDe(array $datos): array
    {
        $lista = isset($datos['vouchers']) && is_array($datos['vouchers']) && $datos['vouchers'] !== []
            ? array_values($datos['vouchers'])
            : [$datos];

        return array_map(fn (array $v) => [
            'banco' => trim((string) ($v['banco'] ?? '')),
            'modalidad' => trim((string) ($v['modalidad'] ?? '')),
            'transcripcion' => trim((string) ($v['transcripcion'] ?? '')),
            'monto' => trim((string) ($v['monto'] ?? '')),
            'imagen_path' => filled($v['imagen_path'] ?? null) ? $v['imagen_path'] : null,
        ], $lista);
    }

    /** Arma el snapshot del Anexo 2 según el contrato de datos. */
    public static function construirSnapshot(Client $client, Credit $credit, array $datos): array
    {
        $vouchers = self::vouchersDe($datos);
        // El primero sigue en las claves de siempre: los lectores viejos del
        // snapshot (y los documentos ya emitidos) no cambian.
        $banco = $vouchers[0]['banco'];
        $modalidad = $vouchers[0]['modalidad'];

        $snapshot = [
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
            // LITERAL (15/09): el maestro transcribe lo que dice el voucher, en
            // su orden y con sus palabras ("ENVIADO A", "NÚMERO DE OPERACIÓN"),
            // no con etiquetas nuestras. Antes se armaba con
            // BancosVoucher::transcripcion() y salía un documento distinto al
            // que el área firma. Los snapshots viejos (pares label/valor) se
            // siguen renderizando a su manera en la vista.
            'transcripcion' => $vouchers[0]['transcripcion'],
            'imagen_path' => $vouchers[0]['imagen_path'],
        ];

        // Obs. 5.1: con DOS vouchers va la lista completa; la plantilla pinta
        // cada uno con su foto y su línea DETALLES, apilados como el maestro
        // del área. Con uno, el snapshot es exactamente el de siempre.
        if (count($vouchers) > 1) {
            $snapshot['vouchers'] = array_map(fn (array $v) => [
                'banco' => $v['banco'],
                'modalidad' => $v['modalidad'],
                'titulo' => BancosVoucher::titulo($v['banco'], $v['modalidad']),
                'banco_legal' => BancosVoucher::nombreLegal($v['banco']),
                'transcripcion' => $v['transcripcion'],
                'imagen_path' => $v['imagen_path'],
                'monto' => self::parsearMonto($v['monto']),
            ], $vouchers);
        }

        return $snapshot;
    }

    /**
     * Bloqueantes: combo banco/modalidad fuera del catálogo, transcripción
     * vacía y el CUADRE DEL MONTO contra credit->importe.
     * La imagen NO bloquea (puede adjuntarse después).
     *
     * @return list<string> errores bloqueantes
     */
    public static function validar(Client $client, Credit $credit, array $datos = []): array
    {
        $vouchers = self::vouchersDe($datos);
        $dos = count($vouchers) > 1;

        foreach ($vouchers as $v) {
            if (! BancosVoucher::esComboValido($v['banco'], $v['modalidad'])) {
                return ["La combinación banco/modalidad '{$v['banco']}/{$v['modalidad']}' no está en el catálogo de vouchers."];
            }
        }

        $errores = [];
        $suma = 0.0;
        $montosOk = true;

        foreach ($vouchers as $k => $v) {
            $cual = $dos ? ' '.($k + 1) : '';

            if ($v['transcripcion'] === '') {
                $errores[] = "Falta la transcripción del voucher{$cual}: es el cuerpo de la constancia.";
            }

            // ─── Cuadre del monto: la constancia debe reproducir el desembolso ───
            // Se compara el monto declarado del voucher (o la SUMA de los dos,
            // obs. 5.1) contra credit->importe. En BBVA hay que escribir el
            // IMPORTE ABONADO, no el PAGADO (que suma el ITF): el ITF no es
            // parte del desembolso que recibió el deudor.
            if ($v['monto'] === '') {
                $errores[] = "Falta el monto del voucher{$cual}: es lo que cuadra la constancia contra el crédito.";
                $montosOk = false;
            } else {
                $monto = self::parsearMonto($v['monto']);
                if ($monto === null) {
                    $errores[] = "El monto del voucher{$cual} ('{$v['monto']}') no se puede interpretar como un número.";
                    $montosOk = false;
                } else {
                    $suma += $monto;
                }
            }
        }

        if ($montosOk) {
            $montoCredito = round((float) $credit->importe, 2);
            if (abs($suma - $montoCredito) > 0.01) {
                $errores[] = $dos
                    ? sprintf(
                        'La suma de los dos vouchers (S/ %s) no coincide con el monto del crédito (S/ %s) — entre los dos deben reproducir el desembolso exacto.',
                        number_format($suma, 2),
                        number_format($montoCredito, 2)
                    )
                    : sprintf(
                        'El monto del voucher (S/ %s) no coincide con el monto del crédito (S/ %s) — la constancia debe reproducir el desembolso exacto.',
                        number_format($suma, 2),
                        number_format($montoCredito, 2)
                    );
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

            $doc = new DocumentoCliente([
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
            $doc->sinAuditoriaAutomatica(fn () => $doc->save());

            Audit::log("Generó el Anexo 2 v{$version} del crédito #{$credit->id} ({$snapshot['cliente']['nombre']})", $doc);

            return $doc;
        });
    }

    /**
     * Parsea el monto tal como se transcribe del voucher REAL. Los formatos
     * que traen los comprobantes del área (verificados contra los .docx de
     * "3. Anexo 2 - Constancia de entrega del monto"):
     *
     *   "S/ 8,000.00"              → 8000.00   (el único que ya funcionaba)
     *   "S/*****15,000.00"         → 15000.00  (BCP enmascara con asteriscos)
     *   "S/ 12.000.00"             → 12000.00  (miles con PUNTO)
     *   "DEPOSITO **** 25,000.00"  → 25000.00  (texto delante del número)
     *   "-7,000.00"                → 7000.00   (BBVA muestra el cargo en
     *                                           negativo; el cuadre compara
     *                                           magnitudes, no signos)
     *
     * Estrategia: extraer el ÚLTIMO token numérico de la cadena (el monto va
     * siempre al final), decidir el separador decimal por posición (los
     * últimos 2 dígitos tras el último . o ,) y devolver el valor absoluto.
     * Devuelve null si no hay ningún número.
     */
    public static function parsearMonto(string $valor): ?float
    {
        // El monto es el último grupo numérico (dígitos con . , intercalados).
        if (! preg_match_all('/\d[\d.,]*/u', $valor, $m) || $m[0] === []) {
            return null;
        }
        $token = rtrim(end($m[0]), '.,');

        $ultimoPunto = strrpos($token, '.');
        $ultimaComa = strrpos($token, ',');

        if ($ultimoPunto === false && $ultimaComa === false) {
            return round(abs((float) $token), 2);
        }

        // El separador DECIMAL es el último de los dos; el resto son miles.
        // Cubre "12.000.00" (miles con punto y decimal con punto: solo el
        // último punto es decimal) y "12,000.00" por igual.
        $posDecimal = max((int) $ultimoPunto, (int) $ultimaComa);
        $decimales = substr($token, $posDecimal + 1);

        // Un separador seguido de 3 dígitos al final es de MILES, no decimal
        // ("25,000" → 25000; "1.500" → 1500).
        if (strlen($decimales) === 3) {
            $entero = preg_replace('/[.,]/', '', $token);

            return round(abs((float) $entero), 2);
        }

        $entero = preg_replace('/[.,]/', '', substr($token, 0, $posDecimal));

        return round(abs((float) ($entero.'.'.$decimales)), 2);
    }
}
