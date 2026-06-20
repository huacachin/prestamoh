<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Client;
use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del historial crediticio de un cliente (/clients/{id}) — réplica de cliente_viewexcel.php.
 * Panel de datos del cliente + "HISTORIAL DE PAGOS" + tabla de 23 columnas + fila de totales.
 */
class ClientHistoryExport implements FromCollection, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    /** @var array<int,float> */
    protected array $paySinMora = [];
    protected array $payMora = [];
    protected array $maxFecha = [];
    protected int $rowCount = 0;
    protected ?Client $client = null;

    // Totales
    protected float $totom = 0; protected float $tdm = 0; protected float $total1 = 0;
    protected float $mmay = 0; protected float $imintc = 0; protected float $moramora = 0;
    protected float $total2 = 0; protected float $total3 = 0; protected float $montomorxdia = 0;

    public function __construct(protected int $clientId) {}

    /** Panel (filas 1-4) + "HISTORIAL DE PAGOS" (5) + cabecera (6) + datos desde 7. */
    public function startCell(): string
    {
        return 'A7';
    }

    public function collection()
    {
        $this->client = Client::find($this->clientId);

        $credits = Credit::where('client_id', $this->clientId)
            ->orderBy('fecha_prestamo', 'asc')->get();
        $this->rowCount = $credits->count();

        $ids = $credits->pluck('id')->all();
        if (!empty($ids)) {
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->where(function ($q) { $q->whereNull('documento')->orWhere('documento', 'NOT LIKE', 'MORA%'); })
                ->selectRaw('credit_id, SUM(monto) t')->groupBy('credit_id')->get() as $r) {
                $this->paySinMora[$r->credit_id] = (float) $r->t;
            }
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->where('documento', 'LIKE', 'MORA%')
                ->selectRaw('credit_id, SUM(monto) t')->groupBy('credit_id')->get() as $r) {
                $this->payMora[$r->credit_id] = (float) $r->t;
            }
            foreach (DB::table('payments')->whereIn('credit_id', $ids)
                ->selectRaw('credit_id, MAX(fecha) m')->groupBy('credit_id')->get() as $r) {
                $this->maxFecha[$r->credit_id] = $r->m;
            }
        }

        return $credits;
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $importe   = (float) $c->importe;
        $intPct    = (float) $c->interes;
        $tintere   = round($importe * $intPct / 100, 2);
        $apSinMora = $this->paySinMora[$c->id] ?? 0;
        $mora      = $this->payMora[$c->id] ?? 0;
        $difeinter = $tintere - $apSinMora;

        if ($difeinter <= 0) { $rftq = abs($difeinter); $iminte = $tintere; }
        else                 { $rftq = 0;               $iminte = $apSinMora; }

        $totalCred = $importe + $tintere;
        $totalGan  = $rftq + $iminte + $mora;
        $saldo2    = $importe + $tintere - $rftq - $iminte;

        // Días de mora (vencimiento → cancelación)
        $newdias = 0;
        if ($c->fecha_cancelacion && $c->fecha_vencimiento) {
            $newdias = (int) $c->fecha_vencimiento->diffInDays($c->fecha_cancelacion, false);
        }
        $intediasdias = round($tintere / 30, 2);
        $sCol = $newdias > 0 ? round($newdias * $intediasdias, 2) : '';
        $mxd  = $newdias > 0 ? $intediasdias : '';
        $dias = $newdias > 0 ? $newdias : '';

        // Totales
        $this->totom        += $importe;
        $this->tdm          += (float) $c->interes_total;
        $this->total1       += round($totalCred, 2);
        $this->mmay         += $rftq;
        $this->imintc       += $iminte;
        $this->moramora     += $mora;
        $this->total2       += $totalGan;
        $this->total3       += $saldo2;
        $this->montomorxdia += $newdias > 0 ? round($newdias * $intediasdias, 2) : 0;

        return [
            $i,                                         // A  Nº
            $c->usuario,                                // B  Usuario
            $c->id,                                     // C  Codigo
            $c->idcan,                                  // D  Cod. Ant.
            $c->fecha_prestamo?->format('Y-m-d'),       // E  F. Credito
            $c->fecha_vencimiento?->format('Y-m-d'),    // F  F. Vcto
            $this->maxFecha[$c->id] ?? '',              // G  F. Pago
            $c->fecha_cancelacion?->format('Y-m-d'),    // H  F. Cancelado
            $importe,                                   // I  Capital
            round($intPct, 2),                          // J  %
            (float) $c->interes_total,                  // K  Interes
            (int) $c->cuotas,                           // L  Cuotas
            round($totalCred, 2),                       // M  Total
            round($rftq, 2),                            // N  Capital R.
            round($iminte, 2),                          // O  Interes G.
            round($mora, 2),                            // P  Mora
            round($totalGan, 2),                        // Q  Total
            round($saldo2, 2),                          // R  Saldo Capital
            $sCol,                                      // S  S/
            $mxd,                                       // T  MxD
            $dias,                                      // U  Dias
            $c->gat,                                    // V  Gat.
            $c->asesor ?? '',                           // W  Asesor
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'W';

                $this->buildPanelAndHeader($sheet, $lastCol);
                $this->buildDataStyles($sheet, $lastCol);
                $this->buildTotalRow($sheet, $lastCol);
                $this->applyBaseFontAndAutosize($sheet);
            },
        ];
    }

    private function buildPanelAndHeader(Worksheet $sheet, string $lastCol): void
    {
        $c = $this->client;
        $nombre = $c ? trim(($c->apellido_pat ?? '') . ' ' . ($c->apellido_mat ?? '') . ' ' . ($c->nombre ?? '')) : ('#' . $this->clientId);
        $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';
        $movil = trim(($c->celular1 ?? '') . ' ' . ($c->celular2 ?? ''));

        // Panel cliente (filas 1-4): label A, valor B:H, label I, valor J:M
        $panel = [
            1 => ['Nombres:', $nombre, 'Fecha Nacimiento:', $fmt($c?->fecha_nacimiento)],
            2 => ['N° Expediente:', $c?->expediente, 'Nacionalidad:', 'Peruano'],
            3 => ['Direccion:', $c?->direccion, 'Movil:', $movil],
            4 => ['Registrado el:', $fmt($c?->fecha_registro), 'DNI:', (string) ($c?->documento ?? '')],
        ];
        foreach ($panel as $row => [$l1, $v1, $l2, $v2]) {
            $sheet->setCellValue("A{$row}", $l1);
            $sheet->mergeCells("B{$row}:H{$row}"); $sheet->setCellValue("B{$row}", $v1);
            $sheet->setCellValue("I{$row}", $l2);
            $sheet->mergeCells("J{$row}:M{$row}"); $sheet->setCellValue("J{$row}", $v2);
        }
        $sheet->getStyle("A1:A4")->getFont()->setBold(true);
        $sheet->getStyle("I1:I4")->getFont()->setBold(true);

        // Subtítulo "HISTORIAL DE PAGOS" (fila 5)
        $sheet->mergeCells("A5:{$lastCol}5");
        $sheet->setCellValue('A5', 'HISTORIAL DE PAGOS');
        $sheet->getStyle('A5')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Cabecera (fila 6)
        $headers = ['Nº', 'Usuario', 'Codigo', 'Cod. Ant.', 'F. Credito', 'F. Vcto', 'F. Pago', 'F. Cancelado',
            'Capital', '%', 'Interes', 'Cuotas', 'Total', 'Capital R.', 'Interes G.', 'Mora', 'Total',
            'Saldo Capital', 'S/', 'MxD', 'Dias', 'Gat.', 'Asesor'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . '6', $h);
        }
        $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        // Bordes punteados cabecera + datos
        $lastData = 6 + $this->rowCount;
        $sheet->getStyle("A6:{$lastCol}{$lastData}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
    }

    /** Formato de moneda + alineación centrada en el rango de datos. */
    private function buildDataStyles(Worksheet $sheet, string $lastCol): void
    {
        if ($this->rowCount < 1) {
            return;
        }
        $first = 7;
        $last  = 6 + $this->rowCount;

        $sheet->getStyle("A{$first}:{$lastCol}{$last}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Columnas de montos (no formatear J=%, L=Cuotas, U=Dias)
        foreach (['I', 'K', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function buildTotalRow(Worksheet $sheet, string $lastCol): void
    {
        $r = 6 + $this->rowCount + 1;
        $sheet->setCellValue("A{$r}", 'Total');
        $sheet->setCellValue("I{$r}", number_format($this->totom, 2));        // Capital
        $sheet->setCellValue("K{$r}", number_format($this->tdm, 2));          // Interes
        $sheet->setCellValue("M{$r}", number_format($this->total1, 2));       // Total
        $sheet->setCellValue("N{$r}", number_format($this->mmay, 2));         // Capital R.
        $sheet->setCellValue("O{$r}", number_format($this->imintc, 2));       // Interes G.
        $sheet->setCellValue("P{$r}", number_format($this->moramora, 2));     // Mora
        $sheet->setCellValue("Q{$r}", number_format($this->total2, 2));       // Total
        $sheet->setCellValue("R{$r}", number_format($this->total3, 2));       // Saldo Capital
        $sheet->setCellValue("S{$r}", number_format($this->montomorxdia, 2)); // S/
        $this->markTotalRow($sheet, $r, $lastCol);
    }
}
