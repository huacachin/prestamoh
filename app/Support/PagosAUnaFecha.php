<?php

namespace App\Support;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Calcular pagos" › pestaña "Pagos a una fecha" (30/10/2026, Antony, crédito 29393): cuánto tiene que
 * pagar el cliente si paga en la fecha elegida (cualquier fecha). Son las cuotas impagas que vencen hasta
 * ese día, cada una con su saldo y su mora por días calendario × la tarifa de la cuota, la misma regla de
 * "elegir cuotas" y del cobro (Credit::moraDiaria); sin la mora acumulada de las cuotas ya pagadas.
 *
 * El texto para mandar al cliente tiene el formato que pidió Antony:
 *   1-4    05/10/2026    500.00    0.00    Vencida    Mora 25 Dias 89.25
 *   …
 *   Total 2,207.06
 * (orden-cuota, vencimiento, cuota, lo ya abonado, estado y mora). La cuota que vence justo ese día va
 * como "Vence", sin mora.
 */
class PagosAUnaFecha
{
    /**
     * @return array{fecha: string, filas: list<array{orden: int, num: int, venc: string, cuota: float, pagado: float, saldo: float, dias: int, tarifa: float, mora: float, estado: string}>, saldo: float, mora: float, total: float, texto: string}
     */
    public static function calcular(Credit $credit, Carbon $fecha): array
    {
        $dia = $fecha->copy()->startOfDay();
        $filas = [];
        $cuotas = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->where('pagado', 0)->where('importe_cuota', '>', 0)
            ->whereDate('fecha_vencimiento', '<=', $dia->toDateString())
            ->orderBy('fecha_vencimiento')->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes', 'importe_excedente',
                'importe_aplicado', 'interes_aplicado', 'excedente_aplicado']);

        foreach ($cuotas as $ins) {
            $venc = Carbon::parse($ins->fecha_vencimiento)->startOfDay();
            $cuota = round((float) $ins->importe_cuota + (float) $ins->importe_interes + (float) $ins->importe_excedente, 2);
            $pagado = round((float) $ins->importe_aplicado + (float) $ins->interes_aplicado + (float) $ins->excedente_aplicado, 2);
            $dias = max(0, (int) floor($venc->diffInDays($dia, false)));
            $tarifa = $credit->moraDiaria($cuota);
            $filas[] = [
                'orden' => count($filas) + 1,
                'num' => (int) $ins->num_cuota,
                'venc' => $venc->toDateString(),
                'cuota' => $cuota,
                'pagado' => $pagado,
                'saldo' => round($cuota - $pagado, 2),
                'dias' => $dias,
                'tarifa' => $tarifa,
                'mora' => round($dias * $tarifa, 2),
                'estado' => $dias > 0 ? 'Vencida' : 'Vence',
            ];
        }

        $saldo = round(array_sum(array_column($filas, 'saldo')), 2);
        $mora = round(array_sum(array_column($filas, 'mora')), 2);
        $total = round($saldo + $mora, 2);

        return [
            'fecha' => $dia->format('d/m/Y'),
            'filas' => $filas,
            'saldo' => $saldo,
            'mora' => $mora,
            'total' => $total,
            'texto' => self::texto($filas, $total),
        ];
    }

    /**
     * El mensaje para el cliente, una línea por cuota y el total.
     *
     * @param  list<array{orden: int, num: int, venc: string, cuota: float, pagado: float, dias: int, mora: float, estado: string}>  $filas
     */
    public static function texto(array $filas, float $total): string
    {
        $lineas = array_map(fn (array $f) => implode('    ', array_filter([
            $f['orden'].'-'.$f['num'],
            Carbon::parse($f['venc'])->format('d/m/Y'),
            number_format($f['cuota'], 2),
            number_format($f['pagado'], 2),
            $f['estado'],
            $f['dias'] > 0 ? 'Mora '.$f['dias'].' Dias '.number_format($f['mora'], 2) : null,
        ], fn ($parte) => $parte !== null)), $filas);
        $lineas[] = 'Total '.number_format($total, 2);

        return implode("\n", $lineas);
    }

    /**
     * Los vencimientos del crédito para el calendario (pagadas, vencidas y por vencer se pintan distinto).
     *
     * @return list<array{num: int, fecha: string, pagada: bool}>
     */
    public static function vencimientos(Credit $credit): array
    {
        return DB::table('credit_installments')
            ->where('credit_id', $credit->id)->where('importe_cuota', '>', 0)
            ->orderBy('fecha_vencimiento')->orderBy('num_cuota')
            ->get(['num_cuota', 'fecha_vencimiento', 'pagado'])
            ->map(fn ($i) => [
                'num' => (int) $i->num_cuota,
                'fecha' => Carbon::parse($i->fecha_vencimiento)->toDateString(),
                'pagada' => (bool) $i->pagado,
            ])->all();
    }
}
