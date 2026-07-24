<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconstrucción y snapshot diario de la morosidad.
 *
 * DEFINICIÓN (espejo de /reports/portfolio, el medidor del dashboard):
 * un crédito está EN MORA el día D cuando su fecha de vencimiento FINAL
 * (credits.fecha_vencimiento) es anterior a D y su saldo a esa fecha es > 0.
 *
 * RECONSTRUCCIÓN HACIA ATRÁS: el saldo al día D parte del saldo ACTUAL del
 * cronograma (importes − aplicados, la misma fuente de Portfolio: hoy cuadra
 * por construcción) y le suma de vuelta los pagos C/I/E POSTERIORES a D
 * ("des-pagar"). Es sólida en la ventana reciente porque cada operación
 * suma en caja lo mismo que aplica al cronograma (verificado en el backtest
 * del motor); las brechas caja↔aplicación de data antigua no entran porque
 * nunca se des-pagan.
 *
 * Limitación documentada: los importes del cronograma son los actuales; en
 * créditos cancelados con condonación, el saldo previo a la cancelación
 * queda levemente subestimado. Por eso el backfill se acota (~12 meses).
 */
final class MorosidadService
{
    /**
     * Calcula la morosidad reconstruida para una fecha.
     *
     * @return array{saldo_mora: float, saldo_cartera: float, n_creditos_mora: int, pct: float}
     */
    public function alDia(string $fecha): array
    {
        $r = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN t.venc < ? AND t.saldo > 0.005 THEN t.saldo ELSE 0 END), 0) AS mora,
                COALESCE(SUM(t.saldo), 0) AS cartera, -- incluye saldos negativos (sobre-pagos), como Portfolio
                COALESCE(SUM(CASE WHEN t.venc < ? AND t.saldo > 0.005 THEN 1 ELSE 0 END), 0) AS ncred
            FROM (
                SELECT c.id,
                       c.fecha_vencimiento AS venc,
                       -- saldo ACTUAL con la FÓRMULA EXACTA de Portfolio
                       -- (reporte1 legacy): capital + capital×%/100 −
                       -- aplicados de capital e interés (sin excedente; en
                       -- mensuales el % cuenta una sola vez — quirk legacy)…
                       (c.importe + c.importe * c.interes / 100)
                     - (SELECT COALESCE(SUM(ci.importe_aplicado + ci.interes_aplicado), 0)
                          FROM credit_installments ci WHERE ci.credit_id = c.id)
                       -- …más los pagos POSTERIORES a D (des-pagar hacia atrás)
                     + (SELECT COALESCE(SUM(p.monto), 0)
                          FROM payments p
                         WHERE p.credit_id = c.id
                           AND p.tipo IN ('CAPITAL', 'INTERES')
                           AND p.fecha > ?) AS saldo
                FROM credits c
                WHERE c.situacion <> 'Eliminado'
                  -- Cancelados: fuera del día D en adelante (espejo de
                  -- Portfolio, que los excluye siempre). Antes de su fecha de
                  -- cancelación SÍ cuentan (conciencia temporal); cancelados
                  -- sin fecha (data vieja) quedan fuera siempre.
                  AND (
                        c.situacion <> 'Cancelado'
                        OR (c.fecha_cancelacion IS NOT NULL AND c.fecha_cancelacion > ?)
                      )
            ) t
        ", [$fecha, $fecha, $fecha, $fecha]);

        $mora = round((float) $r->mora, 2);
        $cartera = round((float) $r->cartera, 2);

        return [
            'saldo_mora' => $mora,
            'saldo_cartera' => $cartera,
            'n_creditos_mora' => (int) $r->ncred,
            'pct' => $cartera > 0 ? round($mora * 100 / $cartera, 2) : 0.0,
        ];
    }

    /** Calcula y persiste el snapshot de una fecha (upsert idempotente). */
    public function snapshot(string $fecha): array
    {
        $datos = $this->alDia($fecha);

        DB::table('cache_morosidad_diaria')->updateOrInsert(
            ['fecha' => $fecha],
            $datos + ['updated_at' => now(), 'created_at' => now()]
        );

        return $datos;
    }

    /**
     * Completa los snapshots que falten en un rango (self-healing del
     * dashboard: barato cuando ya están, calcula solo los huecos).
     * Nunca escribe fechas futuras. El día de HOY se recalcula siempre
     * (los cobros del día lo mueven).
     */
    public function completarRango(string $desde, string $hasta): void
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $hasta = min($hasta, $hoy);
        if ($desde > $hasta) {
            return;
        }

        $existentes = DB::table('cache_morosidad_diaria')
            ->whereBetween('fecha', [$desde, $hasta])
            ->pluck('fecha')
            ->map(fn ($f) => substr((string) $f, 0, 10))
            ->all();
        $existentes = array_flip($existentes);

        $cursor = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        while ($cursor->lte($fin)) {
            $f = $cursor->format('Y-m-d');
            if (! isset($existentes[$f]) || $f === $hoy) {
                $this->snapshot($f);
            }
            $cursor->addDay();
        }
    }
}
