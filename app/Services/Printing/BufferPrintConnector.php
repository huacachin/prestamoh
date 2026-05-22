<?php

declare(strict_types=1);

namespace App\Services\Printing;

use Mike42\Escpos\PrintConnectors\PrintConnector;

/**
 * Acumula los bytes ESC/POS en memoria para luego enviarlos por WebSocket
 * o serializarlos a la BD (en vez de mandarlos directo a una impresora).
 */
final class BufferPrintConnector implements PrintConnector
{
    /** @var list<string> */
    private array $buffer = [];

    private bool $finalized = false;

    public function __destruct()
    {
        // Sin warnings — este connector se finaliza explícitamente.
    }

    public function write($data): void
    {
        $this->buffer[] = $data;
    }

    public function read($len): string|false
    {
        return false;
    }

    public function finalize(): void
    {
        $this->finalized = true;
    }

    public function getBytes(): string
    {
        return implode('', $this->buffer);
    }
}
