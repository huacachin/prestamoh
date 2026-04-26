<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use App\Models\Payment;
use Livewire\Component;

class Incomes extends Component
{
    public string $tipo = '1';   // 1=A, 2=Motivo, 3=Asesor, 4=Usuario
    public string $compra = '';
    public string $fei = '';
    public string $fef = '';

    public function mount(): void
    {
        $this->fei = now()->format('Y-m-d');
        $this->fef = now()->format('Y-m-d');
    }

    public function render()
    {
        $user = auth()->user();
        $term = trim($this->compra);

        // ─── INGRESOS (Fijos/Otros) ────────────────────────────────────
        $incomeQuery = Income::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        if ($user->hasAnyRole(['asesor', 'cobranza'])) {
            $incomeQuery->where('user_id', $user->id)
                        ->where('reason', 'Diario');
        }

        $this->applyDateFilter($incomeQuery, 'date', $term);

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $incomeQuery->where('reason', 'like', "%{$term}%"),
                '2' => $incomeQuery->where('detail', 'like', "%{$term}%"),
                '3' => $incomeQuery->where('asesor', 'like', "%{$term}%"),
                '4' => $incomeQuery->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%")
                ),
                default => null,
            };
        }

        $incomes = $incomeQuery->get();

        // ─── PAGOS DE CRÉDITOS (CREDITO) ───────────────────────────────
        $payQuery = Payment::query()
            ->with(['credit.client:id,nombre,apellido_pat,apellido_mat']);

        if ($user->hasAnyRole(['asesor', 'cobranza'])) {
            $payQuery->where('user_id', $user->id);
        }

        $this->applyDateFilter($payQuery, 'fecha', $term);

        // Filtro de búsqueda en payments
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $payQuery->whereHas('credit.client', function ($c) use ($term) {
                    $c->where('nombre', 'like', "%{$term}%")
                      ->orWhere('apellido_pat', 'like', "%{$term}%")
                      ->orWhere('apellido_mat', 'like', "%{$term}%");
                }),
                '2' => $payQuery->where('detalle', 'like', "%{$term}%"),
                '3' => $payQuery->where('asesor', 'like', "%{$term}%"),
                '4' => null, // payments no tiene user filtrable por nombre directo
                default => null,
            };
        }

        $payments = $payQuery->get();

        // ─── UNIFICAR INGRESOS + PAGOS ──────────────────────────────────
        $rows = collect();

        foreach ($incomes as $i) {
            $rows->push([
                'kind'      => 'income',
                'id'        => $i->id,
                'date'      => $i->date,
                'usuario'   => $i->user?->username ?? $i->user?->name,
                'asesor'    => $i->asesor,
                'reason'    => $i->reason,             // legacy: aa
                'detail'    => $i->detail,
                'documento' => $i->documento ?? '',
                'modo'      => $i->modo,
                'total'     => (float) $i->total,
                'has_image' => !empty($i->image_path ?? null),
                'editable'  => true,
                'user_id'   => $i->user_id,
            ]);
        }

        foreach ($payments as $p) {
            $cli = $p->credit?->client;
            $clienteNombre = $cli ? trim($cli->apellido_pat . ' ' . $cli->apellido_mat . ' ' . $cli->nombre) : '';
            $rows->push([
                'kind'      => 'payment',
                'id'        => $p->id,
                'date'      => $p->fecha,
                'usuario'   => '',
                'asesor'    => $p->asesor,
                'reason'    => $clienteNombre,         // legacy: aa = nombre cliente
                'detail'    => $p->detalle,
                'documento' => $p->tipo,               // CAPITAL/INTERES/MORA
                'modo'      => 'CREDITO',
                'total'     => (float) $p->monto,
                'has_image' => false,
                'editable'  => false,
                'user_id'   => $p->user_id,
            ]);
        }

        // Ordenar por id ASC (legacy: identrada asc)
        $rows = $rows->sortBy('id')->values();

        // ─── SUBTOTALES ─────────────────────────────────────────────────
        $tofijo = 0; $totros = 0;
        $tocapi = 0; $totinte = 0; $totmora = 0;
        $totalGeneral = 0;

        foreach ($rows as $r) {
            $totalGeneral += $r['total'];

            if ($r['kind'] === 'income') {
                if ($r['modo'] === 'Fijos') $tofijo += $r['total'];
                elseif ($r['modo'] === 'Otros') $totros += $r['total'];
            } else {
                $doc = $r['documento'];
                if ($doc === 'CAPITAL') $tocapi += $r['total'];
                elseif ($doc === 'INTERES') $totinte += $r['total'];
                elseif (str_contains($doc, 'MORA')) $totmora += $r['total'];
            }
        }

        return view('livewire.cash.incomes', [
            'rows'         => $rows,
            'totalGeneral' => $totalGeneral,
            'tofijo'       => $tofijo,
            'totros'       => $totros,
            'tocapi'       => $tocapi,
            'totinte'      => $totinte,
            'totmora'      => $totmora,
        ]);
    }

    /**
     * Aplica el filtro de fecha igual al legacy:
     *  - compra + sin fechas → solo búsqueda (sin filtro fecha)
     *  - compra + fechas     → filtro de fechas
     *  - sin compra + fechas → filtro de fechas
     *  - default             → solo hoy
     */
    private function applyDateFilter($query, string $col, string $term): void
    {
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            return;
        }

        if ($this->fei !== '' && $this->fef !== '') {
            $query->where($col, '>=', $this->fei)
                  ->where($col, '<=', $this->fef);
        } else {
            $query->where($col, now()->format('Y-m-d'));
        }
    }
}
