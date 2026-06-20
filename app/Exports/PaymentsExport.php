<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /payments — calca la pantalla "PAGO/CREDITO" (8 columnas).
 * Selección de créditos activos para registrar pago.
 */
class PaymentsExport implements FromCollection, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use LegacyExcelStyle;

    protected float $sumCapital = 0;

    public function __construct(
        protected ?string $nombre = '',
        protected ?string $nombre1 = '',
        protected ?string $codigo = '',
        protected ?int $userId = null,
        protected bool $scopePropio = false,
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
            $query->where('id', 'like', '%'.trim((string) $this->codigo).'%');
        }

        $credits = $query->orderBy('id', 'asc')->get();
        $this->sumCapital = (float) $credits->sum('importe');

        return $credits;
    }

    public function headings(): array
    {
        // Pantalla PAGO/CREDITO: Nº, Exp., Código, Nombre, Moneda, Capital, %, C.
        return ['Nº', 'Exp.', 'Código', 'Nombre', 'Moneda', 'Capital', '%', 'C.'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $cli = $c->client;
        $nombre = $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : '';

        return [
            $i,                              // Nº
            $cli?->expediente,              // Exp.
            $c->id,                         // Código
            $nombre,                        // Nombre
            $c->moneda,                     // Moneda
            (float) $c->importe,            // Capital
            round((float) $c->interes, 0),  // %
            (int) $c->cuotas,               // C.
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'H';

                $this->styleDataRange($sheet, $lastCol, ['F']);

                // Fila de Totales (Capital)
                $r = $sheet->getHighestRow() + 1;
                $sheet->mergeCells("A{$r}:E{$r}");
                $sheet->setCellValue("A{$r}", 'Totales');
                $sheet->setCellValue("F{$r}", number_format($this->sumCapital, 2));

                $this->applyLegacyStyle($sheet, 'PAGO/CREDITO', $lastCol);
                $this->markTotalRow($sheet, $r, $lastCol);
            },
        ];
    }
}
