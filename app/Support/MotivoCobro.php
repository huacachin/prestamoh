<?php

namespace App\Support;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "Motivo" de un cobro en el reporte de pagos (23/10/2026, Antony: "hay que
 * detallarlo más; esa descripción viene del legacy"). Se arma con los
 * movimientos del crédito en ese día y sus cuotas:
 *   - resumen: "Cuota 8 de 12", "Cuotas 8 y 9 de 48", "Cuota 1 de 1 (solo
 *     interés)", más "cancela el crédito" o "abono parcial, queda S/ …";
 *   - desglose: "Capital 625.00 · Interés 187.50 · Mora 5.80";
 *   - situacion: "Atraso 15 días" / "Adelantado 3 días" / "Al día" según el
 *     vencimiento de la cuota más antigua que tocó.
 * Si no se reconoce ninguna cuota (pagos migrados sin número), queda el
 * detalle legacy limpio ("Pago : 28423 Cuota: 1/1" → "Cuota: 1/1").
 */
final class MotivoCobro
{
    /**
     * @param  Collection<int, Payment>  $pagos  movimientos del crédito en el día (sin los 'Gat')
     * @param  array<int, CreditInstallment>  $cuotasPorId  cuotas del crédito por id
     * @param  array<int, CreditInstallment>  $cuotasPorNum  cuotas del crédito por num_cuota
     * @return array{resumen: string, desglose: string, situacion: string, texto: string}
     */
    public static function de(Collection $pagos, ?Credit $credit, array $cuotasPorId, array $cuotasPorNum): array
    {
        $sumas = ['capital' => 0.0, 'interes' => 0.0, 'mora' => 0.0, 'excedente' => 0.0];
        $tocadas = [];
        $totalCuotas = $credit?->cuotas ? (int) $credit->cuotas : null;
        $fecha = null;

        foreach ($pagos as $p) {
            $fecha ??= $p->fecha instanceof Carbon ? $p->fecha->copy()->startOfDay() : Carbon::parse((string) $p->fecha)->startOfDay();
            $tipo = self::clase($p);
            $sumas[$tipo] = round($sumas[$tipo] + (float) $p->monto, 2);
            if ($tipo === 'mora') {
                continue;
            }
            $cuota = null;
            if ($p->installment_id && isset($cuotasPorId[(int) $p->installment_id])) {
                $cuota = $cuotasPorId[(int) $p->installment_id];
            } elseif (preg_match('/(\d+)\s*\/\s*(\d+)/', (string) $p->detalle, $m)) {
                $cuota = $cuotasPorNum[(int) $m[1]] ?? null;
                $totalCuotas ??= (int) $m[2];
                if ($cuota === null) {
                    $tocadas[(int) $m[1]] = null;
                }
            }
            if ($cuota !== null) {
                $tocadas[(int) $cuota->num_cuota] = $cuota;
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
                : self::legacy((string) ($pagos->first()?->detalle ?? ''));
            $situacion = '';
        } else {
            $resumen = ucfirst(RotuloCuotas::de(array_keys($tocadas))).($totalCuotas ? ' de '.$totalCuotas : '');
            if ($sumas['capital'] == 0 && $sumas['interes'] > 0) {
                $resumen .= ' (solo interés)';
            }
            $cancelaHoy = $credit?->fecha_cancelacion && $fecha !== null
                && Carbon::parse($credit->fecha_cancelacion)->startOfDay()->equalTo($fecha);
            if ($cancelaHoy) {
                $resumen .= ' · cancela el crédito';
            } else {
                $parciales = array_filter($tocadas, fn (?CreditInstallment $c) => $c !== null && ! $c->pagado && self::aplicado($c) > 0.001);
                if ($parciales !== []) {
                    $queda = round(array_sum(array_map(fn (CreditInstallment $c) => self::total($c) - self::aplicado($c), $parciales)), 2);
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

    /** capital | interes | mora | excedente, con la mora reconocida también por el documento (legacy). */
    private static function clase(Payment $p): string
    {
        $tipo = strtoupper((string) $p->tipo);
        if ($tipo === 'MORA' || str_starts_with(strtoupper((string) $p->documento), 'MORA')) {
            return 'mora';
        }

        return match ($tipo) {
            'INTERES' => 'interes',
            'EXCEDENTE' => 'excedente',
            default => 'capital',
        };
    }

    /** Atraso o adelanto respecto al vencimiento de la cuota tocada más antigua. */
    private static function situacion(array $tocadas, ?Carbon $fecha): string
    {
        $primera = null;
        foreach ($tocadas as $c) {
            if ($c instanceof CreditInstallment && $c->fecha_vencimiento !== null) {
                $primera = $c;
                break;
            }
        }
        if ($primera === null || $fecha === null) {
            return '';
        }
        $dias = (int) floor($primera->fecha_vencimiento->copy()->startOfDay()->diffInDays($fecha, false));

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

    private static function total(CreditInstallment $c): float
    {
        return (float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente;
    }

    private static function aplicado(CreditInstallment $c): float
    {
        return (float) $c->importe_aplicado + (float) $c->interes_aplicado + (float) $c->excedente_aplicado;
    }
}
