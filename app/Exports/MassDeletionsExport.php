<?php

namespace App\Exports;

use App\Models\MassDeletion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MassDeletionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
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

        return $query->orderBy('date', 'asc')->get();
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
                $sheet->getStyle('A1:H1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
