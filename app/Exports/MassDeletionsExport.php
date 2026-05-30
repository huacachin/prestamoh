<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\MassDeletion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel de pagos masivos — título "ELIMINAR MASIVO" + total de monto.
 */
class MassDeletionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $total = 0;

    public function __construct(
        protected ?string $tipo,
        protected ?string $compra,
        protected ?string $fei,
        protected ?string $fef,
    ) {}

    public function collection()
    {
        $term = trim((string) $this->compra);
        $query = MassDeletion::query()->with(['credit.client']);

        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // Solo búsqueda
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->whereDate('date', '>=', $this->fei)
                  ->whereDate('date', '<=', $this->fef);
        } else {
            $query->whereDate('date', now()->format('Y-m-d'));
        }

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('credit_id', 'like', "%{$term}%"),
                '2' => $query->where('advisor', 'like', "%{$term}%"),
                '3' => $query->where('performed_by', 'like', "%{$term}%"),
                default => null,
            };
        }

        $rows = $query->orderBy('date', 'asc')->get();
        $this->total = (float) $rows->sum('amount');

        return $rows;
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Hora', 'Usuario', 'Asesor', 'Cliente', 'Código', 'Total'];
    }

    public function map($r): array
    {
        $cli = $r->credit?->client;
        $cliente = $cli ? trim($cli->apellido_pat . ' ' . $cli->apellido_mat . ' ' . $cli->nombre) : '';

        static $i = 0;
        $i++;

        return [
            $i,
            $r->date?->format('d/m/Y'),
            $r->time,
            $r->performed_by ?? $r->user,
            $r->advisor,
            $cliente,
            $r->credit_id,
            (float) $r->amount,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'H';

                $totalRow = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$totalRow}", 'Total');
                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                $sheet->setCellValue("H{$totalRow}", number_format($this->total, 2));

                $this->applyLegacyStyle($sheet, 'ELIMINAR MASIVO', $lastCol);
                $this->markTotalRow($sheet, $totalRow, $lastCol);
            },
        ];
    }
}
