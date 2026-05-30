<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use App\Models\CreditInstallment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /credits — réplica de reporte1.php. Título "RESUMEN DE CREDITO".
 * Totales de Capital, Pagado y Saldo.
 */
class CreditsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    /** @var array<int, array{iapli: float, aplido: float}> */
    protected array $pagosMap = [];
    protected float $totCapital = 0;
    protected float $totPagado = 0;
    protected float $totSaldo = 0;

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

        foreach ($credits as $c) {
            $iapli  = $this->pagosMap[$c->id]['iapli'] ?? 0;
            $aplido = $this->pagosMap[$c->id]['aplido'] ?? 0;
            $inter  = round(($c->importe * $c->interes) / 100, 2);
            $this->totCapital += (float) $c->importe;
            $this->totPagado  += $iapli + $aplido;
            $this->totSaldo   += (float) $c->importe - $iapli - $aplido + $inter;
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'N';

                $totalRow = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$totalRow}", 'Totales');
                $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
                $sheet->setCellValue("G{$totalRow}", number_format($this->totCapital, 2));
                $sheet->setCellValue("J{$totalRow}", number_format($this->totPagado, 2));
                $sheet->setCellValue("K{$totalRow}", number_format($this->totSaldo, 2));

                $this->applyLegacyStyle($sheet, 'RESUMEN DE CREDITO', $lastCol);
                $this->markTotalRow($sheet, $totalRow, $lastCol);
            },
        ];
    }
}
