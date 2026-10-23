<?php

namespace App\Services\Chat\Herramientas;

/**
 * Una consulta de SOLO LECTURA que el modelo puede pedir (23/10/2026). Cada
 * herramienta declara su nombre, qué hace (el modelo decide con eso cuándo
 * usarla) y el esquema JSON de sus argumentos, y devuelve un array listo para
 * mandarse como JSON. Corren dentro de la conexión config('chat.conexion').
 */
interface Herramienta
{
    public function nombre(): string;

    public function descripcion(): string;

    /** Esquema JSON (type object) de los argumentos. */
    public function esquema(): array;

    /**
     * @param  array<string, mixed>  $argumentos
     */
    public function ejecutar(array $argumentos): array;
}
