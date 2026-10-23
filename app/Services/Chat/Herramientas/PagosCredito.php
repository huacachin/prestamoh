<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Credit;
use App\Models\MassDeletion;
use App\Models\MassDeletionDetail;
use App\Support\FiltrosSeguros;

/**
 * Cobros (recibos) de un crédito (23/10/2026): un lote de mass_deletions por
 * cobro, con su desglose capital / interés / mora / excedente, las cuotas que
 * tocó, el cobrador y el enlace firmado al recibo público.
 */
final class PagosCredito implements Herramienta
{
    private const TIPOS = ['C' => 'capital', 'I' => 'interes', 'E' => 'excedente', 'M' => 'mora'];

    public function nombre(): string
    {
        return 'pagos_credito';
    }

    public function descripcion(): string
    {
        return 'Cobros (recibos) de un crédito, del más reciente al más antiguo: fecha, hora, total cobrado, desglose en capital, interés, mora y excedente, cuotas que tocó, quién cobró y enlace al recibo. Acepta un rango de fechas (AAAA-MM-DD) y un límite (por defecto 20, máximo 60). Úsala para "qué pagó", "cuándo fue su último pago" o "cuánto pagó en octubre".';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'credit_id' => ['type' => 'integer', 'description' => 'Código del crédito'],
                'desde' => ['type' => 'string', 'description' => 'Fecha inicial AAAA-MM-DD (opcional)'],
                'hasta' => ['type' => 'string', 'description' => 'Fecha final AAAA-MM-DD (opcional)'],
                'limite' => ['type' => 'integer', 'description' => 'Cuántos cobros devolver (por defecto 20, máximo 60)'],
            ],
            'required' => ['credit_id'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $id = (int) ($argumentos['credit_id'] ?? 0);
        $credito = Credit::find($id);
        if ($credito === null) {
            return ['error' => "No existe el crédito {$id}."];
        }
        $limite = max(1, min(60, (int) ($argumentos['limite'] ?? 20)));
        [$desde, $hasta] = FiltrosSeguros::rangoLegacy($argumentos['desde'] ?? null, $argumentos['hasta'] ?? null);

        $query = MassDeletion::with(['details.installment:id,num_cuota'])->where('credit_id', $credito->id)
            ->orderByDesc('date')->orderByDesc('time')->orderByDesc('id');
        if ($desde !== null) {
            $query->where('date', '>=', $desde);
        }
        if ($hasta !== null) {
            $query->where('date', '<=', $hasta);
        }
        $total = (clone $query)->count();
        $lotes = $query->limit($limite)->get();

        $cobros = $lotes->map(function (MassDeletion $lote) {
            $desglose = ['capital' => 0.0, 'interes' => 0.0, 'mora' => 0.0, 'excedente' => 0.0];
            $cuotas = [];
            foreach ($lote->details as $d) {
                /** @var MassDeletionDetail $d */
                $clave = self::TIPOS[strtoupper(substr((string) $d->tipo, 0, 1))] ?? null;
                if ($clave !== null) {
                    $desglose[$clave] = round($desglose[$clave] + (float) $d->amount, 2);
                }
                if ($d->installment?->num_cuota) {
                    $cuotas[(int) $d->installment->num_cuota] = true;
                }
            }
            ksort($cuotas);

            return [
                'recibo' => (int) $lote->id,
                'fecha' => ResumenCredito::fecha($lote->date),
                'hora' => substr((string) $lote->time, 0, 5),
                'total' => round((float) $lote->amount, 2),
                'desglose' => $desglose,
                'cuotas' => array_keys($cuotas),
                'cobrador' => $lote->performed_by ?: $lote->user,
                // Recibo interno (pide sesión): el enlace público firmado no sale del servidor ni queda en el chat.
                'recibo_url' => route('payments.ticket', $lote->id),
            ];
        })->values()->all();

        return [
            'credito' => $credito->id,
            'cobros_en_total' => $total,
            'mostrados' => count($cobros),
            'rango' => ['desde' => $desde, 'hasta' => $hasta],
            'suma_mostrada' => round((float) array_sum(array_column($cobros, 'total')), 2),
            'cobros' => $cobros,
        ];
    }
}
