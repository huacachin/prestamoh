<?php

namespace App\Support;

use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Abonos por cuota (02/10/2026, Antony: "en semanal está desglosado los pagos
 * pero en mensual no"). Cuando una cuota se pagó en varios abonos —lo normal
 * en el mensual de una sola cuota, o la cuota que el cliente va amortizando—
 * debajo de su fila se desglosa cada pago: fecha, hora, capital / interés /
 * excedente, monto, el saldo que iba quedando, su lote de cobro y su recibo.
 * Se arma con lo que cada fila de caja dice de su cuota (installment_id o el
 * "n/N" del detalle), no con el FIFO, para que sea el desglose de ventanilla.
 * Solo se desglosa la cuota con dos o más abonos de verdad (≥ S/ 1): el
 * centavo que un pago semanal deja caer en la cuota siguiente no cuenta.
 *
 * Compartido por /credits/{id}/schedule y /payments/create (17/10/2026,
 * Antony: "al igual que la primera vista debe de haber un desglose").
 */
class AbonosPorCuota
{
    /**
     * @return array<int, list<array{fecha: string, hora: ?string, cap: float, int: float, exc: float, monto: float, saldo: float, color: string, tarde: bool, payment_id: int, lote: ?int, recibo: ?string}>> por installment_id
     */
    public static function porCredito(Credit $credit): array
    {
        $installments = DB::table('credit_installments')
            ->where('credit_id', $credit->id)
            ->orderBy('num_cuota')
            ->get()->values();

        // Pagos reales en orden cronológico (excluye 'Gat' como el legacy).
        $pays = DB::table('payments')
            ->where('credit_id', $credit->id)
            ->whereRaw("(detalle IS NULL OR RIGHT(detalle, 3) <> 'Gat')")
            ->select('id', 'fecha', 'hora', 'monto', 'documento', 'tipo', 'installment_id', 'detalle')
            ->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get();

        return self::desdePagos($installments, $pays);
    }

    /**
     * @param  Collection<int, object>  $installments  filas de credit_installments ordenadas por num_cuota
     * @param  Collection<int, object>  $pays  filas de payments en orden cronológico (sin 'Gat')
     * @return array<int, list<array{fecha: string, hora: ?string, cap: float, int: float, exc: float, monto: float, saldo: float, color: string, tarde: bool, payment_id: int, lote: ?int, recibo: ?string}>> por installment_id
     */
    public static function desdePagos(Collection $installments, Collection $pays): array
    {
        $idPorNum = $installments->pluck('id', 'num_cuota')->map(fn ($v) => (int) $v)->all();

        $porCuota = []; // installment_id => ['fecha|hora' => evento]
        foreach ($pays as $p) {
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA') {
                continue;
            }
            $insId = $p->installment_id ? (int) $p->installment_id : null;
            if ($insId === null && preg_match('/(\d+)\s*\/\s*\d+/', (string) $p->detalle, $m)) {
                $insId = $idPorNum[(int) $m[1]] ?? null;
            }
            if ($insId === null || ! in_array($insId, $idPorNum, true)) {
                continue;
            }
            $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : '';
            $k = $f.'|'.(string) $p->hora;
            if (! isset($porCuota[$insId][$k])) {
                $porCuota[$insId][$k] = ['fecha' => $f, 'hora' => $p->hora, 'cap' => 0.0, 'int' => 0.0, 'exc' => 0.0, 'monto' => 0.0, 'payment_id' => (int) $p->id];
            }
            $campo = match (strtoupper((string) $p->tipo)) {
                'INTERES' => 'int', 'EXCEDENTE' => 'exc', default => 'cap',
            };
            $porCuota[$insId][$k][$campo] += (float) $p->monto;
            $porCuota[$insId][$k]['monto'] += (float) $p->monto;
        }

        $abonos = [];
        $paymentIds = [];
        foreach ($porCuota as $insId => $eventos) {
            if (count(array_filter($eventos, fn ($e) => $e['monto'] >= 1)) < 2) {
                continue;
            }
            ksort($eventos); // 'Y-m-d|H:i:s' ordena en el tiempo
            $ins = $installments->firstWhere('id', $insId);
            $total = (float) $ins->importe_cuota + (float) $ins->importe_interes + (float) $ins->importe_excedente;
            $venc = $ins->fecha_vencimiento ? Carbon::parse($ins->fecha_vencimiento)->format('Y-m-d') : '';
            $acum = 0.0;
            foreach ($eventos as $e) {
                $acum += $e['monto'];
                $e['saldo'] = round(max(0, $total - $acum), 2);
                $e['lote'] = null;
                $e['recibo'] = null;
                // Mismo formato que la fila de la cuota: domingo rojo, sábado verde, tarde en amarillo.
                $dow = $e['fecha'] !== '' ? Carbon::parse($e['fecha'])->dayOfWeek : null;
                $e['color'] = match ($dow) {
                    Carbon::SUNDAY => 'red', Carbon::SATURDAY => 'green', default => '',
                };
                $e['tarde'] = $e['fecha'] !== '' && $venc !== '' && $e['fecha'] > $venc;
                $abonos[$insId][] = $e;
                $paymentIds[] = $e['payment_id'];
            }
        }

        // Recibo de cada abono: el cobro (mass_deletion) al que pertenece su fila de caja.
        if ($paymentIds !== []) {
            $mdPorPago = DB::table('mass_deletion_details')->whereIn('payment_id', $paymentIds)->pluck('mass_deletion_id', 'payment_id');
            foreach ($abonos as $insId => $lista) {
                foreach ($lista as $i => $e) {
                    $md = $mdPorPago[$e['payment_id']] ?? null;
                    $abonos[$insId][$i]['lote'] = $md ? (int) $md : null;
                    $abonos[$insId][$i]['recibo'] = $md ? URL::signedRoute('recibo.publico', ['massDeletionId' => (int) $md]) : null;
                }
            }
        }

        return $abonos;
    }
}
