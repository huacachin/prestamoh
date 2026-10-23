<?php

namespace App\Services\Chat;

use Anthropic\Client;
use GuzzleHttp\Client as Guzzle;
use RuntimeException;
use Throwable;

/**
 * Implementación real de ModeloDeChat con el SDK oficial de Anthropic
 * (23/10/2026), igual que el OCR del voucher (LectorVoucherClaude). Manda las
 * instrucciones y las herramientas con caché de prompt (se repiten en cada
 * pregunta) y devuelve los bloques y el uso de tokens en un objeto propio.
 * Los bloques de razonamiento (thinking) se conservan tal cual para poder
 * devolverlos en el turno siguiente, como exige la API.
 */
final class ClienteClaude implements ModeloDeChat
{
    public function __construct(private ?Client $cliente = null) {}

    public function responder(string $sistema, array $mensajes, array $herramientas): RespuestaModelo
    {
        $modelo = (string) config('chat.modelo');
        $definiciones = $herramientas;
        if ($definiciones !== []) {
            $definiciones[count($definiciones) - 1]['cache_control'] = ['type' => 'ephemeral'];
        }
        $esfuerzo = trim((string) config('chat.esfuerzo'));

        try {
            $respuesta = $this->pedir($modelo, $sistema, $mensajes, $definiciones, $esfuerzo);
        } catch (Throwable $e) {
            // Igual que el OCR: si el modelo no acepta el parámetro de esfuerzo, se repite sin él.
            if ($esfuerzo === '' || ! str_contains($e->getMessage(), 'effort')) {
                throw $e;
            }
            $respuesta = $this->pedir($modelo, $sistema, $mensajes, $definiciones, '');
        }

        $bloques = [];
        foreach ($respuesta->content ?? [] as $bloque) {
            $tipo = (string) ($bloque->type ?? '');
            if ($tipo === 'text') {
                $bloques[] = ['type' => 'text', 'text' => (string) ($bloque->text ?? '')];
            } elseif ($tipo === 'tool_use') {
                $input = $bloque->input ?? [];
                $bloques[] = [
                    'type' => 'tool_use',
                    'id' => (string) ($bloque->id ?? ''),
                    'name' => (string) ($bloque->name ?? ''),
                    'input' => is_array($input) ? $input : (array) $input,
                ];
            } elseif ($tipo === 'thinking') {
                $bloques[] = ['type' => 'thinking', 'thinking' => (string) ($bloque->thinking ?? ''), 'signature' => (string) ($bloque->signature ?? '')];
            } elseif ($tipo === 'redacted_thinking') {
                $bloques[] = ['type' => 'redacted_thinking', 'data' => (string) ($bloque->data ?? '')];
            }
        }

        $uso = $respuesta->usage ?? null;

        return new RespuestaModelo(
            modelo: (string) ($respuesta->model ?? $modelo),
            motivoParada: (string) ($respuesta->stopReason ?? 'end_turn'),
            bloques: $bloques,
            tokensEntrada: (int) ($uso->inputTokens ?? 0),
            tokensSalida: (int) ($uso->outputTokens ?? 0),
            tokensCacheLectura: (int) ($uso->cacheReadInputTokens ?? 0),
            tokensCacheEscritura: (int) ($uso->cacheCreationInputTokens ?? 0),
        );
    }

    private function pedir(string $modelo, string $sistema, array $mensajes, array $definiciones, string $esfuerzo): object
    {
        return $this->cliente()->messages->create(
            model: $modelo,
            maxTokens: (int) config('chat.max_tokens_respuesta', 4000),
            system: [['type' => 'text', 'text' => $sistema, 'cache_control' => ['type' => 'ephemeral']]],
            messages: $mensajes,
            tools: $definiciones === [] ? null : $definiciones,
            outputConfig: $esfuerzo === '' ? null : ['effort' => $esfuerzo],
        );
    }

    private function cliente(): Client
    {
        if ($this->cliente !== null) {
            return $this->cliente;
        }
        $clave = (string) config('chat.api_key');
        if ($clave === '') {
            throw new RuntimeException('El chat no está configurado (falta CHAT_API_KEY).');
        }
        $timeout = max(10, (int) config('chat.timeout', 60));
        $opciones = [
            // El SDK no aplica su timeout: lo pone el transporte. Un solo reintento
            // (el SDK trae 2) para que una API colgada no bloquee 3 × timeout.
            'transporter' => new Guzzle(['timeout' => $timeout, 'connect_timeout' => 10]),
            'maxRetries' => 1,
        ];
        $workspace = trim((string) config('chat.workspace'));
        if ($workspace !== '') {
            $opciones['extraHeaders'] = ['anthropic-workspace-id' => $workspace];
        }

        return $this->cliente = new Client(apiKey: $clave, requestOptions: $opciones);
    }
}
