<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Support\XlsResponse;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function opening()
    {
        return view('cash.opening');
    }

    public function incomes()
    {
        return view('cash.incomes');
    }

    public function createIncome()
    {
        return view('cash.create-income');
    }

    public function editIncome(int $id)
    {
        return view('cash.edit-income', compact('id'));
    }

    public function incomeGallery(int $id)
    {
        return view('cash.income-gallery', compact('id'));
    }

    public function expenses()
    {
        return view('cash.expenses');
    }

    public function createExpense()
    {
        return view('cash.create-expense');
    }

    public function editExpense(int $id)
    {
        return view('cash.edit-expense', compact('id'));
    }

    public function expenseGallery(int $id)
    {
        return view('cash.expense-gallery', compact('id'));
    }

    public function exportIncomes(Request $request)
    {
        $user = auth()->user();

        $tipo = (string) $request->query('tipo', '1');
        $compra = (string) $request->query('compra', '');
        $fei = (string) $request->query('fei', now()->format('Y-m-d'));
        $fef = (string) $request->query('fef', now()->format('Y-m-d'));
        $crossHQ = $user?->can('acceso.cross-headquarter') ?? false;
        $hqId = $user?->headquarter_id ?? 1;
        $editarHistorico = $user?->canAny(['caja.editar-historico', 'caja.ver-todo']) ?? false;

        $term = trim($compra);

        $applyDate = function ($q, string $col) use ($term, $fei, $fef) {
            if ($term !== '' && ($fei === '' || $fef === '')) {
                return;
            }
            if ($fei !== '' && $fef !== '') {
                $q->where($col, '>=', $fei)->where($col, '<=', $fef);
            } else {
                $q->where($col, now()->format('Y-m-d'));
            }
        };

        $incQ = Income::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        if (! $crossHQ) {
            $incQ->where('headquarter_id', $hqId);
        }
        if (! $editarHistorico && $user?->id) {
            $incQ->where('user_id', $user->id)->where('reason', 'Diario');
        }
        $applyDate($incQ, 'date');
        if ($term !== '') {
            match ($tipo) {
                '1' => $incQ->where('reason', 'like', "%{$term}%"),
                '2' => $incQ->where('detail', 'like', "%{$term}%"),
                '3' => $incQ->where('asesor', 'like', "%{$term}%"),
                '4' => $incQ->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")),
                default => null,
            };
        }
        $incomes = $incQ->get();

        $payQ = Payment::query()
            ->with(['credit.client:id,nombre,apellido_pat,apellido_mat', 'user:id,name,username']);

        if (! $crossHQ) {
            $payQ->where('headquarter_id', $hqId);
        }
        if (! $editarHistorico && $user?->id) {
            $payQ->where('user_id', $user->id);
        }
        $applyDate($payQ, 'fecha');
        if ($term !== '') {
            match ($tipo) {
                '1' => $payQ->whereHas('credit.client', function ($c) use ($term) {
                    $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('apellido_pat', 'like', "%{$term}%")
                        ->orWhere('apellido_mat', 'like', "%{$term}%");
                }),
                '2' => $payQ->where('detalle', 'like', "%{$term}%"),
                '3' => $payQ->where('asesor', 'like', "%{$term}%"),
                '4' => $payQ->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")),
                default => null,
            };
        }
        $payments = $payQ->get();

        $rows = collect();
        foreach ($incomes as $i) {
            $rows->push((object) [
                'id' => $i->id,
                'date' => $i->date,
                'usuario' => $i->user?->username ?? $i->user?->name ?? '',
                'asesor' => $i->asesor,
                'reason' => $i->reason,
                'detail' => $i->detail,
                'documento' => $i->documento ?? '',
                'modo' => $i->modo,
                'total' => (float) $i->total,
            ]);
        }
        foreach ($payments as $p) {
            $cli = $p->credit?->client;
            $cliN = $cli ? trim($cli->apellido_pat.' '.$cli->apellido_mat.' '.$cli->nombre) : '';
            $rows->push((object) [
                'id' => $p->id,
                'date' => $p->fecha,
                'usuario' => $p->user?->username ?? $p->user?->name ?? '',
                'asesor' => $p->asesor,
                'reason' => $cliN,
                'detail' => $p->detalle,
                'documento' => $p->tipo,
                'modo' => 'CREDITO',
                'total' => (float) $p->monto,
            ]);
        }

        $rows = $rows->sortBy('id')->values();

        $total = $tofijo = $totros = $tocapi = $totinte = $totmora = 0.0;
        foreach ($rows as $r) {
            $t = (float) $r->total;
            $total += $t;
            if ($r->modo === 'Fijos') {
                $tofijo += $t;
            } elseif ($r->modo === 'Otros') {
                $totros += $t;
            }
            $doc = (string) $r->documento;
            if ($doc === 'CAPITAL') {
                $tocapi += $t;
            } elseif ($doc === 'INTERES') {
                $totinte += $t;
            } elseif (str_contains($doc, 'MORA')) {
                $totmora += $t;
            }
        }

        return XlsResponse::make('exports.incomes', [
            'rows' => $rows,
            'total' => $total,
            'tofijo' => $tofijo,
            'totros' => $totros,
            'tocapi' => $tocapi,
            'totinte' => $totinte,
            'totmora' => $totmora,
        ], 'Ingresos.xls');
    }

    public function exportExpenses(Request $request)
    {
        $user = auth()->user();

        $tipo = (string) $request->query('tipo', '1');
        $compra = (string) $request->query('compra', '');
        $fei = (string) $request->query('fei', now()->format('Y-m-d'));
        $fef = (string) $request->query('fef', now()->format('Y-m-d'));
        $crossHQ = $user?->can('acceso.cross-headquarter') ?? false;
        $hqId = $user?->headquarter_id ?? 1;
        $editarHistorico = $user?->canAny(['caja.editar-historico', 'caja.ver-todo']) ?? false;

        $term = trim($compra);

        $query = Expense::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        if (! $crossHQ) {
            $query->where('headquarter_id', $hqId);
        }
        if (! $editarHistorico && $user?->id) {
            $query->where('user_id', $user->id)->where('reason', 'Diario');
        }

        if ($term !== '' && ($fei === '' || $fef === '')) {
            // solo búsqueda
        } elseif ($fei !== '' && $fef !== '') {
            $query->where('date', '>=', $fei)->where('date', '<=', $fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        if ($term !== '') {
            match ($tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")),
                '4' => $query->where('in_charge', 'like', "%{$term}%"),
                default => null,
            };
        }

        $rows = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        $total = $tofijo = $totros = $sumdiario = $summensu = $sumdm = 0.0;
        foreach ($rows as $e) {
            $t = (float) $e->total;
            $total += $t;
            if ($e->modo === 'Fijos') {
                $tofijo += $t;
            } else {
                $totros += $t;
            }
            if ($e->reason === 'Diario') {
                $sumdiario += $t;
            } elseif ($e->reason === 'Mensual') {
                $summensu += $t;
            } elseif ($e->reason === 'D.M') {
                $sumdm += $t;
            }
        }

        return XlsResponse::make('exports.expenses', [
            'rows' => $rows,
            'total' => $total,
            'tofijo' => $tofijo,
            'totros' => $totros,
            'sumdiario' => $sumdiario,
            'summensu' => $summensu,
            'sumdm' => $sumdm,
        ], 'Egresos.xls');
    }
}
