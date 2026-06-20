<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use App\Models\CreditInstallment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /credits — calca la pantalla "PRESTAMOS" (14 columnas + TOTALES).
 * (El resumen de 21 columnas vive en el reporte de Cartera / PortfolioExport.)
 */
class CreditsExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use LegacyExcelStyle;

    /** @var array<int, array{iapli: float, aplido: float}> */
    protected array $pagosMap = [];

    protected float $sumCapital = 0;
    protected float $sumInteres = 0;
    protected float $sumTotal = 0;
    protected float $sumPago = 0;
    protected float $sumSaldo = 0;

    public function __construct(
        protected string $nombre = '',
        protected string $codigo = '',
        protected string $ejecutivo = '',
        protected string $seletipl = '',
    ) {}

    public function collection()
    {
        $query = Credit::query()
            ->with([
                'client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id',
                'client.asesor:id,name,username',
            ])
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
            $query->where('id', 'like', '%'.trim($this->codigo).'%');
        }
        if (trim($this->ejecutivo) !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }
        if (trim($this->seletipl) !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }

        $credits = $query->orderByDesc('fecha_prestamo')->get();

        $ids = $credits->pluck('id')->all();
        if (! empty($ids)) {
            $sums = CreditInstallment::whereIn('credit_id', $ids)
                ->selectRaw('credit_id, sum(importe_aplicado) as iapli, sum(interes_aplicado) as aplido')
                ->groupBy('credit_id')->get();
            foreach ($sums as $s) {
                $this->pagosMap[$s->credit_id] = ['iapli' => (float) $s->iapli, 'aplido' => (float) $s->aplido];
            }
        }

        foreach ($credits as $c) {
            $iapli = $this->pagosMap[$c->id]['iapli'] ?? 0;
            $aplido = $this->pagosMap[$c->id]['aplido'] ?? 0;
            $inter = round(($c->importe * $c->interes) / 100, 2);
            $pend = (float) $c->importe - $iapli - $aplido + $inter;

            $this->sumCapital += (float) $c->importe;
            $this->sumInteres += $inter;
            $this->sumTotal += (float) $c->importe + $inter;
            $this->sumPago += $iapli + $aplido;
            $this->sumSaldo += $pend;
        }

        return $credits;
    }

    public function headings(): array
    {
        // Pantalla PRESTAMOS: Nº, Fecha, Usuario, Código, Nombre, Capital, %, S/, C., Total, Pago, Saldo, Asesor, T.C.
        return ['Nº', 'Fecha', 'Usuario', 'Código', 'Nombre', 'Capital', '%', 'S/', 'C.', 'Total', 'Pago', 'Saldo', 'Asesor', 'T.C.'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $cli = $c->client;
        $nombre = $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : '';

        $iapli = $this->pagosMap[$c->id]['iapli'] ?? 0;
        $aplido = $this->pagosMap[$c->id]['aplido'] ?? 0;
        $inter = round(($c->importe * $c->interes) / 100, 2);
        $pend = (float) $c->importe - $iapli - $aplido + $inter;

        $tc = match ((int) $c->tipo_planilla) {
            1 => 'S.', 3 => 'M.', 4 => 'D.', default => '',
        };

        return [
            $i,                                       // Nº
            $c->fecha_prestamo?->format('d/m/Y'),     // Fecha
            $c->usuario,                              // Usuario
            $c->id,                                   // Código
            $nombre,                                  // Nombre
            (float) $c->importe,                      // Capital
            round((float) $c->interes, 0),            // %
            $inter,                                   // S/
            (int) $c->cuotas,                         // C.
            (float) $c->importe + $inter,             // Total
            $iapli + $aplido,                         // Pago
            $pend,                                    // Saldo
            $cli?->asesor?->name ?? $cli?->asesor?->username ?? '', // Asesor
            $tc,                                      // T.C.
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'N';

                $this->styleDataRange($sheet, $lastCol, ['F', 'H', 'J', 'K', 'L']);

                // Fila TOTALES (pantalla: A:E "TOTALES:", valores en F/H/J/K/L)
                $r = $sheet->getHighestRow() + 1;
                $sheet->mergeCells("A{$r}:E{$r}");
                $sheet->setCellValue("A{$r}", 'TOTALES:');
                $sheet->setCellValue("F{$r}", number_format($this->sumCapital, 2));
                $sheet->setCellValue("H{$r}", number_format($this->sumInteres, 2));
                $sheet->setCellValue("J{$r}", number_format($this->sumTotal, 2));
                $sheet->setCellValue("K{$r}", number_format($this->sumPago, 2));
                $sheet->setCellValue("L{$r}", number_format($this->sumSaldo, 2));

                $this->applyLegacyStyle($sheet, 'PRESTAMOS', $lastCol);
                $this->markTotalRow($sheet, $r, $lastCol);
            },
        ];
    }
}
