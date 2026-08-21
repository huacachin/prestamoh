<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Livewire\Attributes\Url;
use Livewire\Component;

class Expenses extends Component
{
    #[Url(as: 'tipo', except: '1')]
    public string $tipo = '1';   // 1=A, 2=Motivo, 3=Usuario, 4=Respons.

    #[Url(as: 'buscar', except: '')]
    public string $compra = '';

    #[Url(as: 'desde')]
    public string $fei = '';

    #[Url(as: 'hasta')]
    public string $fef = '';

    public function mount(): void
    {
        if (! request()->has('desde')) {
            $this->fei = now()->format('Y-m-d');
        }
        if (! request()->has('hasta')) {
            $this->fef = now()->format('Y-m-d');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $crossHQ = $user->can('acceso.cross-headquarter');
        $hqId = $user->headquarter_id ?? 1;
        $term = trim($this->compra);

        // caja=1: caja principal (legacy huaca_entrada). caja=3 son duplicados-espejo.
        $query = Expense::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with(['user:id,name,username', 'attachments'])
            ->withCount('attachments');

        if (! $crossHQ) {
            $query->where('headquarter_id', $hqId);
        }

        // Operadores de caja (sin caja.editar-historico): solo sus propios egresos diarios
        if (! $user->canAny(['caja.editar-historico', 'caja.ver-todo'])) {
            $query->where('user_id', $user->id)
                ->where('reason', 'Diario');
        }

        // Lógica fechas + búsqueda (estilo legacy)
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda, sin filtro de fecha
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        // Filtro búsqueda
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                ),
                '4' => $query->where('in_charge', 'like', "%{$term}%"),
                default => null,
            };
        }

        $expenses = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        // Subtotales
        $tofijo = 0;
        $totros = 0;
        $sumdiario = 0;
        $summensu = 0;
        $sumdm = 0;
        $totalGeneral = 0;

        foreach ($expenses as $e) {
            $totalGeneral += (float) $e->total;
            if ($e->modo === 'Fijos') {
                $tofijo += $e->total;
            } else {
                $totros += $e->total;
            }

            if ($e->reason === 'Diario') {
                $sumdiario += $e->total;
            } elseif ($e->reason === 'Mensual') {
                $summensu += $e->total;
            } elseif ($e->reason === 'D.M') {
                $sumdm += $e->total;
            }
        }

        $datos220 = $sumdm / 2;
        $valor1 = $sumdiario + $datos220;
        $valor2 = $summensu + $datos220;
        $valor3 = $valor1 + $valor2;

        // Mapa de galerías por egreso (para el lightbox inline de la lista). Incluye
        // los adjuntos y, si existe, la imagen única legacy (image_path).
        $galleries = [];
        foreach ($expenses as $e) {
            $items = [];
            foreach ($e->attachments as $a) {
                $items[] = ['url' => $a->url(), 'name' => $a->original_name];
            }
            if (! empty($e->image_path)) {
                $items[] = ['url' => '/storage/'.ltrim($e->image_path, '/'), 'name' => 'Imagen'];
            }
            if ($items) {
                $galleries[$e->id] = [
                    'items' => $items,
                    'manageUrl' => route('cash.expenses.gallery', $e->id),
                ];
            }
        }

        return view('livewire.cash.expenses', [
            'expenses' => $expenses,
            'galleries' => $galleries,
            'totalGeneral' => $totalGeneral,
            'tofijo' => $tofijo,
            'totros' => $totros,
            'sumdiario' => $sumdiario,
            'summensu' => $summensu,
            'sumdm' => $sumdm,
            'valor1' => $valor1,
            'valor2' => $valor2,
            'valor3' => $valor3,
        ]);
    }
}
