<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Excel 2da categoría" (30/10/2026, Antony): la réplica del Excel manual "2-cat-prestamoh" con el que se
 * declara la renta de segunda categoría del mes (5 % del interés a la SUNAT).
 *
 * Reglas (confirmadas por Antony el 30/10): sale del CRONOGRAMA, no de los créditos cancelados reales: por
 * cada crédito, las cuotas que vencen en el mes ("lo que debieron cancelarse y lo que se cancelaron"), pagadas
 * o no, aunque el crédito no se haya cancelado. Si el crédito se canceló a mitad de mes, solo las cuotas que
 * vencían hasta la cancelación (opción A); si se canceló antes de que le venciera ninguna cuota del mes, cuenta
 * la cuota en curso (la siguiente a la cancelación, en los mensuales la del mes siguiente) en el mes de la
 * cancelación, con su capital, SIN interés y con la fecha de la cancelación (30/10, Antony, caso Chaname: canceló
 * el 02/09 antes de su cuota del 07/09 → "que quede en el 2 de septiembre, que no considere los intereses"). Después de la cancelación el crédito ya no aparece. Descripción vacía. Entran los clientes con
 * T. Crédito (clients.zona) "SIGM…", "Gar. Hip…" o "Garantia Mobiliaria…".
 *
 * Por fila: Préstamo = capital de esas cuotas según el cronograma (el "=375*3" del Excel manual), Interés S/ =
 * su interés, Interés % = Interés ÷ Capital del crédito, Fec/Venc = la última de esas cuotas y Fecha Cancelado =
 * la fecha en que debió cancelarse según el cronograma, la misma (30/10, Antony; como el "=+Fec/Venc" del manual).
 * Renovaciones (cancelado por refinanciación, el mensual de 1 cuota que se renueva cada mes): NO entran, como en el
 * Excel manual (30/10, Antony: "si en el Excel dice 1,200 entonces por ahí va"; el manual omite 5 de las 7 de
 * septiembre y las otras 2 las lleva con créditos que en el sistema no existen así).
 *
 * Contra el Excel manual (ene–sep 2026) cuadra ~90 % de las filas; lo demás son créditos que el manual sigue
 * listando después de cancelados o reemplazados, cuentas a mano y un cliente que no está en el sistema.
 */
class RentaSegundaCategoria
{
    /** Prefijos de clients.zona (T. Crédito) que entran al reporte. */
    public const TIPOS_CREDITO = ['SIGM', 'GAR. HIP', 'GARANTIA MOBILIARIA'];

    /**
     * @return list<array{credito: int, dni: string, nombre: string, capital: float, prestamo: float, pct: float, interes: float, cuotas: int, fec_cred: ?string, fec_venc: string, fec_cancelado: string}>
     */
    public static function filas(int $anio, int $mes): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();

        $base = fn () => DB::table('credit_installments as i')
            ->join('credits as c', 'c.id', '=', 'i.credit_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->where('c.cancelado_por_refi', 0)
            ->where(function ($q) {
                foreach (self::TIPOS_CREDITO as $prefijo) {
                    $q->orWhereRaw('UPPER(TRIM(cl.zona)) LIKE ?', [$prefijo.'%']);
                }
            })
            ->orderBy('i.fecha_vencimiento')->orderBy('i.num_cuota')
            ->select(['i.credit_id', 'i.fecha_vencimiento', 'i.importe_cuota', 'i.importe_interes',
                'c.importe', 'c.fecha_prestamo', 'c.fecha_cancelacion', 'cl.documento', 'cl.apellido_pat', 'cl.apellido_mat', 'cl.nombre']);

        // Las que vencen en el mes; del cancelado a mitad de mes, solo hasta la cancelación (opción A).
        $cuotas = $base()
            ->whereBetween('i.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->where(fn ($q) => $q->where('c.situacion', '!=', 'Cancelado')
                ->orWhereNull('c.fecha_cancelacion')
                ->orWhereColumn('i.fecha_vencimiento', '<=', 'c.fecha_cancelacion'))
            ->get();

        // Cancelado en el mes antes de que le venciera ninguna cuota del mes: la cuota en curso (la siguiente a la
        // cancelación; en los mensuales, la del mes siguiente) va en el mes de la cancelación.
        $enCurso = $base()
            ->where('c.situacion', 'Cancelado')
            ->whereBetween('c.fecha_cancelacion', [$desde->toDateString(), $hasta->toDateString()])
            ->whereColumn('i.fecha_vencimiento', '>', 'c.fecha_cancelacion')
            ->whereNotIn('c.id', $cuotas->pluck('credit_id')->unique()->all() ?: [0])
            ->get()
            ->groupBy('credit_id')->map(fn (Collection $del) => (object) ((array) $del->first() + ['en_curso' => true]))->values();
        $cuotas = $cuotas->concat($enCurso);

        $filas = $cuotas->groupBy('credit_id')->map(function (Collection $del) {
            $c = $del->first();
            // Cuota en curso: va con la fecha de la cancelación y sin interés (no llegó a vencer).
            $enCurso = $c->en_curso ?? false;
            $vence = $enCurso ? substr((string) $c->fecha_cancelacion, 0, 10) : substr((string) $del->max('fecha_vencimiento'), 0, 10);
            $capital = round((float) $c->importe, 2);
            $interes = $enCurso ? 0.0 : round((float) $del->sum('importe_interes'), 2);

            return [
                'credito' => (int) $c->credit_id,
                'dni' => trim((string) $c->documento),
                'nombre' => trim(implode(' ', array_filter([$c->apellido_pat, $c->apellido_mat, $c->nombre]))),
                'capital' => $capital,
                'prestamo' => round((float) $del->sum('importe_cuota'), 2),
                'pct' => $capital > 0 ? round($interes / $capital * 100, 2) : 0.0,
                'interes' => $interes,
                'cuotas' => $del->count(),
                'fec_cred' => $c->fecha_prestamo ? substr((string) $c->fecha_prestamo, 0, 10) : null,
                'fec_venc' => $vence,
                'fec_cancelado' => $vence,
            ];
        });

        return $filas->sortBy([['fec_venc', 'asc'], ['credito', 'asc']])->values()->all();
    }

    /** "SEPTIEMBRE 2026" para el título. */
    public static function nombreMes(int $anio, int $mes): string
    {
        return ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'][$mes - 1].' '.$anio;
    }

    /** "Sep." para el nombre de la hoja, como en el Excel manual. */
    public static function nombreHoja(int $mes): string
    {
        return ['Enr.', 'Feb.', 'Mar.', 'Abr.', 'May.', 'Jun.', 'Jul.', 'Agt.', 'Sep.', 'Oct.', 'Nov.', 'Dic.'][$mes - 1];
    }
}
