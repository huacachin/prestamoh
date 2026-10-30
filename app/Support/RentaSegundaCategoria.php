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
 * o no. Si el crédito se canceló a mitad de mes, solo las cuotas que vencían hasta la cancelación (opción A):
 * el interés de las siguientes no se cobra; después de la cancelación el crédito ya no aparece. Descripción
 * vacía. Entran los clientes con T. Crédito (clients.zona) "SIGM…" o "Gar. Hip…" (garantía mobiliaria o
 * hipotecaria), sin los cancelados por refinanciación (su capital pasó al crédito nuevo; el Excel manual
 * tampoco los lista). Con esta regla cuadra el ~90 % de las filas del Excel manual de enero, mayo y
 * septiembre; el resto eran omisiones o cuentas a mano.
 *
 * Por fila: Préstamo = capital de esas cuotas (el "=375*3" del Excel), Interés S/ = su interés,
 * Interés % = Interés ÷ Capital del crédito (la tasa por cuota × cuotas), Fec/Venc = la última que vence en
 * el mes y Fecha Cancelado = cuándo se pagó la última (vacía si alguna sigue impaga).
 */
class RentaSegundaCategoria
{
    /** Prefijos de clients.zona (T. Crédito) que entran al reporte. */
    public const TIPOS_CREDITO = ['SIGM', 'GAR. HIP'];

    /**
     * @return list<array{credito: int, dni: string, nombre: string, capital: float, prestamo: float, pct: float, interes: float, cuotas: int, fec_cred: ?string, fec_venc: string, fec_cancelado: ?string}>
     */
    public static function filas(int $anio, int $mes): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();

        $cuotas = DB::table('credit_installments as i')
            ->join('credits as c', 'c.id', '=', 'i.credit_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->whereBetween('i.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            // Refinanciados fuera: el capital no se pagó, pasó al crédito nuevo (el Excel manual no los lista).
            ->where('c.cancelado_por_refi', 0)
            // Cancelado a mitad de mes: solo lo que vencía hasta la cancelación.
            ->where(fn ($q) => $q->where('c.situacion', '!=', 'Cancelado')
                ->orWhereNull('c.fecha_cancelacion')
                ->orWhereColumn('i.fecha_vencimiento', '<=', 'c.fecha_cancelacion'))
            ->where(function ($q) {
                foreach (self::TIPOS_CREDITO as $prefijo) {
                    $q->orWhereRaw('UPPER(TRIM(cl.zona)) LIKE ?', [$prefijo.'%']);
                }
            })
            ->orderBy('i.fecha_vencimiento')->orderBy('i.num_cuota')
            ->get(['i.id', 'i.credit_id', 'i.num_cuota', 'i.fecha_vencimiento', 'i.importe_cuota', 'i.importe_interes', 'i.pagado', 'i.fecha_pago',
                'c.importe', 'c.fecha_prestamo', 'c.situacion', 'c.fecha_cancelacion',
                'cl.documento', 'cl.apellido_pat', 'cl.apellido_mat', 'cl.nombre']);

        $fechasDePago = self::fechasDePago($cuotas->pluck('credit_id')->unique()->values());

        $filas = $cuotas->groupBy('credit_id')->map(function (Collection $del) use ($fechasDePago) {
            $c = $del->first();
            $capital = round((float) $c->importe, 2);
            $interes = round((float) $del->sum('importe_interes'), 2);
            $cancelado = $c->situacion === 'Cancelado' && $c->fecha_cancelacion !== null;
            // Fecha Cancelado: el pago de la última cuota del mes; las que el legacy dejó sin marcar en un
            // crédito cancelado se pagaron en la cancelación. Si alguna sigue impaga, va vacía.
            $pagos = $del->map(function (object $i) use ($fechasDePago, $cancelado, $c) {
                if ((int) $i->pagado === 1) {
                    return $fechasDePago[$i->credit_id][$i->num_cuota] ?? substr((string) $i->fecha_pago, 0, 10) ?: null;
                }

                return $cancelado ? substr((string) $c->fecha_cancelacion, 0, 10) : null;
            });

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
                'fec_venc' => substr((string) $del->max('fecha_vencimiento'), 0, 10),
                'fec_cancelado' => $pagos->contains(null) ? null : $pagos->max(),
            ];
        });

        return $filas->sortBy([['fec_venc', 'asc'], ['credito', 'asc']])->values()->all();
    }

    /**
     * Fecha del último pago de cada cuota (sin mora), por los pagos que la nombran: installment_id o el
     * "n/N" del detalle en los migrados del legacy.
     *
     * @param  Collection<int, int>  $creditIds
     * @return array<int, array<int, string>> [credit_id][num_cuota] => 'Y-m-d'
     */
    private static function fechasDePago(Collection $creditIds): array
    {
        if ($creditIds->isEmpty()) {
            return [];
        }
        $numPorId = DB::table('credit_installments')->whereIn('credit_id', $creditIds)->pluck('num_cuota', 'id');
        $fechas = [];
        DB::table('payments')->whereIn('credit_id', $creditIds)->whereIn('tipo', ['CAPITAL', 'INTERES', 'EXCEDENTE'])
            ->orderBy('fecha')
            ->get(['credit_id', 'installment_id', 'detalle', 'fecha'])
            ->each(function (object $p) use (&$fechas, $numPorId) {
                $num = $p->installment_id ? ($numPorId[$p->installment_id] ?? null) : null;
                if ($num === null && preg_match('/(\d+)\s*\/\s*\d+/', (string) $p->detalle, $m)) {
                    $num = (int) $m[1];
                }
                if ($num !== null) {
                    $fechas[$p->credit_id][(int) $num] = substr((string) $p->fecha, 0, 10);
                }
            });

        return $fechas;
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
