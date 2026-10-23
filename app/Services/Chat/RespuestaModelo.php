<?php

namespace App\Services\Chat;

/**
 * Lo que devuelve el modelo en una vuelta del chat (23/10/2026): los bloques
 * tal como los manda la API (texto y pedidos de herramienta), por qué paró y
 * cuántos tokens cobró. Es un objeto propio para que los tests inyecten
 * respuestas guionadas sin construir objetos del SDK.
 */
final class RespuestaModelo
{
    /**
     * @param  list<array{type: string, text?: string, id?: string, name?: string, input?: array}>  $bloques
     */
    public function __construct(
        public readonly string $modelo,
        public readonly string $motivoParada,
        public readonly array $bloques,
        public readonly int $tokensEntrada = 0,
        public readonly int $tokensSalida = 0,
        public readonly int $tokensCacheLectura = 0,
        public readonly int $tokensCacheEscritura = 0,
    ) {}

    /** Texto de la respuesta (todos los bloques de texto, en orden). */
    public function texto(): string
    {
        $partes = [];
        foreach ($this->bloques as $bloque) {
            if (($bloque['type'] ?? '') === 'text' && trim((string) ($bloque['text'] ?? '')) !== '') {
                $partes[] = trim((string) $bloque['text']);
            }
        }

        return implode("\n\n", $partes);
    }

    /**
     * Herramientas que el modelo pide correr en esta vuelta.
     *
     * @return list<array{id: string, name: string, input: array}>
     */
    public function usosDeHerramienta(): array
    {
        $usos = [];
        foreach ($this->bloques as $bloque) {
            if (($bloque['type'] ?? '') === 'tool_use') {
                $usos[] = [
                    'id' => (string) ($bloque['id'] ?? ''),
                    'name' => (string) ($bloque['name'] ?? ''),
                    'input' => is_array($bloque['input'] ?? null) ? $bloque['input'] : [],
                ];
            }
        }

        return $usos;
    }

    public function pideHerramientas(): bool
    {
        return $this->motivoParada === 'tool_use' && $this->usosDeHerramienta() !== [];
    }

    /**
     * Los bloques en la forma que la API acepta de vuelta como turno del
     * asistente (sin textos vacíos; un input vacío va como objeto, no lista).
     */
    public function contenidoParaHistorial(): array
    {
        $contenido = [];
        foreach ($this->bloques as $bloque) {
            if (in_array($bloque['type'] ?? '', ['thinking', 'redacted_thinking'], true)) {
                // Se devuelven sin tocar (firma incluida): la API los exige en el turno que pidió herramientas.
                $contenido[] = $bloque;
            } elseif (($bloque['type'] ?? '') === 'text') {
                if (trim((string) ($bloque['text'] ?? '')) !== '') {
                    $contenido[] = ['type' => 'text', 'text' => (string) $bloque['text']];
                }
            } elseif (($bloque['type'] ?? '') === 'tool_use') {
                $input = is_array($bloque['input'] ?? null) ? $bloque['input'] : [];
                $contenido[] = [
                    'type' => 'tool_use',
                    'id' => (string) ($bloque['id'] ?? ''),
                    'name' => (string) ($bloque['name'] ?? ''),
                    'input' => $input === [] ? new \stdClass : $input,
                ];
            }
        }

        return $contenido;
    }
}
