<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /payments (créditos activos para pago).
 * Réplica del legacy pagosex2.php — título "PAGO/CREDITO", fila Totales.
 */
class PaymentsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $totalImporte = 0;

    public function __construct(
        protected ?string $nombre  = '',
        protected ?string $nombre1 = '',
        protected ?string $codigo  = '',
        protected ?int    $userId  = null,
        protected bool    $scopePropio = false,
    ) {}

    public function collection()
    {
        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id'])
            ->where('situacion', '<>', 'Cancelado');

        if ($this->scopePropio && $this->userId) {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->userId));
        }
        if (trim((string) $this->nombre) !== '') {
            $t = trim((string) $this->nombre);
            $query->whereHas('client', fn ($c) => $c->where('documento', 'like', "%{$t}%"));
        }
        if (trim((string) $this->nombre1) !== '') {
            $t = trim((string) $this->nombre1);
            $query->whereHas('client', function ($c) use ($t) {
                $c->where('nombre', 'like', "%{$t}%")
                  ->orWhere('apellido_pat', 'like', "%{$t}%")
                  ->orWhere('apellido_mat', 'like', "%{$t}%");
            });
        }
        if (trim((string) $this->codigo) !== '') {
            $query->where('id', 'like', '%' . trim((string) $this->codigo) . '%');
        }

        $rows = $query->orderBy('id', 'asc')->get();
        $this->totalImporte = (float) $rows->sum('importe');

        return $rows;
    }

    public function headings(): array
    {
        return ['Nº', 'Exp.', 'Código', 'Nombre', 'Moneda', 'Capital', '%', 'Cuotas'];
    }

    public function map($credit): array
    {
        static $i = 0;
        $i++;

        $client = $credit->client;
        $nombre = $client ? trim(($client->apellido_pat ?? '') . ' ' . ($client->apellido_mat ?? '') . ' ' . ($client->nombre ?? '')) : '';

        return [
            $i,
            $client?->expediente,
            $credit->id,
            $nombre,
            $credit->moneda,
            (float) $credit->importe,
            round((float) $credit->interes, 0),
            (int) $credit->cuotas,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'H';

                // Fila de totales (legacy: colspan 5 "Totales" + suma Capital)
                $totalRow = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$totalRow}", 'Totales');
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("F{$totalRow}", number_format($this->totalImporte, 2));

                $this->applyLegacyStyle($sheet, 'PAGO/CREDITO', $lastCol);
                $this->markTotalRow($sheet, $totalRow, $lastCol);
            },
        ];
    }
}
