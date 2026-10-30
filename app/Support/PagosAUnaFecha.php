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
 * El cajero marca qué cuotas entran (30/10: "tiene que haber checks y solo eso se debe seleccionar"):
 * siempre de la más antigua en adelante, porque el cobro paga en ese orden; $cuantas = cuántas desde arriba.
 *
 * El texto para mandar al cliente (formato de Antony, ampliado el 30/10 con el nombre, la mora por día
 * y los totales con y sin mora; sin la cancelación: "este módulo es para saber cuánto va a pagar"):
 *   Cliente: Quispe Garcia Cristhian Zosimo
 *   Pagos al 31/10/2026
 *   Mora por día: S/ 3.57 por cuota vencida
 *
 *   1-4    05/10/2026    500.00    0.00    Vencida    Mora 26 Dias 92.82
 *   …
 *   Total sin mora 2,000.00
 *   Total con mora 2,221.34
 * La cuota que vence justo ese día va como "Vence", sin mora.
 */
class PagosAUnaFecha
{
    /**
     * @return array{fecha: string, cliente: string, filas: list<array{orden: int, num: int, venc: string, cuota: float, pagado: float, saldo: float, dias: int, tarifa: float, mora: float, estado: string, sel: bool}>, cuantas: int, mora_dia: list<float>, saldo: float, mora: float, total: float, texto: string}
     */
    public static function calcular(Credit $credit, Carbon $fecha, ?int $cuantas = null): array
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
        $cuantas = $cuantas === null ? $cuotas->count() : max(0, min($cuotas->count(), $cuantas));

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
                'sel' => count($filas) < $cuantas,
            ];
        }

        $elegidas = array_values(array_filter($filas, fn (array $f) => $f['sel']));
        $saldo = round(array_sum(array_column($elegidas, 'saldo')), 2);
        $mora = round(array_sum(array_column($elegidas, 'mora')), 2);
        $moraDia = array_values(array_unique(array_column($elegidas ?: $filas, 'tarifa'), SORT_REGULAR));
        $calculo = [
            'fecha' => $dia->format('d/m/Y'),
            'cliente' => (string) $credit->client?->fullName(),
            'filas' => $filas,
            'cuantas' => $cuantas,
            'mora_dia' => $moraDia,
            'saldo' => $saldo,
            'mora' => $mora,
            'total' => round($saldo + $mora, 2),
        ];

        return $calculo + ['texto' => self::texto($calculo, $elegidas)];
    }

    /**
     * El mensaje para el cliente con las cuotas elegidas.
     *
     * @param  array{fecha: string, cliente: string, mora_dia: list<float>, saldo: float, total: float}  $calculo
     * @param  list<array{orden: int, num: int, venc: string, cuota: float, pagado: float, dias: int, mora: float, estado: string}>  $elegidas
     */
    public static function texto(array $calculo, array $elegidas): string
    {
        $soles = fn (float $v) => 'S/ '.number_format($v, 2);
        $lineas = array_filter([
            $calculo['cliente'] !== '' ? 'Cliente: '.$calculo['cliente'] : null,
            'Pagos al '.$calculo['fecha'],
            $calculo['mora_dia'] !== [] ? 'Mora por día: '.implode(' / ', array_map($soles, $calculo['mora_dia'])).' por cuota vencida' : null,
            '',
        ], fn ($l) => $l !== null);

        foreach ($elegidas as $f) {
            $lineas[] = implode('    ', array_filter([
                $f['orden'].'-'.$f['num'],
                Carbon::parse($f['venc'])->format('d/m/Y'),
                number_format($f['cuota'], 2),
                number_format($f['pagado'], 2),
                $f['estado'],
                $f['dias'] > 0 ? 'Mora '.$f['dias'].' Dias '.number_format($f['mora'], 2) : null,
            ], fn ($parte) => $parte !== null));
        }
        $lineas[] = 'Total sin mora '.number_format($calculo['saldo'], 2);
        $lineas[] = 'Total con mora '.number_format($calculo['total'], 2);

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
