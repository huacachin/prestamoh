<?php

declare(strict_types=1);

namespace App\Services\Printing;

use Mike42\Escpos\PrintConnectors\PrintConnector;
use RuntimeException;

/**
 * CUPS connector que envía cada job con `lp -o raw` para evitar que
 * macOS pase los bytes ESC/POS por el filtro PostScript del driver.
 */
final class CupsRawPrintConnector implements PrintConnector
{
    /** @var list<string> */
    private array $buffer = [];

    private bool $finalized = false;

    public function __construct(private readonly string $printerName) {}

    public function __destruct()
    {
        if (! $this->finalized) {
            trigger_error('Print connector was not finalized.', E_USER_NOTICE);
        }
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
        $data = implode('', $this->buffer);
        $this->buffer = [];
        $this->finalized = true;

        $tmp = tempnam(sys_get_temp_dir(), 'escpos-');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para impresión.');
        }

        file_put_contents($tmp, $data);

        // Ruta absoluta: php artisan serve no hereda el PATH del shell, y `lp`
        // queda fuera de búsqueda. macOS y la mayoría de Linux lo tienen en /usr/bin/lp.
        $lp = is_executable('/usr/bin/lp') ? '/usr/bin/lp'
            : (is_executable('/usr/local/bin/lp') ? '/usr/local/bin/lp' : 'lp');

        $cmd = sprintf(
            '%s -o raw -d %s %s 2>&1',
            $lp,
            escapeshellarg($this->printerName),
            escapeshellarg($tmp),
        );

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        @unlink($tmp);

        if ($exit !== 0) {
            throw new RuntimeException(
                "lp falló (exit {$exit}): " . implode("\n", $output),
            );
        }
    }
}
