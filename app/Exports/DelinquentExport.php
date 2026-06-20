<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Delinquent;
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
 * Excel del reporte /reports/delinquent (Pendientes por Cobrar) — réplica de reporte1_xcobrar.php.
 * Tabla principal de 20 columnas (cabecera 2 filas, grupo "Interés": TC/%/S//C.),
 * filas Total Soles / Total Dólares. Datos del componente Delinquent.
 */
class DelinquentExport implements FromCollection, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    private const COLOR_BLUE = '0D6EFD';
    private const COLOR_YELLOW = 'FFFF00';

    protected array $totals = [];
    protected float $tc = 1;
    protected int $rowCount = 0;
    /** @var array<int,array{tipo:int,estado:string}> */
    protected array $rowMeta = [];

    public function __construct(
        protected array $filters = [],
    ) {}

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $c = new Delinquent();
        foreach (['selemes0', 'selecano0', 'seletipl0', 'exp', 'codigo', 'cdni', 'cnombre', 'casesor', 'fechai', 'fechaf'] as $f) {
            $c->{$f} = (string) ($this->filters[$f] ?? '');
        }

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->totals = $data['totals'] ?? [];
        $this->tc = (float) ($data['tc'] ?? 1) ?: 1;
        $this->rowCount = $rows->count();

        return $rows;
    }

    public function map($r): array
    {
        static $i = 0;
        $i++;
        $this->rowMeta[3 + $i] = [
            'tipo'   => (int) ($r['tipo_planilla'] ?? 0),
            'estado' => $r['estado'] ?? '',
        ];

        return [
            $r['n']             ?? '',  // A  Nº
            $r['exp']           ?? '',  // B  Exp
            $r['codigo']        ?? '',  // C  Código
            (string) ($r['dni'] ?? ''), // D  DNI
            $r['cliente']       ?? '',  // E  Nombre y Apellidos
            $r['cod_rem']       ?? '',  // F  Dt.
            (float) ($r['cuota']         ?? 0), // G  Capital (importe cuota)
            $r['tc_label']      ?? '',  // H  TC
            (float) ($r['interes_pct']   ?? 0), // I  %
            (float) ($r['interes_monto'] ?? 0), // J  S/
            $r['cuotas']        ?? '',  // K  C.
            (float) ($r['total']         ?? 0), // L  Cuota (total)
            (float) ($r['pago']          ?? 0), // M  Pago
            (float) ($r['saldo']         ?? 0), // N  Saldo
            $r['fecha_cred']     ?? '', // O  Fec/Cred
            $r['fecha_venc']     ?? '', // P  Fec/Venc
            (string) ($r['celular'] ?? ''), // Q  Cel/Titu
            $r['estado']         ?? '', // R  Estado
            $r['tiempo']         ?? '', // S  Tiempo
            $r['asesor']         ?? '', // T  Asesor
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildTitleAndHeader($sheet, 'T');
                $this->buildDataStyles($sheet);
                $this->buildTotals($sheet);
            },
        ];
    }

    private function buildTitleAndHeader(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('A1', 'PENDIENTES POR COBRAR');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $merge = [
            'A2:A3' => 'Nº', 'B2:B3' => 'Exp', 'C2:C3' => 'Código', 'D2:D3' => 'DNI', 'E2:E3' => 'Nombre y Apellidos',
            'F2:F3' => 'Dt.', 'G2:G3' => 'Capital', 'H2:K2' => 'Interés', 'L2:L3' => 'Cuota', 'M2:M3' => 'Pago',
            'N2:N3' => 'Saldo', 'O2:O3' => 'Fec/Cred', 'P2:P3' => 'Fec/Venc', 'Q2:Q3' => 'Cel/Titu',
            'R2:R3' => 'Estado', 'S2:S3' => 'Tiempo', 'T2:T3' => 'Asesor',
        ];
        foreach ($merge as $range => $val) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $val);
        }
        foreach (['H3' => 'TC', 'I3' => '%', 'J3' => 'S/', 'K3' => 'C.'] as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $lastData = 3 + $this->rowCount;
        if ($lastData >= 2) {
            $sheet->getStyle("A2:{$lastCol}{$lastData}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
            ]);
        }
    }

    private function buildDataStyles(Worksheet $sheet): void
    {
        $widths = ['A' => 8, 'B' => 7, 'C' => 9, 'D' => 11, 'E' => 34, 'F' => 8, 'G' => 11, 'H' => 5,
            'I' => 7, 'J' => 11, 'K' => 6, 'L' => 11, 'M' => 11, 'N' => 11, 'O' => 13, 'P' => 13,
            'Q' => 13, 'R' => 10, 'S' => 15, 'T' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        if ($this->rowCount < 1) {
            return;
        }

        $first = 4;
        $last  = 3 + $this->rowCount;

        $sheet->getStyle("A{$first}:T{$last}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['G', 'J', 'L', 'M', 'N'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach ($this->rowMeta as $row => $m) {
            if (($m['estado'] ?? '') === 'Vencida') {
                $sheet->getStyle("A{$row}:T{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_YELLOW]],
                ]);
                $sheet->getStyle("R{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
            $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB('FF0000');
            if ($m['tipo'] === 1) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('0000FF');
            } elseif ($m['tipo'] === 3) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
        }
    }

    private function buildTotals(Worksheet $sheet): void
    {
        $t = $this->totals;
        $r = 3 + $this->rowCount;

        $line = function (int $row, string $label, float $div) use ($sheet, $t) {
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("G{$row}", number_format(($t['cuota']   ?? 0) / $div, 2));
            $sheet->setCellValue("J{$row}", number_format(($t['interes'] ?? 0) / $div, 2));
            $sheet->setCellValue("L{$row}", number_format(($t['total']   ?? 0) / $div, 2));
            $sheet->setCellValue("M{$row}", number_format(($t['pago']    ?? 0) / $div, 2));
            $sheet->setCellValue("N{$row}", number_format(($t['saldo']   ?? 0) / $div, 2));
            $this->markTotalRow($sheet, $row, 'T');
            foreach (['G', 'J', 'L', 'M', 'N'] as $c) {
                $sheet->getStyle("{$c}{$row}")->getFont()->getColor()->setRGB(self::COLOR_BLUE);
            }
        };

        $line($r + 1, 'Total Soles', 1);
        $tc = $this->tc > 0 ? $this->tc : 1;
        $line($r + 2, 'Total Dólares', $tc);
    }
}
