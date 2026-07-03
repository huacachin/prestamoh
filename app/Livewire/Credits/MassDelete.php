<?php

namespace App\Livewire\Credits;

use App\Models\MassDeletion;
use App\Support\XlsResponse;
use Livewire\Attributes\Url;
use Livewire\Component;

class MassDelete extends Component
{
    #[Url(as: 'tipo', except: '1')]
    public string $tipo = '1'; // 1=Codigo, 2=Asesor, 3=Usuario

    #[Url(as: 'buscar', except: '')]
    public string $compra = '';

    #[Url(as: 'desde')]
    public string $fei = '';

    #[Url(as: 'hasta')]
    public string $fef = '';

    public function mount(): void
    {
        // Default hoy solo si la URL no trae la fecha (así una fecha vacía
        // elegida por el usuario se respeta al refrescar).
        if (! request()->has('desde')) {
            $this->fei = now()->format('Y-m-d');
        }
        if (! request()->has('hasta')) {
            $this->fef = now()->format('Y-m-d');
        }
    }

    public function exportExcel()
    {
        $term = trim($this->compra);

        $query = MassDeletion::query()->with(['credit.client']);

        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda, sin filtro de fecha
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)
                ->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('credit_id', 'like', "%{$term}%"),
                '2' => $query->where('advisor', 'like', "%{$term}%"),
                '3' => $query->where('performed_by', 'like', "%{$term}%"),
                default => null,
            };
        }

        $records = $query->orderBy('date', 'asc')->get();
        $totalSum = $records->sum('amount');

        return XlsResponse::make('exports.mass-deletions', [
            'records' => $records,
            'totalSum' => $totalSum,
        ], 'Eliminar Masivo.xls');
    }

    public function render()
    {
        $term = trim($this->compra);

        $query = MassDeletion::query()->with(['credit.client']);

        // Lógica del legacy:
        //  - compra + sin fechas → solo búsqueda
        //  - compra + fechas → ambos filtros
        //  - sin compra + fechas → solo fechas
        //  - default → solo hoy
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda, sin filtro de fecha
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)
                ->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        // Filtro por búsqueda
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('credit_id', 'like', "%{$term}%"),
                '2' => $query->where('advisor', 'like', "%{$term}%"),
                '3' => $query->where('performed_by', 'like', "%{$term}%"),
                default => null,
            };
        }

        $records = $query->orderBy('date', 'asc')->get();

        $totalSum = $records->sum('amount');

        return view('livewire.credits.mass-delete', compact('records', 'totalSum'));
    }
}
