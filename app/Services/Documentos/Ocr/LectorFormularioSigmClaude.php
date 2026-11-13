<?php

namespace App\Services\Documentos\Ocr;

use Anthropic\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lee el "Formulario de constitución" del SIGM con la API de Claude (Trello #137, 13/11/2026): el área
 * pega la captura y se llenan N° de formulario, folio causal electrónico y fecha de inscripción.
 *
 * Mismo criterio que LectorVoucherClaude: mismo modelo (services.anthropic.modelo), esfuerzo bajo
 * porque es extracción, y NUNCA registra sola: los datos van al formulario para que la persona los
 * revise y pulse "Registrar constitución". "dudas" señala lo que no se distinguió con certeza.
 */
class LectorFormularioSigmClaude implements LectorDeFormularioSigm
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
                'formulario' => ['type' => 'string'],
                'folio' => ['type' => 'string'],
                'fecha' => ['type' => 'string'],
                'documentos' => ['type' => 'array', 'items' => ['type' => 'string']],
                'es_constitucion' => ['type' => 'boolean'],
                'dudas' => ['type' => 'string'],
            ],
            'required' => ['formulario', 'folio', 'fecha', 'documentos', 'es_constitucion', 'dudas'],
            'additionalProperties' => false,
        ],
    ];

    private const INSTRUCCIONES = <<<'TXT'
    La imagen es (o debería ser) el "FORMULARIO DE CONSTITUCIÓN" de una garantía mobiliaria del SIGM (Sistema Informativo de Garantías Mobiliarias de SUNARP, Perú). Esos datos se registran en un sistema legal, así que cada dígito importa.

    Devuelve:
    1. "formulario": el "Número de formulario", tal como aparece (ej. "2026-928139").
    2. "folio": el "Folio Causal electrónico", solo los dígitos (ej. "20261107002358210").
    3. "fecha": la fecha de "Fecha y Hora de la Inscripción" en formato DD/MM/AAAA, sin la hora (ej. "07/11/2026"). NO uses la "fecha y hora de visualización" del encabezado: esa es la de impresión, no la de inscripción.
    4. "documentos": los números de documento (DNI o RUC) de los DEUDORES que aparezcan en la imagen, solo dígitos. Lista vacía si no se ven.
    5. "es_constitucion": true si el título del formulario es "FORMULARIO DE CONSTITUCIÓN"; false si es otro (cancelación, modificación, ejecución…) o si la imagen no es un formulario del SIGM.
    6. "dudas": los dígitos o datos que NO distingues con certeza y por qué. Vacío si no tienes ninguna. Declarar la duda es más útil que adivinar.

    Si un dato no aparece o no se lee, devuélvelo vacío: no lo inventes.
    TXT;

    public function __construct(private ?Client $cliente = null) {}

    public function leer(string $rutaAbsoluta): array
    {
        $imagen = $this->leerImagen($rutaAbsoluta);
        $modelo = (string) config('services.anthropic.modelo');

        $mensajes = [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $imagen['tipo'], 'data' => $imagen['datos']]],
                ['type' => 'text', 'text' => self::INSTRUCCIONES],
            ],
        ]];

        try {
            // Como en el voucher: si el modelo configurado no acepta el esfuerzo, se reintenta sin él.
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

        return $this->interpretar($respuesta, $modelo);
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
            maxTokens: 2000,
            outputConfig: $salida,
            messages: $mensajes,
        );
    }

    private function traducir(Throwable $e, string $modelo): FormularioSigmIlegible
    {
        Log::warning('Lectura del formulario SIGM falló', ['modelo' => $modelo, 'clase' => $e::class, 'error' => $e->getMessage()]);

        return new FormularioSigmIlegible(MensajeDeFallo::para($e, 'el formulario'), 0, $e);
    }

    private function cliente(): Client
    {
        if ($this->cliente instanceof Client) {
            return $this->cliente;
        }

        $clave = (string) config('services.anthropic.key');
        if ($clave === '') {
            throw new FormularioSigmIlegible('La lectura automática no está configurada (falta ANTHROPIC_API_KEY).');
        }

        $workspace = trim((string) config('services.anthropic.workspace'));
        $opciones = $workspace !== '' ? ['extraHeaders' => ['anthropic-workspace-id' => $workspace]] : null;

        return $this->cliente = new Client(apiKey: $clave, requestOptions: $opciones);
    }

    /** @return array{tipo: string, datos: string} */
    private function leerImagen(string $ruta): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new FormularioSigmIlegible('No se encuentra la imagen del formulario.');
        }
        $bytes = filesize($ruta);
        if ($bytes === false || $bytes === 0) {
            throw new FormularioSigmIlegible('La imagen del formulario está vacía.');
        }
        if ($bytes > self::MAX_BYTES) {
            throw new FormularioSigmIlegible('La imagen pesa más de 5 MB: recorta la captura al formulario.');
        }
        // Por el contenido, no por la extensión: el temporal de Livewire no la conserva.
        $tipo = @mime_content_type($ruta) ?: '';
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new FormularioSigmIlegible('El archivo no es una imagen soportada ('.($tipo ?: 'desconocido').'). Usa una captura PNG o JPG.');
        }
        $datos = @file_get_contents($ruta);
        if ($datos === false) {
            throw new FormularioSigmIlegible('No se pudo abrir la imagen del formulario.');
        }

        return ['tipo' => $tipo, 'datos' => base64_encode($datos)];
    }

    /** @return array{formulario: string, folio: string, fecha: string, documentos: list<string>, esConstitucion: bool, dudas: string, modelo: string} */
    private function interpretar(object $respuesta, string $modelo): array
    {
        if (($respuesta->stopReason ?? null) === 'refusal') {
            throw new FormularioSigmIlegible('El servicio rechazó la lectura de esta imagen.');
        }

        $json = null;
        foreach ($respuesta->content ?? [] as $bloque) {
            if (($bloque->type ?? null) === 'text') {
                $json = json_decode((string) $bloque->text, true);
                break;
            }
        }
        if (! is_array($json) || ! array_key_exists('formulario', $json)) {
            throw new FormularioSigmIlegible('La respuesta de la lectura no se pudo interpretar.');
        }

        return [
            'formulario' => preg_replace('/\s+/', '', (string) ($json['formulario'] ?? '')),
            'folio' => preg_replace('/\D+/', '', (string) ($json['folio'] ?? '')),
            'fecha' => self::fecha((string) ($json['fecha'] ?? '')),
            'documentos' => array_values(array_filter(array_map(
                fn ($d) => preg_replace('/\D+/', '', (string) $d), (array) ($json['documentos'] ?? [])
            ))),
            'esConstitucion' => (bool) ($json['es_constitucion'] ?? false),
            'dudas' => trim((string) ($json['dudas'] ?? '')),
            'modelo' => $modelo,
        ];
    }

    /** "07/11/2026" (con o sin hora) → "2026-11-07"; lo que no sea una fecha válida, vacío. */
    private static function fecha(string $texto): string
    {
        if (! preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $texto, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return '';
        }

        return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
    }
}
