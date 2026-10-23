<?php

namespace App\Support;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Motivo" de un cobro en el reporte de pagos y en Caja General 1
 * (23/10/2026, Antony: "hay que detallarlo más; esa descripción viene del
 * legacy"). Se arma con los movimientos del crédito en ese día y sus cuotas:
 *   - resumen: "Cuota 8 de 12", "Cuotas 8 y 9 de 48", "Cuota 1 de 1 (solo
 *     interés)", más "cancela el crédito" o "abono parcial, queda S/ …";
 *   - desglose: "Capital 625.00 · Interés 187.50 · Mora 5.80";
 *   - situacion: "Atraso 15 días" / "Adelantado 3 días" / "Al día" según el
 *     vencimiento de la cuota más antigua que tocó.
 * Si no se reconoce ninguna cuota (pagos migrados sin número), queda el
 * detalle legacy limpio ("Pago : 28423 Cuota: 1/1" → "Cuota: 1/1").
 *
 * Rendimiento (23/10, Caja General 1 en el droplet: 0.52 → 1.21 s por mes):
 * se leen los valores CRUDOS de pagos, créditos y cuotas (sin los casts de
 * Eloquent a Carbon y a decimal, que eran lo caro) y las cuotas se traen con
 * cuotasTocadas(): solo las que tocan los pagos, en filas planas.
 */
final class MotivoCobro
{
    private const COLUMNAS_CUOTA = ['id', 'credit_id', 'num_cuota', 'fecha_vencimiento', 'importe_cuota', 'importe_interes', 'importe_excedente', 'importe_aplicado', 'interes_aplicado', 'excedente_aplicado', 'pagado'];

    /**
     * @param  Collection<int, Payment|object>  $pagos  movimientos del crédito en el día (sin los 'Gat')
     * @param  array<int, CreditInstallment|object>  $cuotasPorId  cuotas del crédito por id
     * @param  array<int, CreditInstallment|object>  $cuotasPorNum  cuotas del crédito por num_cuota
     * @return array{resumen: string, desglose: string, situacion: string, texto: string}
     */
    public static function de(Collection $pagos, ?Credit $credit, array $cuotasPorId, array $cuotasPorNum): array
    {
        $sumas = ['capital' => 0.0, 'interes' => 0.0, 'mora' => 0.0, 'excedente' => 0.0];
        $tocadas = [];
        $cred = $credit !== null ? self::crudo($credit) : [];
        $totalCuotas = ! empty($cred['cuotas']) ? (int) $cred['cuotas'] : null;
        $fecha = null;
        $primerDetalle = null;

        foreach ($pagos as $p) {
            $a = self::crudo($p);
            $primerDetalle ??= (string) ($a['detalle'] ?? '');
            $fecha ??= self::dia($a['fecha'] ?? null);
            $tipo = self::clase((string) ($a['tipo'] ?? ''), (string) ($a['documento'] ?? ''));
            $sumas[$tipo] = round($sumas[$tipo] + (float) ($a['monto'] ?? 0), 2);
            if ($tipo === 'mora') {
                continue;
            }
            $cuota = null;
            $insId = (int) ($a['installment_id'] ?? 0);
            if ($insId > 0 && isset($cuotasPorId[$insId])) {
                $cuota = self::cuota($cuotasPorId[$insId]);
            } elseif (preg_match('/(\d+)\s*\/\s*(\d+)/', (string) ($a['detalle'] ?? ''), $m)) {
                $cuota = isset($cuotasPorNum[(int) $m[1]]) ? self::cuota($cuotasPorNum[(int) $m[1]]) : null;
                $totalCuotas ??= (int) $m[2];
                if ($cuota === null) {
                    $tocadas[(int) $m[1]] = null;
                }
            }
            if ($cuota !== null) {
                $tocadas[$cuota['num']] = $cuota;
            }
        }
        ksort($tocadas);

        $desglose = implode(' · ', array_filter([
            $sumas['capital'] > 0 ? 'Capital '.number_format($sumas['capital'], 2) : null,
            $sumas['interes'] > 0 ? 'Interés '.number_format($sumas['interes'], 2) : null,
            $sumas['mora'] > 0 ? 'Mora '.number_format($sumas['mora'], 2) : null,
            $sumas['excedente'] > 0 ? 'Excedente '.number_format($sumas['excedente'], 2) : null,
        ]));

        if ($tocadas === []) {
            $resumen = $sumas['mora'] > 0 && $sumas['capital'] == 0 && $sumas['interes'] == 0
                ? 'Solo mora'
                : self::legacy((string) $primerDetalle);
            $situacion = '';
        } else {
            $resumen = ucfirst(RotuloCuotas::de(array_keys($tocadas))).($totalCuotas ? ' de '.$totalCuotas : '');
            if ($sumas['capital'] == 0 && $sumas['interes'] > 0) {
                $resumen .= ' (solo interés)';
            }
            $cancelaHoy = $fecha !== null && self::dia($cred['fecha_cancelacion'] ?? null) === $fecha;
            if ($cancelaHoy) {
                $resumen .= ' · cancela el crédito';
            } else {
                $parciales = array_filter($tocadas, fn (?array $c) => $c !== null && ! $c['pagado'] && $c['aplicado'] > 0.001);
                if ($parciales !== []) {
                    $queda = round(array_sum(array_map(fn (array $c) => $c['total'] - $c['aplicado'], $parciales)), 2);
                    $resumen .= ' · abono parcial, queda S/ '.number_format($queda, 2);
                }
            }
            $situacion = self::situacion($tocadas, $fecha);
        }

        return [
            'resumen' => $resumen,
            'desglose' => $desglose,
            'situacion' => $situacion,
            'texto' => implode(' · ', array_filter([$resumen, $desglose, $situacion])),
        ];
    }

    /**
     * Las cuotas que tocan los pagos —por installment_id o, en los migrados, por
     * el "n/N" del detalle— en filas planas (sin modelos), indexadas por crédito:
     * [credit_id => [porId, porNum]]. Es lo que de() necesita; traer todas las
     * cuotas de los créditos multiplicaba las filas por diez.
     *
     * @param  Collection<int, Payment|object>  $pagos
     * @return array<int, array{0: array<int, object>, 1: array<int, object>}>
     */
    public static function cuotasTocadas(Collection $pagos): array
    {
        $ids = [];
        $pares = [];
        foreach ($pagos as $p) {
            $a = self::crudo($p);
            if (! empty($a['installment_id'])) {
                $ids[(int) $a['installment_id']] = true;
            } elseif (preg_match('/(\d+)\s*\/\s*(\d+)/', (string) ($a['detalle'] ?? ''), $m)) {
                $pares[(int) $a['credit_id']][(int) $m[1]] = (int) $m[1];
            }
        }

        $filas = [];
        foreach (array_chunk(array_keys($ids), 1000) as $lote) {
            array_push($filas, ...DB::table('credit_installments')->whereIn('id', $lote)->get(self::COLUMNAS_CUOTA)->all());
        }
        foreach (array_chunk($pares, 200, true) as $lote) {
            $consulta = DB::table('credit_installments')->where(function ($q) use ($lote) {
                foreach ($lote as $creditId => $nums) {
                    $q->orWhere(fn ($w) => $w->where('credit_id', $creditId)->whereIn('num_cuota', array_values($nums)));
                }
            });
            array_push($filas, ...$consulta->get(self::COLUMNAS_CUOTA)->all());
        }

        $indices = [];
        foreach ($filas as $f) {
            $indices[(int) $f->credit_id][0][(int) $f->id] = $f;
            $indices[(int) $f->credit_id][1][(int) $f->num_cuota] = $f;
        }

        return $indices;
    }

    /** Valores crudos (sin casts) de un modelo o de una fila de DB::table. */
    private static function crudo(object $o): array
    {
        return $o instanceof Model ? $o->getAttributes() : get_object_vars($o);
    }

    /** 'Y-m-d' de una fecha cruda ('2026-10-22' o '2026-10-22 00:00:00'); null si no hay. */
    private static function dia(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : substr($valor, 0, 10);
    }

    /** @return array{num: int, venc: ?string, total: float, aplicado: float, pagado: bool} */
    private static function cuota(object $c): array
    {
        $a = self::crudo($c);

        return [
            'num' => (int) $a['num_cuota'],
            'venc' => self::dia($a['fecha_vencimiento'] ?? null),
            'total' => (float) ($a['importe_cuota'] ?? 0) + (float) ($a['importe_interes'] ?? 0) + (float) ($a['importe_excedente'] ?? 0),
            'aplicado' => (float) ($a['importe_aplicado'] ?? 0) + (float) ($a['interes_aplicado'] ?? 0) + (float) ($a['excedente_aplicado'] ?? 0),
            'pagado' => (bool) ($a['pagado'] ?? false),
        ];
    }

    /** capital | interes | mora | excedente, con la mora reconocida también por el documento (legacy). */
    private static function clase(string $tipo, string $documento): string
    {
        $tipo = strtoupper($tipo);
        if ($tipo === 'MORA' || str_starts_with(strtoupper($documento), 'MORA')) {
            return 'mora';
        }

        return match ($tipo) {
            'INTERES' => 'interes',
            'EXCEDENTE' => 'excedente',
            default => 'capital',
        };
    }

    /** Atraso o adelanto respecto al vencimiento de la cuota tocada más antigua. */
    private static function situacion(array $tocadas, ?string $fecha): string
    {
        $venc = null;
        foreach ($tocadas as $c) {
            if ($c !== null && $c['venc'] !== null) {
                $venc = $c['venc'];
                break;
            }
        }
        if ($venc === null || $fecha === null) {
            return '';
        }
        $dias = intdiv(strtotime($fecha.' 00:00:00 UTC') - strtotime($venc.' 00:00:00 UTC'), 86400);

        return match (true) {
            $dias > 0 => 'Atraso '.$dias.' '.($dias === 1 ? 'día' : 'días'),
            $dias < 0 => 'Adelantado '.abs($dias).' '.(abs($dias) === 1 ? 'día' : 'días'),
            default => 'Al día',
        };
    }

    /** "Pago : 28423 Cuota: 1/1" → "Cuota: 1/1". */
    private static function legacy(string $detalle): string
    {
        $limpio = trim((string) preg_replace('/^\s*Pago\s*:\s*\d+\s*/i', '', $detalle));

        return $limpio !== '' ? $limpio : trim($detalle);
    }
}
