<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Client;
use App\Models\Credit;
use App\Support\MorosidadClientes;
use Illuminate\Support\Collection;

/**
 * Busca clientes por nombre (en cualquier orden), documento, expediente o
 * código de crédito (23/10/2026). Es la primera consulta que el modelo hace
 * cuando el usuario nombra a una persona.
 */
final class BuscarClientes implements Herramienta
{
    private const TOPE = 10;

    public function nombre(): string
    {
        return 'buscar_clientes';
    }

    public function descripcion(): string
    {
        return 'Busca clientes por nombre o apellidos (en cualquier orden, no hace falta el nombre completo), por DNI/RUC, por número de expediente o por código de crédito. Devuelve hasta 10 coincidencias con id de cliente, expediente, nombre, documento, celular, zona, asesor, estado, cuántos créditos activos tiene y cuántas cuotas vencidas acumula su peor crédito. Úsala PRIMERO cuando el usuario menciona a una persona; con el id de cliente pide luego ficha_cliente.';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Nombre, apellidos, DNI/RUC, expediente o código de crédito'],
            ],
            'required' => ['texto'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $texto = trim((string) ($argumentos['texto'] ?? ''));
        if ($texto === '') {
            return ['clientes' => [], 'nota' => 'Falta el texto a buscar.'];
        }

        $base = Client::query()->with('asesor:id,name,username')->where('status', '<>', 'deleted');
        $nota = null;

        if (preg_match('/^\d+$/', $texto)) {
            $clientes = (clone $base)->where(fn ($q) => $q->where('documento', $texto)->orWhere('expediente', $texto))->limit(self::TOPE)->get();
            // El mismo número puede ser también el código de un crédito: su titular entra con aviso.
            $credito = Credit::with('client.asesor:id,name,username')->find((int) $texto);
            if ($credito?->client && $credito->client->status !== 'deleted' && ! $clientes->contains('id', $credito->client->id)) {
                $nota = $clientes->isEmpty()
                    ? "El número {$texto} es el código del crédito; se muestra a su titular."
                    : "El número {$texto} también es el código de un crédito cuyo titular es {$credito->client->fullName()} (client_id {$credito->client->id}, incluido al final); pide detalle_credito si te referías al crédito.";
                $clientes->push($credito->client);
            }
        } else {
            $clientes = (clone $base)->nombreContiene($texto)->orderBy('apellido_pat')->orderBy('apellido_mat')->orderBy('nombre')->limit(self::TOPE + 1)->get();
            if ($clientes->count() > self::TOPE) {
                $clientes = $clientes->take(self::TOPE);
                $nota = 'Hay más coincidencias; afina con otro apellido o el documento.';
            }
        }

        return array_filter([
            'clientes' => $this->filas($clientes),
            'nota' => $nota ?? ($clientes->isEmpty() ? 'Ningún cliente coincide.' : null),
        ], fn ($v) => $v !== null);
    }

    private function filas(Collection $clientes): array
    {
        if ($clientes->isEmpty()) {
            return [];
        }
        $ids = $clientes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $morosidad = MorosidadClientes::calcular($ids);
        $activos = Credit::whereIn('client_id', $ids)->where('situacion', 'Activo')
            ->selectRaw('client_id, COUNT(*) AS n')->groupBy('client_id')->pluck('n', 'client_id');

        return $clientes->map(fn (Client $c) => [
            'client_id' => (int) $c->id,
            'expediente' => $c->expediente,
            'nombre' => $c->fullName(),
            'documento' => trim(($c->tipo_documento ?? '').' '.($c->documento ?? '')),
            'celular' => $c->celular1,
            'zona' => $c->zona,
            'estado' => $c->status,
            'asesor' => $c->asesor?->username ?? $c->asesor?->name,
            'creditos_activos' => (int) ($activos[$c->id] ?? 0),
            'cuotas_vencidas' => (int) ($morosidad['morosidad'][$c->id] ?? 0),
            'en_ejecucion' => isset($morosidad['enEjecucion'][$c->id]),
            'ficha' => route('clients.show', $c->id),
        ])->values()->all();
    }
}
