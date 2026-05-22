<?php

namespace App\Exports;

use App\Models\Credit;
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
 * Excel del listado /payments (créditos activos para pago).
 * Réplica del legacy pagosex2.php — solo créditos NO cancelados.
 */
class PaymentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
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

        return $query->orderBy('id', 'asc')->get();
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
                $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
