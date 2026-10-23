<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Credit;
use App\Models\MassDeletion;
use Illuminate\Support\Str;

/**
 * Detalle de un crédito por su código (23/10/2026): el resumen completo más
 * los totales pagados por tipo, cuántos cobros tuvo y quién lo registró.
 */
final class DetalleCredito implements Herramienta
{
    public function nombre(): string
    {
        return 'detalle_credito';
    }

    public function descripcion(): string
    {
        return 'Detalle de un crédito por su código: titular, tipo (diario/semanal/mensual), capital, interés y total, saldo, cuotas pagadas/pendientes/vencidas/amortizadas, primera cuota vencida y días de atraso, próxima cuota, mora de hoy y mora ya pagada, último cobro, totales pagados por tipo (capital, interés, mora), cantidad de cobros, asesor y quién lo registró. Úsala cuando el usuario da el código del crédito o quiere el detalle de uno en particular.';
    }

    public function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'credit_id' => ['type' => 'integer', 'description' => 'Código del crédito'],
            ],
            'required' => ['credit_id'],
        ];
    }

    public function ejecutar(array $argumentos): array
    {
        $id = (int) ($argumentos['credit_id'] ?? 0);
        $credito = Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota'), 'client.asesor:id,name,username', 'user:id,name,username'])->find($id);
        if ($credito === null) {
            return ['error' => "No existe el crédito {$id}."];
        }

        // Clasificado como el cronograma: la mora por el documento (los legacy 'MORA INTERES' van con tipo INTERES).
        $clase = "CASE WHEN tipo = 'MORA' OR UPPER(LEFT(documento, 4)) = 'MORA' THEN 'mora' WHEN tipo = 'INTERES' THEN 'interes' WHEN tipo = 'EXCEDENTE' THEN 'excedente' ELSE 'capital' END";
        $porTipo = ResumenCredito::pagosSinGat($credito->id)
            ->selectRaw("{$clase} AS clase, ROUND(SUM(monto), 2) AS total, COUNT(*) AS n")->groupByRaw($clase)->get()
            ->mapWithKeys(fn ($r) => [(string) $r->clase => ['total' => (float) $r->total, 'movimientos' => (int) $r->n]])->all();

        return ResumenCredito::de($credito) + [
            'cliente' => [
                'client_id' => (int) $credito->client_id,
                'nombre' => $credito->client?->fullName(),
                'documento' => $credito->client?->documento,
                'celular' => $credito->client?->celular1,
                'ficha' => $credito->client ? route('clients.show', $credito->client_id) : null,
            ],
            'pagado_por_tipo' => $porTipo,
            'cobros' => MassDeletion::where('credit_id', $credito->id)->count(),
            'registrado_por' => $credito->user?->username ?? $credito->usuario,
            'glosa' => filled($credito->glosa) ? Str::limit(trim((string) $credito->glosa), 300) : null,
            'moneda' => $credito->moneda,
        ];
    }
}
