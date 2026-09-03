<?php

namespace App\Livewire\Legal\Caja;

use App\Models\Expense;
use App\Models\Income;
use App\Models\SigmAviso;
use App\Models\TramiteNotarial;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tablero de la Caja Legal (Área Legal — FASE 6).
 *
 * Fusiona los movimientos con caja=4 de las tablas incomes y expenses del mes
 * elegido en una sola línea de tiempo homogénea (ingresos y egresos), con
 * totales del mes en cabecera y la columna ORIGEN que enlaza cada egreso
 * generado por un documento legal (sigm_avisos.expense_id → garantía,
 * tramites_notariales.expense_id → notaría); el resto son asientos manuales.
 *
 * El alta manual (tarifas cobradas, gastos sueltos del área) vive en el
 * componente hijo MovimientoModal; este tablero escucha 'movimiento-guardado'
 * para refrescarse. Los asientos legales NO se editan desde las pantallas de
 * caja operativa (EditIncome/EditExpense abortan 403 con caja=4): se gestionan
 * desde su documento de origen.
 */
class Index extends Component
{
    /** Nombres de mes en español (config app.locale puede estar en 'en'). */
    private const MESES_ES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    // ── Filtros (persisten en la URL) ─────────────────────────────────

    /** Mes mostrado, formato Y-m (default: mes actual). */
    #[Url(as: 'mes', except: '')]
    public string $mes = '';

    /** Búsqueda por motivo (reason) o detalle (detail). */
    #[Url(as: 'buscar', except: '')]
    public $buscar = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }
    }

    /** El hijo MovimientoModal avisa que registró un asiento: re-render. */
    #[On('movimiento-guardado')]
    public function refrescar(): void
    {
        // Sin cuerpo: recibir el evento ya fuerza el re-render del tablero.
    }

    /** Primer día del mes filtrado (con red de seguridad si la URL trae basura). */
    private function periodo(): Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m', $this->mes)->startOfMonth();
    }

    public function render()
    {
        $inicio = $this->periodo();
        $fin = $inicio->copy()->endOfMonth();
        $rango = [$inicio->toDateString(), $fin->toDateString()];

        // ── Tarjetas: totales del MES completo (la búsqueda no las altera) ──
        $totalIngresos = (float) Income::where('caja', 4)->whereBetween('date', $rango)->sum('total');
        $totalEgresos = (float) Expense::where('caja', 4)->whereBetween('date', $rango)->sum('total');
        $neto = round($totalIngresos - $totalEgresos, 2);

        // ── Listado del mes (ambas tablas), con búsqueda por motivo/detalle ──
        $term = trim((string) $this->buscar);
        $filtroTexto = function ($q) use ($term) {
            if ($term !== '') {
                $q->where(function ($qq) use ($term) {
                    $qq->where('reason', 'like', "%{$term}%")
                        ->orWhere('detail', 'like', "%{$term}%");
                });
            }
        };

        $ingresos = Income::with('user:id,name')
            ->where('caja', 4)->whereBetween('date', $rango)
            ->tap($filtroTexto)
            ->get(['id', 'date', 'reason', 'detail', 'total', 'user_id']);

        $egresos = Expense::with('user:id,name')
            ->where('caja', 4)->whereBetween('date', $rango)
            ->tap($filtroTexto)
            ->get(['id', 'date', 'reason', 'detail', 'total', 'user_id']);

        // ── ORIGEN de los egresos: un mapa expense_id => documento por gancho ──
        $expenseIds = $egresos->pluck('id')->all();
        $avisosPorExpense = collect();
        $tramitesPorExpense = collect();
        if ($expenseIds !== []) {
            $avisosPorExpense = SigmAviso::whereIn('expense_id', $expenseIds)
                ->get(['id', 'garantia_id', 'expense_id'])->keyBy('expense_id');
            $tramitesPorExpense = TramiteNotarial::whereIn('expense_id', $expenseIds)
                ->get(['id', 'expense_id'])->keyBy('expense_id');
        }

        // ── Colección homogénea {tipo, id, date, reason, detail, total, user, origen} ──
        $movimientos = $ingresos
            ->map(fn ($i) => [
                'tipo' => 'ingreso',
                'id' => $i->id,
                'date' => $i->date,
                'reason' => (string) $i->reason,
                'detail' => (string) $i->detail,
                'total' => (float) $i->total,
                'user' => $i->user?->name,
                'origen' => null, // los ganchos legales solo apuntan a expenses
            ])
            ->concat($egresos->map(function ($e) use ($avisosPorExpense, $tramitesPorExpense) {
                $origen = null;
                if ($aviso = $avisosPorExpense->get($e->id)) {
                    $origen = ['tipo' => 'aviso', 'id' => $aviso->id, 'garantia_id' => $aviso->garantia_id];
                } elseif ($tramite = $tramitesPorExpense->get($e->id)) {
                    $origen = ['tipo' => 'tramite', 'id' => $tramite->id];
                }

                return [
                    'tipo' => 'egreso',
                    'id' => $e->id,
                    'date' => $e->date,
                    'reason' => (string) $e->reason,
                    'detail' => (string) $e->detail,
                    'total' => (float) $e->total,
                    'user' => $e->user?->name,
                    'origen' => $origen,
                ];
            }))
            ->sortBy([['date', 'desc'], ['id', 'desc']])
            ->values();

        // ── Selector de mes: los últimos 18 meses ──
        $meses = collect(range(0, 17))
            ->map(fn ($i) => now()->startOfMonth()->subMonths($i))
            ->mapWithKeys(fn (Carbon $m) => [
                $m->format('Y-m') => self::MESES_ES[$m->month].' '.$m->year,
            ]);
        // Si la URL trae un mes válido fuera del rango, que el select no lo pierda
        if (! $meses->has($this->mes)) {
            $meses->put($this->mes, self::MESES_ES[$inicio->month].' '.$inicio->year);
        }

        return view('livewire.legal.caja.index', compact(
            'movimientos', 'totalIngresos', 'totalEgresos', 'neto', 'meses',
        ));
    }
}
