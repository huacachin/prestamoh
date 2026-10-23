<?php

namespace App\Services\Chat;

/**
 * Lo único que el chat necesita del modelo de lenguaje (23/10/2026): una
 * vuelta de conversación con herramientas disponibles. Se resuelve por
 * interfaz (AppServiceProvider) para que los tests inyecten un doble y no
 * gasten llamadas a la API.
 */
interface ModeloDeChat
{
    /**
     * @param  list<array{role: string, content: string|array}>  $mensajes
     * @param  list<array{name: string, description: string, input_schema: array}>  $herramientas
     *
     * @throws \Throwable si la API falla (sin saldo, clave, red…)
     */
    public function responder(string $sistema, array $mensajes, array $herramientas): RespuestaModelo;
}
