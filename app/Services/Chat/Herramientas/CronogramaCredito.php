<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Credit;
use App\Models\CreditInstallment;
use Carbon\Carbon;

/**
 * Cuotas de un crédito con su estado (23/10/2026), con las mismas reglas que
 * la pantalla del cronograma: Pagada, Vencida, Pendiente o Amortizada.
 */
final class CronogramaCredito implements Herramienta
{
    public function nombre(): string
    {
        return 'cronograma_credito';
    }

    public function descripcion(): string
    {
        return 'Cuotas de un crédito: número, vencimiento, capital, interés, total, pagado, saldo, estado (Pagada, Vencida, Pendiente, Amortizada = con abono parcial) y días de atraso de las vencidas, más los totales. Por defecto devuelve solo las cuotas NO pagadas; pide solo_pendientes=false para ver todas (incluidas las pagadas).';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'credit_id' => ['type' => 'integer', 'description' => 'Código del crédito'],
                'solo_pendientes' => ['type' => 'boolean', 'description' => 'true (por defecto) = solo cuotas no pagadas'],
            ],
            'required' => ['credit_id'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $id = (int) ($argumentos['credit_id'] ?? 0);
        $credito = Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota')])->find($id);
        if ($credito === null) {
            return ['error' => "No existe el crédito {$id}."];
        }
        $soloPendientes = (bool) ($argumentos['solo_pendientes'] ?? true);
        $hoy = Carbon::today();

        $activo = $credito->situacion === 'Activo';
        $todas = $credito->installments->map(fn (CreditInstallment $c) => ResumenCredito::fila($c, $hoy, $activo));
        $filas = $soloPendientes ? $todas->filter(fn ($f) => $f['estado'] !== 'Pagada')->values() : $todas;

        return [
            'credito' => $credito->id,
            'situacion' => $credito->situacion,
            'tipo' => $credito->tipoPlanillaLabel(),
            'cuotas_total' => $todas->count(),
            'mostradas' => $soloPendientes ? 'solo no pagadas' : 'todas',
            'nota' => $activo ? null : 'Crédito no activo: sus cuotas impagas no cuentan como vencidas ni generan mora.',
            'cuotas' => $filas->all(),
            'totales' => [
                'pagadas' => $todas->where('estado', 'Pagada')->count(),
                'vencidas' => $todas->filter(fn ($f) => str_starts_with($f['estado'], 'Vencida'))->count(),
                'saldo' => round((float) $todas->sum('saldo'), 2),
                'saldo_vencido' => round((float) $todas->filter(fn ($f) => str_starts_with($f['estado'], 'Vencida'))->sum('saldo'), 2),
            ],
            'enlace' => route('credits.schedule', $credito->id),
        ];
    }
}
