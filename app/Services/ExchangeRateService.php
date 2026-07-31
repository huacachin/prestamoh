<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tipo de cambio SUNAT (via api.migo.pe).
 *
 * Resuelve en este orden: caché → base de datos → API. Lo que llega de la API
 * se guarda en ambas, así que la consulta externa ocurre como mucho UNA VEZ
 * por día, aunque se abra la pantalla cien veces.
 */
class ExchangeRateService
{
    /** Origen del dato devuelto, para poder mostrarlo en pantalla. */
    public const DE_CACHE = 'cache';

    public const DE_BD = 'bd';

    public const DE_SUNAT = 'sunat';

    /** No se pudo consultar: se devuelve el último conocido, que puede ser viejo. */
    public const DE_BD_ANTERIOR = 'bd-anterior';

    /**
     * Tipo de cambio de una fecha (hoy si no se indica otra).
     *
     * @param  bool  $forzar  Salta caché y base de datos, y vuelve a preguntar a SUNAT.
     * @return array{fecha:string,compra:string,venta:string,origen:string}|null
     */
    public function delDia(?string $fecha = null, bool $forzar = false): ?array
    {
        $fecha = $fecha ?: now()->format('Y-m-d');

        if (! $forzar) {
            if ($cacheado = Cache::get($this->clave($fecha))) {
                return $cacheado + ['origen' => self::DE_CACHE];
            }

            if ($fila = ExchangeRate::whereDate('fecha', $fecha)->first()) {
                $datos = $this->aArray($fila);
                $this->recordar($fecha, $datos);

                return $datos + ['origen' => self::DE_BD];
            }
        }

        $deSunat = $this->consultarSunat($fecha);

        if ($deSunat === null) {
            // Sin respuesta de SUNAT: mejor el último conocido que nada, pero
            // NO se cachea — así el siguiente intento vuelve a probar.
            $ultimo = ExchangeRate::orderByDesc('fecha')->first();

            return $ultimo ? $this->aArray($ultimo) + ['origen' => self::DE_BD_ANTERIOR] : null;
        }

        // SUNAT publica por fecha; en fines de semana y feriados devuelve la
        // última publicada, que puede no coincidir con la pedida.
        $fila = ExchangeRate::updateOrCreate(
            ['fecha' => $deSunat['fecha']],
            ['compra' => $deSunat['compra'], 'venta' => $deSunat['venta']],
        );

        $datos = $this->aArray($fila);
        $this->recordar($fecha, $datos);

        return $datos + ['origen' => self::DE_SUNAT];
    }

    /** Invalida la caché de una fecha. Se usa tras guardar a mano. */
    public function olvidar(?string $fecha = null): void
    {
        Cache::forget($this->clave($fecha ?: now()->format('Y-m-d')));
    }

    /** Deja en caché un valor ya conocido, sin consultar nada. */
    public function recordar(string $fecha, array $datos): void
    {
        // Hasta el final del día: SUNAT publica un tipo de cambio diario.
        Cache::put($this->clave($fecha), [
            'fecha' => $datos['fecha'],
            'compra' => $datos['compra'],
            'venta' => $datos['venta'],
        ], now()->endOfDay());
    }

    private function clave(string $fecha): string
    {
        return "tc:sunat:{$fecha}";
    }

    private function aArray(ExchangeRate $fila): array
    {
        return [
            'fecha' => $fila->fecha->format('Y-m-d'),
            'compra' => (string) $fila->compra,
            'venta' => (string) $fila->venta,
        ];
    }

    /**
     * @return array{fecha:string,compra:string,venta:string}|null
     */
    private function consultarSunat(string $fecha): ?array
    {
        $token = config('services.migo.token');
        $base = rtrim((string) config('services.migo.base'), '/');

        if (! $token) {
            Log::warning('Tipo de cambio: falta MIGO_PE_TOKEN');

            return null;
        }

        // Se pide la fecha exacta; si ese día no hay publicación (fin de semana
        // o feriado) se recurre a la última disponible.
        foreach ([['/exchange/date', ['fecha' => $fecha]], ['/exchange/latest', []]] as [$ruta, $extra]) {
            try {
                $resp = Http::timeout(8)->acceptJson()->asJson()
                    ->post($base.$ruta, ['token' => $token] + $extra);

                if (! $resp->ok()) {
                    continue;
                }

                $j = $resp->json();
                if (empty($j) || (isset($j['success']) && $j['success'] === false)) {
                    continue;
                }
                if (! isset($j['precio_compra'], $j['precio_venta'])) {
                    continue;
                }

                return [
                    'fecha' => (string) ($j['fecha'] ?? $fecha),
                    'compra' => (string) $j['precio_compra'],
                    'venta' => (string) $j['precio_venta'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Tipo de cambio: fallo consultando SUNAT', ['ruta' => $ruta, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }
}
