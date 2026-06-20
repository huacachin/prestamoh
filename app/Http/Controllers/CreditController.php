<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index() { return view('credits.index'); }
    public function create(?int $clientId = null) { return view('credits.create', compact('clientId')); }
    public function show(int $id) { return view('credits.show', compact('id')); }
    public function schedule(int $id) { return view('credits.schedule', compact('id')); }
    public function edit(int $id) { return view('credits.edit', compact('id')); }
    public function activate() { return view('credits.activate'); }
    public function changeStatus() { return view('credits.change-status'); }
    public function massDelete() { return view('credits.mass-delete'); }
    public function massDeleteEdit(int $id) { return view('credits.mass-delete-edit', compact('id')); }
    public function export(Request $request)
    {
        $c = new \App\Livewire\Credits\Index();
        $c->nombre    = (string) $request->query('nombre', '');
        $c->codigo    = (string) $request->query('codigo', '');
        $c->ejecutivo = (string) $request->query('ejecutivo', '');
        $c->seletipl  = (string) $request->query('seletipl', '');

        $d = $c->render()->getData();

        return \App\Support\XlsResponse::make('exports.credits', [
            'credits'  => $d['credits'],
            'pagosMap' => $d['pagosMap'],
            'sumtotal' => $d['sumtotal'],
            'suminter' => $d['suminter'],
            'sumtotax' => $d['sumtotax'],
            'sumpagos' => $d['sumpagos'],
            'sumsaldo' => $d['sumsaldo'],
        ], 'Prestamos.xls');
    }

    public function exportSchedule(int $id)
    {
        $credit = \App\Models\Credit::with(['installments' => fn ($q) => $q->orderBy('num_cuota')])->find($id);

        $rows = collect();
        $totCap = 0; $totInt = 0; $totMora = 0; $totPag = 0;
        $dailyExtras = [];
        $sumMoraExtra = 0; $sumImpoExtra = 0;

        if ($credit) {
            $installments = $credit->installments;
            $rowCount = $installments->count();

            $rows = $installments->values()->map(function ($i) use (&$totCap, &$totInt, &$totMora, &$totPag) {
                $cap  = (float) $i->importe_cuota;
                $int  = (float) $i->importe_interes;
                $mora = (float) $i->importe_mora;
                $pag  = (float) $i->importe_aplicado + (float) $i->interes_aplicado;

                $totCap  += $cap;
                $totInt  += $int;
                $totMora += $mora;
                $totPag  += $pag;

                $color = '';
                if ($i->fecha_vencimiento) {
                    $dow = $i->fecha_vencimiento->dayOfWeek;
                    if ($dow === \Carbon\Carbon::SUNDAY)        $color = 'red';
                    elseif ($dow === \Carbon\Carbon::SATURDAY)  $color = 'green';
                }

                return (object) [
                    'num'        => $i->num_cuota,
                    'periodo'    => $i->fecha_vencimiento?->format('d/m/Y'),
                    'capital'    => $cap,
                    'interes'    => $int,
                    'total'      => $cap + $int,
                    'mora'       => $mora,
                    'pagado'     => $pag,
                    'fecha_pago' => $i->pagado ? $i->fecha_pago?->format('d/m/Y') : '',
                    'color'      => $color,
                ];
            });

            // Créditos diarios (tipoplani=4): pagos antes/después del cronograma
            if ((int) $credit->tipo_planilla === 4) {
                $minFec = $installments->min(fn ($i) => $i->fecha_vencimiento?->format('Y-m-d'));
                $maxFec = $installments->max(fn ($i) => $i->fecha_vencimiento?->format('Y-m-d'));

                if ($minFec && $maxFec) {
                    $base = fn () => \Illuminate\Support\Facades\DB::table('payments')
                        ->where('credit_id', $id)
                        ->where('documento', 'NOT LIKE', 'MORA%')
                        ->where(function ($q) { $q->whereNull('detalle')->orWhere('detalle', 'NOT LIKE', '%Gat'); });

                    $before = (clone $base())->where('fecha', '<', $minFec)
                        ->selectRaw('fecha, SUM(monto) tgm')->groupBy('fecha')->orderBy('fecha')->get();
                    $after = (clone $base())->where('fecha', '>', $maxFec)
                        ->selectRaw('fecha, SUM(monto) tgm')->groupBy('fecha')->orderBy('fecha')->get();

                    $tt = $rowCount;
                    foreach ($before->concat($after) as $p) {
                        $tt++;
                        $mora = (float) \Illuminate\Support\Facades\DB::table('payments')
                            ->where('credit_id', $id)
                            ->where('documento', 'LIKE', 'MORA%')
                            ->whereDate('fecha', $p->fecha)
                            ->sum('monto');

                        $dailyExtras[] = [
                            'num'    => $tt,
                            'mora'   => $mora,
                            'pagado' => (float) $p->tgm,
                            'fecha'  => \Carbon\Carbon::parse($p->fecha)->format('d/m/Y'),
                        ];
                        $sumImpoExtra += (float) $p->tgm;
                        $sumMoraExtra += $mora;
                    }
                }
            }
        }

        return \App\Support\XlsResponse::make('exports.schedule', [
            'rows'         => $rows,
            'totCap'       => $totCap,
            'totInt'       => $totInt,
            'totMora'      => $totMora,
            'totPag'       => $totPag,
            'dailyExtras'  => $dailyExtras,
            'sumMoraExtra' => $sumMoraExtra,
            'sumImpoExtra' => $sumImpoExtra,
        ], 'Cronograma.xls');
    }
}
