<?php

namespace App\Livewire\Dashboard;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $today      = Carbon::today();
        $yesterday  = $today->copy()->subDay();
        $monthStart = $today->copy()->startOfMonth();
        $period30   = $today->copy()->subDays(29);
        $period7    = $today->copy()->subDays(6);

        // ── KPIs principales ──────────────────────────────────────────
        $creditosActivos = Credit::where('situacion', 'Activo')->count();
        $totalCartera    = (float) Credit::where('situacion', 'Activo')->sum('importe');
        $cobranzaHoy     = (float) Payment::where('fecha', $today)->sum('monto');
        $cobranzaAyer    = (float) Payment::where('fecha', $yesterday)->sum('monto');
        $cobranzaMes     = (float) Payment::whereBetween('fecha', [$monthStart, $today])->sum('monto');
        $ingresosHoy     = (float) Income::where('date', $today)->sum('total');
        $egresosHoy      = (float) Expense::where('date', $today)->sum('total');
        $saldoCajaHoy    = $ingresosHoy - $egresosHoy;

        // Delta cobranza vs ayer
        $deltaCobranza = 0.0;
        if ($cobranzaAyer > 0) {
            $deltaCobranza = (($cobranzaHoy - $cobranzaAyer) / $cobranzaAyer) * 100;
        } elseif ($cobranzaHoy > 0) {
            $deltaCobranza = 100.0;
        }

        // ── Mora ──────────────────────────────────────────────────────
        $cuotasMoraQuery = CreditInstallment::where('pagado', false)
            ->where('fecha_vencimiento', '<', $today);

        $morosidad   = (clone $cuotasMoraQuery)->distinct('credit_id')->count('credit_id');
        $montoEnMora = (float) (clone $cuotasMoraQuery)
            ->selectRaw('COALESCE(SUM((importe_cuota + importe_interes) - (importe_aplicado + interes_aplicado)),0) as t')
            ->value('t');

        $morosidadPct = $creditosActivos > 0
            ? round(($morosidad / $creditosActivos) * 100, 1)
            : 0;

        // ── Cobranza últimos 30 días (serie para chart) ───────────────
        $rawSerie = Payment::whereBetween('fecha', [$period30, $today])
            ->selectRaw('DATE(fecha) as d, SUM(monto) as total')
            ->groupBy('d')->orderBy('d')->pluck('total', 'd');

        $cobranza30 = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $period30->copy()->addDays($i)->format('Y-m-d');
            $cobranza30[] = [
                'x' => $day,
                'y' => round((float) ($rawSerie[$day] ?? 0), 2),
            ];
        }

        // Mini sparkline 7 días para hero
        $spark7 = array_slice($cobranza30, -7);
        $spark7Vals = array_map(fn ($p) => $p['y'], $spark7);

        // ── Distribución de cartera por situación ─────────────────────
        $cartera = Credit::selectRaw('situacion, COUNT(*) as n, COALESCE(SUM(importe),0) as monto')
            ->groupBy('situacion')->get();

        // ── Cuotas vencidas (top alertas) ─────────────────────────────
        $alertasVencidas = CreditInstallment::with('credit.client')
            ->where('pagado', false)
            ->where('fecha_vencimiento', '<', $today)
            ->orderBy('fecha_vencimiento')
            ->take(6)
            ->get()
            ->map(function ($i) use ($today) {
                $saldo = ((float) $i->importe_cuota + (float) $i->importe_interes)
                       - ((float) $i->importe_aplicado + (float) $i->interes_aplicado);
                $dias = $i->fecha_vencimiento
                    ? (int) Carbon::parse($i->fecha_vencimiento)->diffInDays($today)
                    : 0;
                return (object) [
                    'cliente'   => $i->credit?->client?->fullName() ?? '—',
                    'credito'   => $i->credit_id,
                    'cuota'     => $i->num_cuota,
                    'fecha'     => $i->fecha_vencimiento,
                    'dias'      => $dias,
                    'saldo'     => round($saldo, 2),
                ];
            });

        // ── Listas existentes ─────────────────────────────────────────
        $ultimosPagos = Payment::with('credit.client')
            ->latest('fecha')->latest('id')
            ->take(8)->get();

        $creditosRecientes = Credit::with('client')
            ->latest('fecha_prestamo')->latest('id')
            ->take(6)->get();

        return view('livewire.dashboard.index', compact(
            'creditosActivos',
            'totalCartera',
            'cobranzaHoy',
            'cobranzaAyer',
            'cobranzaMes',
            'deltaCobranza',
            'morosidad',
            'morosidadPct',
            'montoEnMora',
            'ingresosHoy',
            'egresosHoy',
            'saldoCajaHoy',
            'cobranza30',
            'spark7Vals',
            'cartera',
            'alertasVencidas',
            'ultimosPagos',
            'creditosRecientes',
        ));
    }
}
