<?php

namespace App\Services\Chat\Herramientas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Las herramientas que el chat de consulta ofrece al modelo (23/10/2026) y
 * el único punto por donde se ejecutan: siempre dentro de la conexión de
 * lectura (config chat.conexion) y con los errores convertidos en un
 * resultado que el modelo puede explicar, nunca en una excepción.
 */
final class Catalogo
{
    /** @var array<string, Herramienta> */
    private array $herramientas = [];

    public function __construct()
    {
        foreach ([
            new BuscarClientes,
            new FichaCliente,
            new DetalleCredito,
            new CronogramaCredito,
            new PagosCredito,
            new DocumentosCliente,
        ] as $herramienta) {
            $this->herramientas[$herramienta->nombre()] = $herramienta;
        }
    }

    /** @return list<string> */
    public function nombres(): array
    {
        return array_keys($this->herramientas);
    }

    /**
     * Definiciones en el formato de la API (name, description, input_schema).
     *
     * @return list<array{name: string, description: string, input_schema: array}>
     */
    public function definiciones(): array
    {
        return array_values(array_map(fn (Herramienta $h) => [
            'name' => $h->nombre(),
            'description' => $h->descripcion(),
            'input_schema' => $h->esquema(),
        ], $this->herramientas));
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array{ok: bool, datos?: array, error?: string}
     */
    public function ejecutar(string $nombre, array $argumentos): array
    {
        $herramienta = $this->herramientas[$nombre] ?? null;
        if ($herramienta === null) {
            return ['ok' => false, 'error' => "No existe la consulta '{$nombre}'. Disponibles: ".implode(', ', $this->nombres()).'.'];
        }

        try {
            $datos = DB::usingConnection((string) config('chat.conexion', 'lectura'), fn () => $herramienta->ejecutar($argumentos));

            return ['ok' => true, 'datos' => $datos];
        } catch (Throwable $e) {
            // El detalle (SQL, conexión, usuario) se queda en el log: al modelo y a la pantalla va un texto fijo.
            Log::warning('Herramienta del chat falló', ['herramienta' => $nombre, 'argumentos' => $argumentos, 'clase' => $e::class, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => "La consulta '{$nombre}' falló por un error interno del sistema; el detalle quedó en el registro. Avisa a sistemas si se repite."];
        }
    }
}
