<?php

namespace App\Http\Controllers;

use App\Livewire\Payments\Daily;
use App\Livewire\Payments\Monthly;
use App\Livewire\Payments\Weekly;
use App\Models\Credit;
use App\Support\XlsResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return view('payments.index');
    }

    public function create(?int $creditId = null)
    {
        return view('payments.create', compact('creditId'));
    }

    public function daily()
    {
        return view('payments.daily');
    }

    public function weekly()
    {
        return view('payments.weekly');
    }

    public function monthly()
    {
        return view('payments.monthly');
    }

    public function refinance(int $creditId)
    {
        return view('payments.refinance', compact('creditId'));
    }

    public function export(Request $request)
    {
        $user = auth()->user();

        $nombre = (string) $request->query('nombre', '');
        $nombre1 = (string) $request->query('nombre1', '');
        $codigo = (string) $request->query('codigo1', '');
        $scopePropio = $user?->can('clientes.scope-propio') ?? false;

        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id'])
            ->where('situacion', '<>', 'Cancelado');

        if ($scopePropio && $user?->id) {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $user->id));
        }
        if (trim($nombre) !== '') {
            $t = trim($nombre);
            $query->whereHas('client', fn ($c) => $c->where('documento', 'like', "%{$t}%"));
        }
        if (trim($nombre1) !== '') {
            $t = trim($nombre1);
            $query->whereHas('client', function ($c) use ($t) {
                $c->where('nombre', 'like', "%{$t}%")
                    ->orWhere('apellido_pat', 'like', "%{$t}%")
                    ->orWhere('apellido_mat', 'like', "%{$t}%");
            });
        }
        if (trim($codigo) !== '') {
            $query->where('id', 'like', '%'.trim($codigo).'%');
        }

        $credits = $query->orderBy('id', 'asc')->get();

        return XlsResponse::make('exports.payments', [
            'credits' => $credits,
            'sumCapital' => (float) $credits->sum('importe'),
        ], 'Pagos.xls');
    }

    public function exportDaily(Request $request)
    {
        $c = new Daily;
        $c->ejecutivo = (string) $request->query('ejecutivo', 'Todos');
        $c->eestado = (string) $request->query('eestado', 'Vigente');
        $c->codio1 = (string) $request->query('codio1', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.payments.daily', [
            'rows' => $d['rows'],
            'tot' => $d['tot'],
            'sub' => $d['sub'],
            'morosidadPct' => $d['morosidadPct'],
            'activosPct' => $d['activosPct'],
        ], 'Reporte Credito Diario.xls');
    }

    public function exportWeekly(Request $request)
    {
        $c = new Weekly;
        $c->ejecutivo = (string) $request->query('ejecutivo', 'Todos');
        $c->eestado = (string) $request->query('eestado', 'Vigente');
        $c->codio1 = (string) $request->query('codio1', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.payments.weekly', [
            'rows' => $d['rows'],
            'tot' => $d['tot'],
            'sub' => $d['sub'],
            'morosidadPct' => $d['morosidadPct'],
            'activosPct' => $d['activosPct'],
        ], 'Reporte Credito Semanal.xls');
    }

    public function exportMonthly(Request $request)
    {
        $c = new Monthly;
        $c->ejecutivo = (string) $request->query('ejecutivo', 'Todos');
        $c->eestado = (string) $request->query('eestado', 'Vigente');
        $c->codio1 = (string) $request->query('codio1', '');

        $d = $c->render()->getData();

        return XlsResponse::make('exports.payments.monthly', [
            'rows' => $d['rows'],
            'tot' => $d['tot'],
            'sub' => $d['sub'],
            'morosidadPct' => $d['morosidadPct'],
            'activosPct' => $d['activosPct'],
        ], 'Reporte Credito Mensual.xls');
    }
}
