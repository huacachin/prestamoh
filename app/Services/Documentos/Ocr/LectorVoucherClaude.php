<?php

namespace App\Services\Documentos\Ocr;

use Anthropic\Client;
use App\Support\Documentos\BancosVoucher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lee el voucher con la API de Claude (15/09).
 *
 * Por qué un modelo multimodal y no un OCR clásico: en la prueba a ciegas
 * sobre los 15 vouchers maestros acertó 109 de 113 datos críticos (96%), y de
 * las 4 diferencias 2 eran del comparador, 1 fue una duda que el propio modelo
 * declaró y 1 fue un ERROR de la transcripción manual. Un OCR de texto crudo
 * exigiría reglas por banco que se rompen cada vez que un banco cambia su app.
 *
 * La transcripción que devuelve es LITERAL y SELECTIVA, como la hacen a mano:
 * copia lo que dice el voucher en su orden y se salta el ruido del pie
 * (RUC del banco, comisiones en cero, códigos internos, publicidad).
 *
 * NUNCA decide sola: lo que devuelve va al formulario para que el operador lo
 * confirme, y `dudas` señala los dígitos que el modelo no distinguió con
 * certeza — es la mitad del valor en un documento que se firma.
 */
class LectorVoucherClaude implements LectorDeVoucher
{
    /** Formatos que la API acepta como imagen. */
    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** 5 MB: tope de la API por imagen. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ESQUEMA = [
        'type' => 'json_schema',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'transcripcion' => ['type' => 'string'],
                'monto' => ['type' => 'string'],
                'beneficiario' => ['type' => 'string'],
                'dudas' => ['type' => 'string'],
                // 18/09: el banco y la modalidad los identifica la propia lectura
                // (claves del catálogo BancosVoucher, o vacío si no coincide con
                // ninguno). Antes el operador los elegía a mano antes de leer.
                'banco' => ['type' => 'string'],
                'modalidad' => ['type' => 'string'],
            ],
            'required' => ['transcripcion', 'monto', 'beneficiario', 'dudas', 'banco', 'modalidad'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(private ?Client $cliente = null) {}

    public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array
    {
        $imagen = $this->leerImagen($rutaAbsoluta);
        $modelo = (string) config('services.anthropic.modelo');

        $mensajes = [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => [
                    'type' => 'base64',
                    'media_type' => $imagen['tipo'],
                    'data' => $imagen['datos'],
                ]],
                ['type' => 'text', 'text' => $this->instrucciones($banco, $modalidad)],
            ],
        ]];

        try {
            // Extracción, no razonamiento largo: esfuerzo bajo alcanza y cuesta
            // una fracción. Los modelos más chicos no aceptan ese parámetro, así
            // que si lo rechazan se reintenta sin él en vez de mantener aquí una
            // lista de modelos que envejece con cada lanzamiento.
            $respuesta = $this->pedir($modelo, $mensajes, conEsfuerzo: true);
        } catch (Throwable $e) {
            if (! str_contains($e->getMessage(), 'effort')) {
                throw $this->traducir($e, $modelo);
            }

            try {
                $respuesta = $this->pedir($modelo, $mensajes, conEsfuerzo: false);
            } catch (Throwable $e2) {
                throw $this->traducir($e2, $modelo);
            }
        }

        $leido = $this->interpretar($respuesta, $modelo);

        // Si el operador ya fijó el formato, manda la pista; lo identificado
        // solo cuenta cuando no había pista.
        if (BancosVoucher::esComboValido($banco, $modalidad)) {
            $leido['banco'] = $banco;
            $leido['modalidad'] = $modalidad;
        }

        return $leido;
    }

    /** El detalle técnico va al log; al operador le llega algo accionable. */
    private function traducir(Throwable $e, string $modelo): VoucherIlegible
    {
        Log::warning('Lectura de voucher falló', [
            'modelo' => $modelo,
            'clase' => $e::class,
            'error' => $e->getMessage(),
        ]);

        return new VoucherIlegible(MensajeDeFallo::para($e), 0, $e);
    }

    /** @param  list<array<string, mixed>>  $mensajes */
    private function pedir(string $modelo, array $mensajes, bool $conEsfuerzo): object
    {
        $salida = ['format' => self::ESQUEMA];
        if ($conEsfuerzo) {
            $salida['effort'] = 'low';
        }

        return $this->cliente()->messages->create(
            model: $modelo,
            maxTokens: 4000,
            outputConfig: $salida,
            messages: $mensajes,
        );
    }

    private function cliente(): Client
    {
        if ($this->cliente instanceof Client) {
            return $this->cliente;
        }

        $clave = (string) config('services.anthropic.key');
        if ($clave === '') {
            throw new VoucherIlegible('La lectura automática no está configurada (falta ANTHROPIC_API_KEY).');
        }

        return $this->cliente = new Client(apiKey: $clave);
    }

    /** @return array{tipo: string, datos: string} */
    private function leerImagen(string $ruta): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new VoucherIlegible('No se encuentra la imagen del voucher.');
        }

        $bytes = filesize($ruta);
        if ($bytes === false || $bytes === 0) {
            throw new VoucherIlegible('La imagen del voucher está vacía.');
        }
        if ($bytes > self::MAX_BYTES) {
            throw new VoucherIlegible('La imagen del voucher pesa más de 5 MB: vuelve a subirla más liviana.');
        }

        // El tipo se deduce del CONTENIDO, no de la extensión: el archivo que
        // Livewire deja mientras se sube no conserva la extensión original.
        $tipo = @mime_content_type($ruta) ?: '';
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new VoucherIlegible('El archivo no es una imagen soportada ('.($tipo ?: 'desconocido').'). Usa JPG, PNG o WEBP.');
        }

        $datos = @file_get_contents($ruta);
        if ($datos === false) {
            throw new VoucherIlegible('No se pudo abrir la imagen del voucher.');
        }

        return ['tipo' => $tipo, 'datos' => base64_encode($datos)];
    }

    /** Las instrucciones llevan el formato esperado y, si existe, su checklist. */
    private function instrucciones(string $banco, string $modalidad): string
    {
        $conPista = BancosVoucher::esComboValido($banco, $modalidad);

        if ($conPista) {
            // El operador fijó el formato: se le dice cuál es y qué no debe faltar.
            $bloqueFormato = 'FORMATO ESPERADO: '.BancosVoucher::titulo($banco, $modalidad);
            $obligatorios = collect(BancosVoucher::campos($banco, $modalidad))
                ->filter(fn (array $c) => $c[1])->map(fn (array $c) => $c[0])->implode(', ');
            if ($obligatorios !== '') {
                $bloqueFormato .= "\nEn este formato no debería faltar: {$obligatorios}.";
            }
            $bloqueFormato .= "\nEn \"banco\" y \"modalidad\" devuelve exactamente: {$banco} y {$modalidad}.";
        } else {
            // 18/09: sin pista, la lectura identifica el formato entre los del
            // catálogo (los 15 maestros del área). Se le dan las claves con su
            // descripción y se le pide la clave, no el nombre.
            // Cada opción lleva los datos que la caracterizan (los del catálogo):
            // es lo que separa variantes parecidas del mismo banco, como la
            // transferencia común de la interbancaria del BCP (probado 18/09:
            // sin esto, 13 de 15 maestros exactos; el banco, 15 de 15).
            $opciones = collect(BancosVoucher::combosDisponibles())
                ->flatMap(fn (array $mods, string $bco) => collect($mods)->map(function (string $mod) use ($bco) {
                    $datos = collect(BancosVoucher::campos($bco, $mod))->map(fn (array $c) => $c[0])->implode(', ');

                    return "  - banco \"{$bco}\", modalidad \"{$mod}\": ".BancosVoucher::titulo($bco, $mod)." (muestra: {$datos})";
                }))->implode("\n");
            $bloqueFormato = "FORMATO: identifícalo tú. Estas son las opciones que existen (clave de banco, clave de modalidad, descripción y qué datos muestra ese tipo de comprobante):\n"
                .$opciones
                ."\nEn \"banco\" y \"modalidad\" devuelve las CLAVES de la opción que mejor coincida con el comprobante: primero el banco (logo o nombre) y luego la modalidad por el tipo de operación y los datos que aparecen. Si no coincide con ninguna, deja las dos vacías.";
        }

        return <<<TXT
        Transcribes comprobantes bancarios para una constancia legal que se FIRMA, así que cada dígito importa más que la velocidad.

        {$bloqueFormato}

        Devuelve:

        1. "transcripcion": el texto del voucher en el MISMO ORDEN en que aparece, separando cada dato con "; ". LITERAL: respeta mayúsculas, símbolos (S/), asteriscos de enmascarado (****2097), guiones y puntuación tal como se ven; no corrijas los errores del banco ni normalices las fechas. SÁLTATE el ruido que no describe la operación: RUC del banco, comisiones en cero, códigos internos de auditoría, publicidad, "escaneado con...". No inventes nada: si un dato no se lee, escribe [ilegible]. No agregues punto final.
        2. "monto": el importe de la operación tal como aparece, sin el símbolo de moneda (ej. "10,000.00"). Si el voucher distingue importe abonado del pagado, devuelve el ABONADO: el ITF no es parte del desembolso.
        3. "beneficiario": a quién se le envió o depositó, tal como figura.
        4. "dudas": los dígitos o palabras que NO distingues con certeza y por qué (ej. "el 3er dígito de la operación podría ser 6 u 8: el punteado está borroso"). Vacío si no tienes ninguna. Sé honesto: declarar la duda es más útil que adivinar, porque quien revisa mira justo ahí.
        5. "banco" y "modalidad": las claves indicadas arriba.

        ESTILO DE LA TRANSCRIPCIÓN — así se hace en el área (ejemplo con datos inventados):

            ¡TRANSFERENCIA EXITOSA!; S/9,999.00; LUNES, 01 ENERO 2026 - 08:00 a.m.; ENVIADO A APELLIDO NOMBRE X.; ****1111; MONEDA SOLES; DESDE CUENTAS DE AHORRO; ****2222; NÚMERO DE OPERACIÓN 00000000; MENSAJE: GARANTIA VEHICULAR

        Fíjate en tres cosas de ese ejemplo:
        - La etiqueta va JUNTO a su valor en un mismo elemento ("ENVIADO A APELLIDO NOMBRE X.", "NÚMERO DE OPERACIÓN 00000000"), no separadas por punto y coma.
        - El nombre del banco o su logo suelto NO se transcribe: ya consta en el documento.
        - Cada elemento describe un dato de la operación. Nada de encabezados vacíos.
        TXT;
    }

    /** @return array{transcripcion: string, monto: string, beneficiario: string, dudas: string, modelo: string} */
    private function interpretar(object $respuesta, string $modelo): array
    {
        // Las clasificaciones de seguridad pueden declinar: hay que mirar
        // stop_reason antes de leer el contenido.
        if (($respuesta->stopReason ?? null) === 'refusal') {
            throw new VoucherIlegible('El servicio rechazó la lectura de esta imagen.');
        }

        $json = null;
        foreach ($respuesta->content ?? [] as $bloque) {
            if (($bloque->type ?? null) === 'text') {
                $json = json_decode((string) $bloque->text, true);
                break;
            }
        }

        if (! is_array($json) || ! isset($json['transcripcion'])) {
            throw new VoucherIlegible('La respuesta de la lectura no se pudo interpretar.');
        }

        $limpio = fn (string $clave) => trim((string) ($json[$clave] ?? ''));

        // Solo se acepta un combo del catálogo; cualquier otra cosa es "no
        // identificado" y el operador lo elige a mano.
        $banco = mb_strtolower($limpio('banco'));
        $modalidad = mb_strtolower($limpio('modalidad'));
        if (! BancosVoucher::esComboValido($banco, $modalidad)) {
            $banco = '';
            $modalidad = '';
        }

        return [
            'transcripcion' => rtrim($limpio('transcripcion'), ' .;'),
            'monto' => $limpio('monto'),
            'beneficiario' => $limpio('beneficiario'),
            'dudas' => $limpio('dudas'),
            'banco' => $banco,
            'modalidad' => $modalidad,
            'modelo' => $modelo,
        ];
    }
}
