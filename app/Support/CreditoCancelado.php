<?php

namespace App\Support;

use App\Models\Credit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuotas sin marcar de un crédito CANCELADO (30/10/2026, Antony, crédito 29013: "tiene el crédito
 * cancelado pero sale una cuota vencida que falta pagar 60 soles").
 *
 * La migración del legacy dejó cuotas cobradas sin marcar como pagadas: en prod, 10 205 créditos
 * cancelados sin refinanciar (9 974 cobrados exacto, 114 de más, 117 con faltante, ninguno de 2026) y
 * 7 411 refinanciados; ningún activo. En el cronograma y en la ficha del crédito esas cuotas no son
 * deuda ni "Vencida": se muestran "Cancelado", con lo que caja cobró para ellas y saldo 0. Los datos
 * no se tocan.
 *
 * Lo no cobrado se mide por crédito (cronograma − pagos sin mora), no por cuota: el legacy a veces
 * anotó en una cuota lo de la siguiente (28155: S/ 70 de interés de la 48 quedaron en la 47).
 */
class CreditoCancelado
{
    public static function es(Credit $credit): bool
    {
        return $credit->situacion === 'Cancelado';
    }

    /**
     * Lo que caja cobró para cada cuota: capital, interés y excedente de los pagos que la nombran
     * (installment_id, o el "n/N" del detalle en los migrados, como AbonosPorCuota). Sin la mora.
     *
     * @param  Collection<int, object>  $installments  filas de credit_installments del crédito
     * @param  Collection<int, object>  $pays  pagos del crédito (sin 'Gat')
     * @return array<int, array{cap: float, int: float, exc: float}> por installment_id
     */
    public static function cobradoPorCuota(Collection $installments, Collection $pays): array
    {
        $idPorNum = $installments->pluck('id', 'num_cuota')->map(fn ($v) => (int) $v)->all();
        $porCuota = [];
        foreach ($pays as $p) {
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA' || strtoupper((string) $p->tipo) === 'MORA') {
                continue;
            }
            $insId = $p->installment_id ? (int) $p->installment_id : null;
            if ($insId === null && preg_match('/(\d+)\s*\/\s*\d+/', (string) $p->detalle, $m)) {
                $insId = $idPorNum[(int) $m[1]] ?? null;
            }
            if ($insId === null) {
                continue;
            }
            $campo = match (strtoupper((string) $p->tipo)) {
                'INTERES' => 'int', 'EXCEDENTE' => 'exc', default => 'cap',
            };
            $porCuota[$insId] ??= ['cap' => 0.0, 'int' => 0.0, 'exc' => 0.0];
            $porCuota[$insId][$campo] += (float) $p->monto;
        }

        return $porCuota;
    }

    /**
     * cobradoPorCuota() leyendo las cuotas y los pagos del crédito.
     *
     * @return array<int, array{cap: float, int: float, exc: float}> por installment_id
     */
    public static function cobradoPorCuotaDe(Credit $credit): array
    {
        return self::cobradoPorCuota(
            DB::table('credit_installments')->where('credit_id', $credit->id)->get(['id', 'num_cuota']),
            DB::table('payments')->where('credit_id', $credit->id)
                ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
                ->get(['monto', 'documento', 'tipo', 'installment_id', 'detalle'])
        );
    }

    /** Tooltip del estado "Cancelado" de una cuota sin marcar. */
    public static function motivoCuota(Credit $credit): string
    {
        $el = self::el($credit);

        return $credit->cancelado_por_refi
            ? "Crédito cancelado por refinanciación{$el}: el saldo de esta cuota pasó al crédito nuevo."
            : "Crédito cancelado{$el}: la cuota quedó sin marcar como pagada en el sistema anterior.";
    }

    /** Tooltip del saldo (en 0) de un crédito cancelado; $noCobrado = cronograma − pagos sin mora. */
    public static function motivoSaldo(Credit $credit, float $noCobrado): string
    {
        $el = self::el($credit);
        if ($noCobrado <= 0.01) {
            return "Crédito cancelado{$el}.";
        }

        return "Crédito cancelado{$el}: S/ ".number_format($noCobrado, 2)
            .($credit->cancelado_por_refi ? ' pasaron al crédito nuevo (refinanciación).' : ' del cronograma no se cobraron.');
    }

    private static function el(Credit $credit): string
    {
        return $credit->fecha_cancelacion ? ' el '.$credit->fecha_cancelacion->format('d/m/Y') : '';
    }
}
