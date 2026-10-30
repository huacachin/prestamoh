<?php

namespace App\Support;

use App\Models\Vehiculo;

/**
 * Placa que ya tiene otro vehículo (30/10/2026, Antony): se avisa al buscar la
 * placa y al guardar el vehículo, en el alta de cliente y en la pestaña
 * Vehículos de la ficha, con el cliente al que está asignada y un enlace a su
 * ficha. Compara sin guiones, espacios ni minúsculas: "ABC-123" y "abc123"
 * son el mismo vehículo.
 */
class PlacaRegistrada
{
    /**
     * @return array{placa: string, vehiculo_id: int, client_id: int, cliente: string, url: string}|null
     */
    public static function buscar(?string $placa, ?int $ignorarVehiculoId = null): ?array
    {
        $normal = self::normalizar($placa);
        if ($normal === '') {
            return null;
        }

        $vehiculo = Vehiculo::with('client:id,nombre,apellido_pat,apellido_mat')
            ->whereRaw("REPLACE(REPLACE(UPPER(placa), '-', ''), ' ', '') = ?", [$normal])
            ->when($ignorarVehiculoId, fn ($q) => $q->whereKeyNot($ignorarVehiculoId))
            ->orderBy('id')
            ->first();

        if ($vehiculo === null) {
            return null;
        }

        return [
            'placa' => (string) $vehiculo->placa,
            'vehiculo_id' => (int) $vehiculo->id,
            'client_id' => (int) $vehiculo->client_id,
            'cliente' => $vehiculo->client?->fullName() ?: 'cliente #'.$vehiculo->client_id,
            'url' => route('clients.edit', ['id' => $vehiculo->client_id, 'tab' => 'vehiculos']),
        ];
    }

    /**
     * @param  array{placa: string, client_id: int, cliente: string}  $registrada
     */
    public static function mensaje(array $registrada, ?int $clienteActual = null): string
    {
        if ($clienteActual !== null && $registrada['client_id'] === $clienteActual) {
            return "La placa {$registrada['placa']} ya está registrada en este cliente.";
        }

        return "La placa {$registrada['placa']} ya está registrada en el cliente {$registrada['cliente']}.";
    }

    public static function normalizar(?string $placa): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim((string) $placa)));
    }
}
