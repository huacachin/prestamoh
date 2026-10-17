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
 *
 * Qué cuota se desglosa: la que recibió dos o más abonos de verdad (≥ S/ 1:
 * el centavo que un pago semanal deja caer en la cuota siguiente no cuenta) y,
 * desde el 17/10, también la cuota AMORTIZADA (con algo aplicado y sin
 * completar) aunque lleve un solo abono: es justo "pagó el saldo de una cuota
 * y amortizó la siguiente", y sin la fila la pantalla de cobro no decía ni
 * cuándo ni cuánto.
 *
 * Cada abono trae además su COBRO (el evento de caja de esa fecha+hora): total
 * cobrado, mora y lo que fue a las otras cuotas, para explicar en la propia
 * fila el cobro que tocó dos cuotas ("Cobro #118431 de S/ 450.00 · además
 * S/ 50.00 a la cuota 15").
 *
 * RECIBO (corregido el 17/10): el lote se cruza SOLO por crédito + fecha + hora
 * del evento. Antes se cruzaba por mass_deletion_details.payment_id sin filtrar
 * por crédito, y los lotes migrados del legacy guardan ahí el id del ingreso
 * legacy: el botón abría el recibo de OTRO cliente (verificado: 14.1 del
 * crédito 28808 abría el lote 109242 del crédito 27102). Sin lote en esa clave
 * el abono queda sin recibo: mejor ninguno que uno equivocado.
 *
 * Compartido por /credits/{id}/schedule y /payments/create (17/10/2026,
 * Antony: "al igual que la primera vista debe de haber un desglose").
 */
class AbonosPorCuota
{
    /**
     * @return array<int, list<array{fecha: string, hora: ?string, cap: float, int: float, exc: float, monto: float, saldo: float, color: string, tarde: bool, payment_id: int, lote: ?int, recibo: ?string, segun_caja: bool, cobro: array{lote: ?int, total: float, mora: float, otras: list<array{num: int, monto: float}>}}>> por installment_id
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
     * @param  Collection<int, object>  $installments  filas de credit_installments del crédito, ordenadas por num_cuota
     * @param  Collection<int, object>  $pays  filas de payments del crédito en orden cronológico (sin 'Gat')
     * @return array<int, list<array<string, mixed>>> por installment_id (ver porCredito)
     */
    public static function desdePagos(Collection $installments, Collection $pays): array
    {
        if ($installments->isEmpty()) {
            return [];
        }
        $creditId = (int) ($installments->first()->credit_id ?? 0);
        $idPorNum = $installments->pluck('id', 'num_cuota')->map(fn ($v) => (int) $v)->all();
        $numPorId = array_flip($idPorNum);

        // ─── 1) Eventos de caja: misma fecha+hora = un cobro en ventanilla. ───
        // Cada evento guarda su total (con mora), su mora y lo que fue a cada cuota.
        $eventos = []; // 'Y-m-d|H:i:s' => ['fecha','hora','total','mora','cuotas' => [insId => [...]]]
        foreach ($pays as $p) {
            $f = $p->fecha ? Carbon::parse($p->fecha)->format('Y-m-d') : '';
            $k = $f.'|'.self::hora($p->hora);
            if (! isset($eventos[$k])) {
                $eventos[$k] = ['fecha' => $f, 'hora' => $p->hora, 'total' => 0.0, 'mora' => 0.0, 'cuotas' => []];
            }
            $monto = (float) $p->monto;
            $eventos[$k]['total'] += $monto;
            // La mora no es abono (no baja el saldo de la cuota): MORA, MORA ACUM., MORA INTERES…
            if (strtoupper(substr($p->documento ?? '', 0, 4)) === 'MORA' || strtoupper((string) $p->tipo) === 'MORA') {
                $eventos[$k]['mora'] += $monto;

                continue;
            }
            $insId = $p->installment_id ? (int) $p->installment_id : null;
            if ($insId === null && preg_match('/(\d+)\s*\/\s*\d+/', (string) $p->detalle, $m)) {
                $insId = $idPorNum[(int) $m[1]] ?? null;
            }
            if ($insId === null || ! isset($numPorId[$insId])) {
                continue; // pago sin cuota (OTROS): cuenta en el total del cobro, no en una cuota
            }
            if (! isset($eventos[$k]['cuotas'][$insId])) {
                $eventos[$k]['cuotas'][$insId] = ['cap' => 0.0, 'int' => 0.0, 'exc' => 0.0, 'monto' => 0.0, 'payment_id' => (int) $p->id];
            }
            $campo = match (strtoupper((string) $p->tipo)) {
                'INTERES' => 'int', 'EXCEDENTE' => 'exc', default => 'cap',
            };
            $eventos[$k]['cuotas'][$insId][$campo] += $monto;
            $eventos[$k]['cuotas'][$insId]['monto'] += $monto;
        }
        ksort($eventos); // 'Y-m-d|H:i:s' ordena en el tiempo

        // ─── 2) Qué cuota se desglosa. ───
        $porCuota = []; // insId => [clave de evento => abono]
        foreach ($eventos as $k => $ev) {
            foreach ($ev['cuotas'] as $insId => $c) {
                $porCuota[$insId][$k] = $c;
            }
        }

        $abonos = [];
        $clavesConAbono = [];
        foreach ($porCuota as $insId => $evs) {
            $deVerdad = count(array_filter($evs, fn ($e) => $e['monto'] >= 1));
            $ins = $installments->firstWhere('id', $insId);
            $aplicado = (float) $ins->importe_aplicado + (float) $ins->interes_aplicado + (float) $ins->excedente_aplicado;
            $amortizada = ! (bool) $ins->pagado && $aplicado > 0.001;
            if ($deVerdad < 2 && ! ($amortizada && $deVerdad >= 1)) {
                continue;
            }
            $total = (float) $ins->importe_cuota + (float) $ins->importe_interes + (float) $ins->importe_excedente;
            $venc = $ins->fecha_vencimiento ? Carbon::parse($ins->fecha_vencimiento)->format('Y-m-d') : '';
            // Cobros migrados: la caja puede atribuir a la cuota algo distinto de lo aplicado
            // (28808, cuota 19: Σ 462.50 vs 425). Se muestra lo que dijo caja y se avisa.
            $segunCaja = abs(array_sum(array_column($evs, 'monto')) - $aplicado) > 0.01;
            $acum = 0.0;
            foreach ($evs as $k => $e) {
                if ($e['monto'] < 0.005) {
                    continue; // la fila 'CAPITAL 0.00' suelta del legacy no es un abono
                }
                $ev = $eventos[$k];
                $acum += $e['monto'];
                $e['fecha'] = $ev['fecha'];
                $e['hora'] = $ev['hora'];
                $e['saldo'] = round(max(0, $total - $acum), 2);
                // Mismo formato que la fila de la cuota: domingo rojo, sábado verde, tarde en amarillo.
                $dow = $e['fecha'] !== '' ? Carbon::parse($e['fecha'])->dayOfWeek : null;
                $e['color'] = match ($dow) {
                    Carbon::SUNDAY => 'red', Carbon::SATURDAY => 'green', default => '',
                };
                $e['tarde'] = $e['fecha'] !== '' && $venc !== '' && $e['fecha'] > $venc;
                $e['segun_caja'] = $segunCaja;
                $otras = [];
                foreach ($ev['cuotas'] as $otroId => $oc) {
                    if ($otroId !== $insId && $oc['monto'] >= 0.01) {
                        $otras[] = ['num' => (int) $numPorId[$otroId], 'monto' => round($oc['monto'], 2)];
                    }
                }
                usort($otras, fn ($a, $b) => $a['num'] <=> $b['num']);
                $e['cobro'] = ['lote' => null, 'total' => round($ev['total'], 2), 'mora' => round($ev['mora'], 2), 'otras' => $otras];
                $e['lote'] = null;
                $e['recibo'] = null;
                $e['clave'] = $k;
                $abonos[$insId][] = $e;
                $clavesConAbono[$k] = true;
            }
        }

        if ($abonos === []) {
            return [];
        }

        // ─── 3) Recibo: el lote del crédito con la misma fecha+hora del evento. ───
        $lotes = self::lotesPorClave($creditId, array_keys($clavesConAbono), $eventos);
        foreach ($abonos as $insId => $lista) {
            foreach ($lista as $i => $e) {
                $md = $lotes[$e['clave']] ?? null;
                $abonos[$insId][$i]['lote'] = $md;
                $abonos[$insId][$i]['cobro']['lote'] = $md;
                $abonos[$insId][$i]['recibo'] = $md ? URL::signedRoute('recibo.publico', ['massDeletionId' => $md]) : null;
                unset($abonos[$insId][$i]['clave']);
            }
        }

        return $abonos;
    }

    /**
     * Lote (mass_deletion) de cada evento de caja, cruzado por crédito + fecha +
     * hora. Si en esa clave hubiera más de un lote (102 casos en todo el
     * histórico, ninguno en 2026), gana el que cuadra con el total cobrado.
     * Nunca por mass_deletion_details.payment_id: en los lotes migrados ese id
     * es del legacy y señala cobros de otros créditos.
     *
     * @param  list<string>  $claves  'Y-m-d|H:i:s' de los eventos con abono
     * @return array<string, int> clave => mass_deletion_id
     */
    private static function lotesPorClave(int $creditId, array $claves, array $eventos): array
    {
        if ($creditId <= 0 || $claves === []) {
            return [];
        }

        $candidatos = []; // clave => [['id', 'amount'], ...]
        foreach (DB::table('mass_deletions')->where('credit_id', $creditId)->orderBy('id')->get(['id', 'date', 'time', 'amount']) as $m) {
            $k = ($m->date ? Carbon::parse($m->date)->format('Y-m-d') : '').'|'.self::hora($m->time);
            $candidatos[$k][] = ['id' => (int) $m->id, 'amount' => (float) $m->amount];
        }

        $out = [];
        foreach ($claves as $k) {
            $lista = $candidatos[$k] ?? [];
            if ($lista === []) {
                continue;
            }
            $total = $eventos[$k]['total'];
            $exacto = array_values(array_filter($lista, fn ($l) => abs($l['amount'] - $total) <= 0.01));
            $out[$k] = ($exacto[0] ?? $lista[0])['id'];
        }

        return $out;
    }

    /** 'H:i:s' normalizada (payments.hora es varchar y mass_deletions.time es TIME; pueden traer milésimas o venir vacías). */
    private static function hora(mixed $hora): string
    {
        return substr((string) $hora, 0, 8);
    }
}
