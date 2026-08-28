<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta de documentos y placas contra api.factiliza.com (reemplaza a
 * migo.pe, 28/08). Token Bearer desde config('services.factiliza.token').
 *
 * Ventaja sobre lo anterior: la API devuelve los nombres YA SEPARADOS
 * (nombres / apellido_paterno / apellido_materno), así que desaparece el
 * split posicional que rompía con apellidos compuestos ("DE LA CRUZ ROJAS
 * JUAN" quedaba como pat="De", mat="La").
 *
 * Todos los métodos devuelven el mismo contrato:
 *   ['ok' => bool, 'data' => array|null, 'error' => string|null]
 */
class Factiliza
{
    /** Respuestas cacheadas 24h: el mismo DNI consultado dos veces no gasta crédito. */
    private const CACHE_TTL = 86400;

    private const TIMEOUT = 10;

    public function dni(string $numero): array
    {
        return $this->consultar('dni', $numero, fn (array $d) => [
            'nombre' => $this->limpiar($d['nombres'] ?? ''),
            'apellido_pat' => $this->limpiar($d['apellido_paterno'] ?? ''),
            'apellido_mat' => $this->limpiar($d['apellido_materno'] ?? ''),
            'direccion' => $this->limpiar($d['direccion'] ?? ''),
            'distrito' => $this->limpiar($d['distrito'] ?? ''),
            'provincia' => $this->limpiar($d['provincia'] ?? ''),
            'departamento' => $this->limpiar($d['departamento'] ?? ''),
            'fecha_nacimiento' => $this->fecha($d['fecha_nacimiento'] ?? null),
            'sexo' => $this->sexo($d['sexo'] ?? null),
        ]);
    }

    /** Carné de extranjería: mismos campos de nombre que DNI, sin domicilio. */
    public function cee(string $numero): array
    {
        return $this->consultar('cee', $numero, fn (array $d) => [
            'nombre' => $this->limpiar($d['nombres'] ?? ''),
            'apellido_pat' => $this->limpiar($d['apellido_paterno'] ?? ''),
            'apellido_mat' => $this->limpiar($d['apellido_materno'] ?? ''),
        ]);
    }

    /** RUC: la razón social entera va al campo `nombre` (regla de negocio previa). */
    public function ruc(string $numero): array
    {
        return $this->consultar('ruc', $numero, fn (array $d) => [
            'nombre' => trim($this->reparar((string) ($d['nombre_o_razon_social'] ?? ''))),
            'direccion' => $this->limpiar($d['direccion'] ?? ''),
            'distrito' => $this->limpiar($d['distrito'] ?? ''),
            'provincia' => $this->limpiar($d['provincia'] ?? ''),
            'departamento' => $this->limpiar($d['departamento'] ?? ''),
            'estado' => $this->limpiar($d['estado'] ?? ''),
            'condicion' => $this->limpiar($d['condicion'] ?? ''),
        ]);
    }

    public function placa(string $placa): array
    {
        return $this->consultar('placa', strtoupper(trim($placa)), fn (array $d) => [
            'placa' => strtoupper($this->limpiar($d['placa'] ?? '')),
            'marca' => $this->limpiar($d['marca'] ?? ''),
            'modelo' => $this->limpiar($d['modelo'] ?? ''),
            'nro_motor' => strtoupper($this->limpiar($d['motor'] ?? '')),
            'nro_serie' => strtoupper($this->limpiar($d['serie'] ?? $d['vin'] ?? '')),
            'color' => $this->limpiar($d['color'] ?? ''),
        ]);
    }

    /**
     * @param  callable(array): array  $mapear  normaliza el `data` de la API a nuestros campos
     */
    private function consultar(string $recurso, string $valor, callable $mapear): array
    {
        $token = (string) config('services.factiliza.token');
        if ($token === '') {
            return $this->error('Falta FACTILIZA_TOKEN en el .env.');
        }

        $valor = trim($valor);
        if ($valor === '') {
            return $this->error('Indica el número a consultar.');
        }

        $cacheKey = "factiliza:{$recurso}:".strtoupper($valor);
        if ($hit = Cache::get($cacheKey)) {
            return ['ok' => true, 'data' => $mapear($hit), 'error' => null];
        }

        try {
            $base = rtrim((string) config('services.factiliza.base'), '/');
            $resp = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(self::TIMEOUT)
                ->get("{$base}/{$recurso}/info/".rawurlencode($valor));
        } catch (\Throwable $e) {
            Log::warning('Factiliza: excepción de red', ['recurso' => $recurso, 'error' => $e->getMessage()]);

            return $this->error('No se pudo conectar con el servicio de consulta. Ingresa los datos a mano.');
        }

        $json = $resp->json();

        if (! $resp->successful() || ! is_array($json) || ($json['success'] ?? false) !== true || empty($json['data'])) {
            $msg = is_array($json) ? (string) ($json['message'] ?? '') : '';
            if ($resp->status() === 401) {
                $msg = 'El token de consulta no es válido o expiró.';
            }
            Log::warning('Factiliza: consulta sin datos', [
                'recurso' => $recurso, 'http' => $resp->status(), 'mensaje' => $msg,
            ]);

            return $this->error($msg !== '' ? $msg : 'No se encontraron datos para ese número.');
        }

        Cache::put($cacheKey, $json['data'], self::CACHE_TTL);

        return ['ok' => true, 'data' => $mapear($json['data']), 'error' => null];
    }

    private function error(string $mensaje): array
    {
        return ['ok' => false, 'data' => null, 'error' => $mensaje];
    }

    /** Texto de la API → repara encoding y pasa a Title Case ("JOSE PEDRO" → "Jose Pedro"). */
    private function limpiar(?string $valor): string
    {
        $valor = trim($this->reparar((string) $valor));

        return $valor === '' ? '' : mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * La API entrega algunos textos con doble codificación: "PUÑA" llega como
     * "PUÃA" (bytes UTF-8 leídos como latin1). Se revierte solo cuando
     * se detecta el patrón, para no dañar texto ya correcto.
     */
    private function reparar(string $valor): string
    {
        if ($valor === '' || ! preg_match('/Ã[\x80-\xBF\x{0080}-\x{009F}]/u', $valor)) {
            return $valor;
        }

        $reparado = @mb_convert_encoding($valor, 'ISO-8859-1', 'UTF-8');

        return ($reparado !== false && $reparado !== '' && mb_check_encoding($reparado, 'UTF-8'))
            ? $reparado
            : $valor;
    }

    /** "12/05/1980" o "1980-05-12" → "1980-05-12"; vacío → null. */
    private function fecha(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function sexo(?string $valor): ?string
    {
        $valor = strtoupper(trim((string) $valor));

        return match (true) {
            str_starts_with($valor, 'M') => 'M',
            str_starts_with($valor, 'F') => 'F',
            default => null,
        };
    }
}
