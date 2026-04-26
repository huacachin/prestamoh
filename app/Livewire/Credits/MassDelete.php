<?php

namespace App\Livewire\Credits;

use App\Exports\MassDeletionsExport;
use App\Models\MassDeletion;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class MassDelete extends Component
{
    public string $tipo = '1'; // 1=Codigo, 2=Asesor, 3=Usuario
    public string $compra = '';
    public string $fei = '';
    public string $fef = '';

    public function mount(): void
    {
        $this->fei = now()->format('Y-m-d');
        $this->fef = now()->format('Y-m-d');
    }

    public function exportExcel()
    {
        return Excel::download(
            new MassDeletionsExport($this->tipo, $this->compra, $this->fei, $this->fef),
            'eliminar-masivo-' . now()->format('Y-m-d') . '.xlsx'
        );
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
