<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resumen de un crédito tal como lo cuentan las pantallas (23/10/2026):
 * saldo por cronograma (como el cobro), cuotas pagadas / vencidas /
 * amortizadas (reglas del cronograma), días de atraso y mora teórica de hoy
 * desde la cuota impaga más antigua (Payments\Create::moraCalcAt), mora ya
 * pagada (por documento, como el cronograma), último cobro y enlaces. Un
 * crédito que no está Activo (cancelado, refinanciado) no acumula atraso ni
 * mora: ninguna pantalla se los calcula.
 */
final class ResumenCredito
{
    public static function de(Credit $credit, bool $conPagos = true): array
    {
        $hoy = Carbon::today();
        $activo = $credit->situacion === 'Activo';
        $cuotas = $credit->relationLoaded('installments') ? $credit->installments : $credit->installments()->orderBy('num_cuota')->get();
        $cuotas = $cuotas->sortBy('num_cuota')->values();

        $pagadas = $cuotas->filter(fn (CreditInstallment $c) => (bool) $c->pagado);
        $impagas = $cuotas->filter(fn (CreditInstallment $c) => ! $c->pagado);
        $vencidas = $activo ? $impagas->filter(fn (CreditInstallment $c) => $c->fecha_vencimiento !== null && $c->fecha_vencimiento->lt($hoy)) : collect();
        $amortizadas = $impagas->filter(fn (CreditInstallment $c) => self::aplicado($c) > 0.001);

        $interesTotal = round((float) $credit->importe * (float) $credit->interes / 100, 2);
        $saldo = round($cuotas->sum(fn (CreditInstallment $c) => self::total($c) - self::aplicado($c)), 2);
        $pagadoCapital = round((float) $cuotas->sum('importe_aplicado'), 2);
        $pagadoInteres = round((float) $cuotas->sum('interes_aplicado'), 2);
        $pagadoExcedente = round((float) $cuotas->sum('excedente_aplicado'), 2);

        $mora = null;
        $diasAtraso = 0;
        $proxima = null;
        if ($activo) {
            $primeraImpaga = $impagas->filter(fn (CreditInstallment $c) => (float) $c->importe_cuota > 0)->sortBy('fecha_vencimiento')->first();
            $proxima = $impagas->filter(fn (CreditInstallment $c) => $c->fecha_vencimiento !== null && $c->fecha_vencimiento->gte($hoy))->sortBy('fecha_vencimiento')->first();
            if ($primeraImpaga !== null && $primeraImpaga->fecha_vencimiento !== null) {
                $diasAtraso = max(0, (int) floor($primeraImpaga->fecha_vencimiento->diffInDays($hoy, false)));
                $porDia = $credit->moraDiaria(self::total($primeraImpaga));
                $mora = ['dias' => $diasAtraso, 'por_dia' => $porDia, 'monto' => round($diasAtraso * $porDia, 2)];
            }
        }

        $cerradoPorRefi = (bool) $credit->cancelado_por_refi;
        $resumen = [
            'credito' => $credit->id,
            'situacion' => $credit->situacion,
            'tipo' => $credit->tipoPlanillaLabel(),
            'fecha_prestamo' => self::fecha($credit->fecha_prestamo),
            'fecha_vencimiento' => self::fecha($credit->fecha_vencimiento),
            'fecha_cancelacion' => self::fecha($credit->fecha_cancelacion),
            // Un refinanciamiento deja DOS créditos con la marca: el cerrado (cancelado_por_refi) y el nuevo (idcan = el anterior).
            'cerrado_por_refinanciamiento' => $cerradoPorRefi,
            'credito_anterior_que_refinancio' => ((bool) $credit->refinanciado && ! $cerradoPorRefi && $credit->idcan) ? (int) $credit->idcan : null,
            'credito_que_lo_reemplaza' => $cerradoPorRefi ? Credit::where('idcan', $credit->id)->where('id', '<>', $credit->id)->orderByDesc('id')->value('id') : null,
            'capital' => round((float) $credit->importe, 2),
            'interes_pct' => (float) $credit->interes,
            'interes_total' => $interesTotal,
            'total_credito' => round((float) $credit->importe + $interesTotal, 2),
            'cuotas' => (int) $credit->cuotas,
            'cuotas_pagadas' => $pagadas->count(),
            'cuotas_pendientes' => $impagas->count(),
            'cuotas_vencidas' => $vencidas->count(),
            'cuotas_amortizadas' => $amortizadas->count(),
            'pagado_capital' => $pagadoCapital,
            'pagado_interes' => $pagadoInteres,
            'pagado_total' => round($pagadoCapital + $pagadoInteres + $pagadoExcedente, 2),
            'saldo' => $saldo,
            'dias_atraso' => $diasAtraso,
            'primera_cuota_vencida' => $vencidas->isEmpty() ? null : self::cuotaBreve($vencidas->sortBy('fecha_vencimiento')->first(), $hoy),
            'proxima_cuota' => $proxima === null ? null : self::cuotaBreve($proxima, $hoy),
            'mora_hoy' => $mora,
            'asesor' => $credit->client?->asesor?->username ?? $credit->client?->asesor?->name,
            'enlaces' => [
                'cronograma' => route('credits.schedule', $credit->id),
                'cobrar' => route('payments.create', $credit->id),
            ],
        ];
        if (! $activo) {
            $resumen['nota'] = 'Crédito '.mb_strtolower((string) $credit->situacion).($cerradoPorRefi ? ' por refinanciamiento' : '').': no acumula atraso ni mora; el saldo del cronograma es solo referencial.';
        }

        if ($conPagos) {
            $resumen['mora_pagada'] = self::moraPagada($credit->id);
            $resumen['mora_acumulada_registrada'] = round((float) DB::table('mora_acumulada')->where('credit_id', $credit->id)->sum('importe'), 2);
            $ultimo = MassDeletion::where('credit_id', $credit->id)->orderByDesc('date')->orderByDesc('time')->first();
            $resumen['ultimo_pago'] = $ultimo === null ? null : [
                'fecha' => self::fecha($ultimo->date),
                'hora' => substr((string) $ultimo->time, 0, 5),
                'monto' => round((float) $ultimo->amount, 2),
                'recibo' => (int) $ultimo->id,
            ];
        }

        return $resumen;
    }

    /** Mora cobrada como la suma el cronograma: por el documento (MORA…), sin los movimientos 'Gat'. */
    public static function moraPagada(int $creditId): float
    {
        return round((float) self::pagosSinGat($creditId)
            ->where(fn ($q) => $q->where('tipo', 'MORA')->orWhereRaw("UPPER(LEFT(documento, 4)) = 'MORA'"))
            ->sum('monto'), 2);
    }

    /** Pagos del crédito sin los asientos de garantía ('Gat'), como filtran todas las pantallas. */
    public static function pagosSinGat(int $creditId)
    {
        return Payment::where('credit_id', $creditId)
            ->where(fn ($q) => $q->whereNull('detalle')->orWhereRaw("RIGHT(detalle, 3) <> 'Gat'"));
    }

    /** Fila del cronograma como la ve la pantalla: estado, pagado, saldo y días de atraso. */
    public static function fila(CreditInstallment $c, ?Carbon $hoy = null, bool $activo = true): array
    {
        $hoy ??= Carbon::today();
        $vencida = $activo && ! $c->pagado && $c->fecha_vencimiento !== null && $c->fecha_vencimiento->lt($hoy);
        $estado = match (true) {
            (bool) $c->pagado => 'Pagada',
            $vencida => self::aplicado($c) > 0.001 ? 'Vencida (amortizada)' : 'Vencida',
            self::aplicado($c) > 0.001 => 'Amortizada',
            default => 'Pendiente',
        };

        return [
            'numero' => (int) $c->num_cuota,
            'vencimiento' => self::fecha($c->fecha_vencimiento),
            'capital' => round((float) $c->importe_cuota, 2),
            'interes' => round((float) $c->importe_interes, 2),
            'total' => round(self::total($c), 2),
            'pagado' => round(self::aplicado($c), 2),
            'saldo' => round(self::total($c) - self::aplicado($c), 2),
            'estado' => $estado,
            'dias_atraso' => $vencida ? (int) floor($c->fecha_vencimiento->diffInDays($hoy, false)) : 0,
        ];
    }

    public static function total(CreditInstallment $c): float
    {
        return (float) $c->importe_cuota + (float) $c->importe_interes + (float) $c->importe_excedente;
    }

    public static function aplicado(CreditInstallment $c): float
    {
        return (float) $c->importe_aplicado + (float) $c->interes_aplicado + (float) $c->excedente_aplicado;
    }

    public static function fecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        return ($fecha instanceof Carbon ? $fecha : Carbon::parse((string) $fecha))->format('d/m/Y');
    }

    private static function cuotaBreve(CreditInstallment $c, Carbon $hoy): array
    {
        $fila = self::fila($c, $hoy);

        return ['numero' => $fila['numero'], 'vencimiento' => $fila['vencimiento'], 'total' => $fila['total'], 'saldo' => $fila['saldo'], 'dias_atraso' => $fila['dias_atraso']];
    }

    /**
     * Créditos de un cliente con su resumen: los activos (todos) o los no
     * activos (los $tope más recientes), los más nuevos arriba.
     */
    public static function deCliente(int $clientId, bool $activos, int $tope = 10): Collection
    {
        return Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota'), 'client.asesor:id,name,username'])
            ->where('client_id', $clientId)
            ->where('situacion', $activos ? '=' : '<>', 'Activo')
            ->orderByDesc('fecha_prestamo')->orderByDesc('id')
            ->limit($activos ? 50 : $tope)->get()
            ->map(fn (Credit $c) => self::de($c));
    }
}
