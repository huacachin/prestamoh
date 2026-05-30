<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del historial crediticio de un cliente (/clients/{id}).
 * Réplica de cliente_viewexcel.php — título "HISTORIAL CREDITICIO" + totales.
 */
class ClientHistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $totCap = 0;
    protected float $totInt = 0;
    protected float $totTotal = 0;
    protected ?string $clienteNombre = null;

    public function __construct(protected int $clientId) {}

    public function collection()
    {
        $client = Client::find($this->clientId);
        $this->clienteNombre = $client
            ? trim(($client->apellido_pat ?? '') . ' ' . ($client->apellido_mat ?? '') . ' ' . ($client->nombre ?? ''))
            : ('#' . $this->clientId);

        $credits = Credit::where('client_id', $this->clientId)
            ->orderBy('fecha_prestamo', 'asc')
            ->get();

        // Suma de pagos por crédito (sin Gat.)
        $ids = $credits->pluck('id')->all();
        $pagos = [];
        if (!empty($ids)) {
            $rows = DB::table('payments')
                ->whereIn('credit_id', $ids)
                ->where(function ($q) { $q->whereNull('detalle')->orWhere('detalle', 'NOT LIKE', 'Gat.%'); })
                ->selectRaw('credit_id, SUM(monto) total')
                ->groupBy('credit_id')->get();
            foreach ($rows as $r) $pagos[$r->credit_id] = (float) $r->total;
        }
        $this->pagosMap = $pagos;

        return $credits;
    }

    /** @var array<int, float> */
    protected array $pagosMap = [];

    public function headings(): array
    {
        return [
            'Nº', 'Usuario', 'Código', 'F. Crédito', 'F. Vcto', 'F. Cancelado',
            'Capital', '%', 'Interés', 'Cuotas', 'Total', 'Pagado', 'Saldo', 'Situación', 'Asesor',
        ];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $cap = (float) $c->importe;
        $intPct = (float) $c->interes;
        $int = round($cap * $intPct / 100, 2);
        $total = $cap + $int;
        $pagado = $this->pagosMap[$c->id] ?? 0;
        $saldo = round($total - $pagado, 2);

        $this->totCap += $cap;
        $this->totInt += $int;
        $this->totTotal += $total;

        $tipo = match ((int) $c->tipo_planilla) {
            1 => 'Semanal', 3 => 'Mensual', 4 => 'Diario', default => '',
        };

        return [
            $i,
            $c->usuario,
            $c->id,
            $c->fecha_prestamo?->format('d/m/Y'),
            $c->fecha_vencimiento?->format('d/m/Y'),
            $c->fecha_cancelacion?->format('d/m/Y'),
            $cap,
            round($intPct, 2),
            $int,
            (int) $c->cuotas,
            $total,
            $pagado,
            $saldo,
            $c->situacion,
            $c->asesor ?? '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'O';

                $r = $sheet->getHighestRow() + 1;
                $sheet->setCellValue("A{$r}", 'Total');
                $sheet->mergeCells("A{$r}:F{$r}");
                $sheet->setCellValue("G{$r}", number_format($this->totCap, 2));
                $sheet->setCellValue("I{$r}", number_format($this->totInt, 2));
                $sheet->setCellValue("K{$r}", number_format($this->totTotal, 2));

                $titulo = 'HISTORIAL CREDITICIO - ' . ($this->clienteNombre ?? '');
                $this->applyLegacyStyle($sheet, $titulo, $lastCol);
                $this->markTotalRow($sheet, $r, $lastCol);
            },
        ];
    }
}
