<?php

namespace App\Exports;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del listado /cash/expenses — réplica del legacy gastos_excel.php.
 * Aplica los MISMOS filtros que ve la pantalla (HQ, scope usuario, fechas, búsqueda).
 */
class ExpensesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected string $tipo = '1',
        protected string $compra = '',
        protected string $fei = '',
        protected string $fef = '',
        protected ?int   $userId = null,
        protected bool   $crossHQ = false,
        protected ?int   $hqId = 1,
        protected bool   $editarHistorico = false,
    ) {}

    public function collection()
    {
        $term = trim($this->compra);

        $query = Expense::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        if (!$this->crossHQ) {
            $query->where('headquarter_id', $this->hqId ?? 1);
        }
        if (!$this->editarHistorico && $this->userId) {
            $query->where('user_id', $this->userId)->where('reason', 'Diario');
        }

        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // solo búsqueda
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ),
                '4' => $query->where('in_charge', 'like', "%{$term}%"),
                default => null,
            };
        }

        return $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Usuario', 'Responsable', 'Modo', 'Categoría', 'Motivo', 'Documento', 'Total'];
    }

    public function map($e): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $e->date?->format('d/m/Y'),
            $e->user?->username ?? $e->user?->name ?? '',
            $e->in_charge,
            $e->modo,
            $e->reason,
            $e->detail,
            $e->documento,
            (float) $e->total,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
