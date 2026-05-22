<?php

namespace App\Exports;

use App\Models\Credit;
use App\Models\CreditInstallment;
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
 * Excel del listado /credits — réplica de reporte1.php (cartera activa).
 */
class CreditsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    /** @var array<int, array{iapli: float, aplido: float}> */
    protected array $pagosMap = [];

    public function __construct(
        protected string $nombre = '',
        protected string $codigo = '',
        protected string $ejecutivo = '',
        protected string $seletipl = '',
    ) {}

    public function collection()
    {
        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id', 'user:id,name,username'])
            ->where('estado', 1)
            ->where('situacion', '<>', 'Cancelado');

        if (trim($this->nombre) !== '') {
            $t = trim($this->nombre);
            $query->whereHas('client', function ($c) use ($t) {
                $c->where('nombre', 'like', "%{$t}%")
                  ->orWhere('apellido_pat', 'like', "%{$t}%")
                  ->orWhere('apellido_mat', 'like', "%{$t}%");
            });
        }
        if (trim($this->codigo) !== '') {
            $query->where('id', 'like', '%' . trim($this->codigo) . '%');
        }
        if (trim($this->ejecutivo) !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }
        if (trim($this->seletipl) !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }

        $credits = $query->orderByDesc('fecha_prestamo')->get();

        // Precalcular sumas de pagos (evita N+1 en map)
        $ids = $credits->pluck('id')->all();
        if (!empty($ids)) {
            $sums = CreditInstallment::whereIn('credit_id', $ids)
                ->selectRaw('credit_id, sum(importe_aplicado) as iapli, sum(interes_aplicado) as aplido')
                ->groupBy('credit_id')->get();
            foreach ($sums as $s) {
                $this->pagosMap[$s->credit_id] = [
                    'iapli'  => (float) $s->iapli,
                    'aplido' => (float) $s->aplido,
                ];
            }
        }

        return $credits;
    }

    public function headings(): array
    {
        return ['Nº', 'Código', 'Fecha', 'Cliente', 'Asesor', 'Tipo', 'Capital', 'Interés', 'Total', 'Pagado', 'Saldo', 'Moneda', 'Cuotas', 'Situación'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $cli = $c->client;
        $nombre = $cli ? trim(($cli->apellido_pat ?? '') . ' ' . ($cli->apellido_mat ?? '') . ' ' . ($cli->nombre ?? '')) : '';

        $iapli  = $this->pagosMap[$c->id]['iapli'] ?? 0;
        $aplido = $this->pagosMap[$c->id]['aplido'] ?? 0;
        $inter  = round(($c->importe * $c->interes) / 100, 2);

        $tipo = match ((int) $c->tipo_planilla) {
            1 => 'Semanal', 3 => 'Mensual', 4 => 'Diario', default => '',
        };

        return [
            $i,
            $c->id,
            $c->fecha_prestamo?->format('d/m/Y'),
            $nombre,
            $c->user?->name ?? $c->user?->username ?? '',
            $tipo,
            (float) $c->importe,
            $inter,
            (float) $c->importe + $inter,
            $iapli + $aplido,
            (float) $c->importe - $iapli - $aplido + $inter,
            $c->moneda,
            (int) $c->cuotas,
            $c->situacion,
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
                $sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2874A6');
                $sheet->getStyle('A1:N1')->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
