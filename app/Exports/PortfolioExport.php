<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Livewire\Reports\Portfolio;
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
 * Excel del reporte /reports/portfolio (Cartera) — réplica de reporte1.php.
 * Título "RESUMEN DE CREDITOS". Tabla principal 21 cols (cabecera 2 filas, grupo Interés),
 * Total Soles/Dólares, clasificación MORA/ACTIVOS/TOTAL y 3 tablas resumen
 * (Vigente/Vencidas, por planilla, y por % de interés). Datos del componente Portfolio.
 */
class PortfolioExport implements FromCollection, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    private const COLOR_DARK = '005F8C';
    private const COLOR_YELLOW = 'FFFF00';

    /** @var array<int,array{tipo:int,estado:string,ref:bool}> estilo por fila de datos */
    protected array $rowMeta = [];

    protected array $totals = [];
    protected array $morisidad = [];
    protected array $tipoTotals = [];
    protected array $byInteres = [];
    protected int $vignt = 0;
    protected int $venc = 0;
    protected float $tc = 1;
    protected int $rowCount = 0;

    public function __construct(
        protected array $filters = [],
    ) {}

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $c = new Portfolio();
        $c->selemes0  = (string) ($this->filters['selemes0']  ?? '');
        $c->selecano0 = (string) ($this->filters['selecano0'] ?? '');
        $c->seletipl0 = (string) ($this->filters['seletipl0'] ?? '');
        $c->exp       = (string) ($this->filters['exp']       ?? '');
        $c->codigo    = (string) ($this->filters['codigo']    ?? '');
        $c->cdni      = (string) ($this->filters['cdni']      ?? '');
        $c->cnombre   = (string) ($this->filters['cnombre']   ?? '');
        $c->casesor   = (string) ($this->filters['casesor']   ?? '');
        $c->fechai    = (string) ($this->filters['fechai']    ?? '');
        $c->fechaf    = (string) ($this->filters['fechaf']    ?? '');

        $data = $c->render()->getData();
        $rows = collect($data['rows'] ?? []);
        $this->totals     = $data['totals'] ?? [];
        $this->morisidad  = $data['morisidad'] ?? [];
        $this->tipoTotals = $data['tipoTotals'] ?? [];
        $this->byInteres  = $data['byInteres'] ?? [];
        $this->vignt      = (int) ($data['vignt'] ?? 0);
        $this->venc       = (int) ($data['venc'] ?? 0);
        $this->tc         = (float) ($data['tc'] ?? 1) ?: 1;
        $this->rowCount   = $rows->count();

        return $rows;
    }

    public function map($r): array
    {
        static $i = 0;
        $i++;
        $this->rowMeta[3 + $i] = [
            'tipo'   => (int) ($r['tipo_planilla'] ?? 0),
            'estado' => $r['estado'] ?? '',
            'ref'    => !empty($r['is_refi']),
        ];

        return [
            $r['n']             ?? '',  // A  Nº
            $r['exp']           ?? '',  // B  Exp
            $r['codigo']        ?? '',  // C  Código
            (string) ($r['dni'] ?? ''), // D  DNI
            $r['cliente']       ?? '',  // E  Apellidos y Nombres
            $r['cod_rem']       ?? '',  // F  Dt.
            (float) ($r['capital']        ?? 0), // G  Capital
            $r['tc_label']      ?? '',  // H  TC
            (float) ($r['interes_pct']    ?? 0), // I  %
            (float) ($r['interes_monto']  ?? 0), // J  S/
            $r['cuotas']        ?? '',  // K  C.
            (float) ($r['total']          ?? 0), // L  Total
            (float) ($r['pago']           ?? 0), // M  Pago
            (float) ($r['saldo']          ?? 0), // N  Saldo
            $r['fecha_cred']     ?? '', // O  Fec/Cred
            $r['fecha_venc']     ?? '', // P  Fec/Venc
            $r['fecha_ult_pago'] ?? '', // Q  Fec/Ult/Pag
            (string) ($r['celular'] ?? ''), // R  Cel/Titu
            $r['estado']         ?? '', // S  Estado
            $r['tiempo']         ?? '', // T  Tiempo
            $r['asesor']         ?? '', // U  Asesor
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildTitleAndHeader($sheet, 'U');
                $this->buildDataStyles($sheet);
                $this->buildTotals($sheet);
            },
        ];
    }

    private function buildTitleAndHeader(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('A1', 'RESUMEN DE CREDITOS');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $merge = [
            'A2:A3' => 'Nº', 'B2:B3' => 'Exp', 'C2:C3' => 'Código', 'D2:D3' => 'DNI', 'E2:E3' => 'Apellidos y Nombres',
            'F2:F3' => 'Dt.', 'G2:G3' => 'Capital', 'H2:K2' => 'Interés', 'L2:L3' => 'Total', 'M2:M3' => 'Pago',
            'N2:N3' => 'Saldo', 'O2:O3' => 'Fec/Cred', 'P2:P3' => 'Fec/Venc', 'Q2:Q3' => 'Fec/Ult/Pag',
            'R2:R3' => 'Cel/Titu', 'S2:S3' => 'Estado', 'T2:T3' => 'Tiempo', 'U2:U3' => 'Asesor',
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

    /** Formato de números, alineación, colores por dato y anchos de columna. */
    private function buildDataStyles(Worksheet $sheet): void
    {
        $widths = ['A' => 8, 'B' => 7, 'C' => 9, 'D' => 11, 'E' => 34, 'F' => 9, 'G' => 12, 'H' => 8,
            'I' => 7, 'J' => 11, 'K' => 6, 'L' => 12, 'M' => 12, 'N' => 12, 'O' => 13, 'P' => 13,
            'Q' => 15, 'R' => 13, 'S' => 10, 'T' => 15, 'U' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        if ($this->rowCount < 1) {
            return;
        }

        $first = 4;
        $last  = 3 + $this->rowCount;

        $sheet->getStyle("A{$first}:U{$last}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['G', 'J', 'L', 'M', 'N'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach ($this->rowMeta as $row => $m) {
            if (!empty($m['ref'])) {
                $sheet->getStyle("A{$row}:U{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_YELLOW]],
                ]);
            }
            $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB('FF0000');
            if ($m['tipo'] === 1) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('0000FF');
            } elseif ($m['tipo'] === 3) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
            if (($m['estado'] ?? '') === 'Vencida') {
                $sheet->getStyle("S{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
        }
    }

    private function buildTotals(Worksheet $sheet): void
    {
        $t = $this->totals;
        $r = 3 + $this->rowCount;

        // ── Total Soles ──
        $r++;
        $sheet->mergeCells("A{$r}:E{$r}"); $sheet->setCellValue("A{$r}", 'Total Soles');
        $sheet->setCellValue("G{$r}", number_format($t['capital'] ?? 0, 2));
        $sheet->setCellValue("J{$r}", number_format($t['interes'] ?? 0, 2));
        $sheet->setCellValue("L{$r}", number_format($t['total']   ?? 0, 2));
        $sheet->setCellValue("M{$r}", number_format($t['pago']    ?? 0, 2));
        $sheet->setCellValue("N{$r}", number_format($t['saldo']   ?? 0, 2));
        $this->markTotalRow($sheet, $r, 'U');

        // ── Total Dólares ──
        $tc = $this->tc > 0 ? $this->tc : 1;
        $r++;
        $sheet->mergeCells("A{$r}:E{$r}"); $sheet->setCellValue("A{$r}", 'Total Dólares');
        $sheet->setCellValue("G{$r}", number_format(($t['capital'] ?? 0) / $tc, 2));
        $sheet->setCellValue("J{$r}", number_format(($t['interes'] ?? 0) / $tc, 2));
        $sheet->setCellValue("L{$r}", number_format(($t['total']   ?? 0) / $tc, 2));
        $sheet->setCellValue("M{$r}", number_format(($t['pago']    ?? 0) / $tc, 2));
        $sheet->setCellValue("N{$r}", number_format(($t['saldo']   ?? 0) / $tc, 2));
        $this->markTotalRow($sheet, $r, 'U');

        // ── MORA / ACTIVOS / TOTAL ──
        $m = $this->morisidad;
        $blocks = [
            ['pct' => $m['mora_pct'] ?? 0, 'lbl' => 'MORA', 'lblColor' => 'FF0000', 'cnt' => $m['mora_count'] ?? 0, 'cap' => $m['mora_capital'] ?? 0, 'int' => $m['mora_interes'] ?? 0, 'tot' => $m['mora_total'] ?? 0, 'sal' => $m['mora_saldo'] ?? 0],
            ['pct' => $m['activos_pct'] ?? 0, 'lbl' => 'ACTIVOS', 'lblColor' => '008000', 'cnt' => $m['activos_count'] ?? 0, 'cap' => $m['activos_capital'] ?? 0, 'int' => $m['activos_interes'] ?? 0, 'tot' => $m['activos_total'] ?? 0, 'sal' => $m['activos_saldo'] ?? 0],
            ['pct' => 100, 'lbl' => 'TOTAL', 'lblColor' => self::COLOR_DARK, 'cnt' => $m['total_count'] ?? 0, 'cap' => $m['total_capital'] ?? 0, 'int' => $m['total_interes'] ?? 0, 'tot' => $m['total_total'] ?? 0, 'sal' => $m['total_saldo'] ?? 0],
        ];
        foreach ($blocks as $b) {
            $r++;
            $sheet->setCellValue("A{$r}", number_format($b['pct'], 2) . '%');
            $sheet->setCellValue("B{$r}", $b['lbl']);
            $sheet->setCellValue("C{$r}", $b['cnt']);
            $sheet->mergeCells("D{$r}:F{$r}"); $sheet->setCellValue("D{$r}", 'TOTAL ' . $b['lbl']);
            $sheet->setCellValue("G{$r}", number_format($b['cap'], 2));
            $sheet->setCellValue("J{$r}", number_format($b['int'], 2));
            $sheet->setCellValue("L{$r}", number_format($b['tot'], 2));
            $sheet->setCellValue("N{$r}", number_format($b['sal'], 2));
            // Columna A: el % con el color del bloque (MORA rojo / ACTIVOS verde / TOTAL azul)
            $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $b['lblColor']]]]);
            $sheet->getStyle("B{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $b['lblColor']]]]);
            $sheet->getStyle("C{$r}:F{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_DARK]]]);
            $sheet->getStyle("G{$r}:N{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_YELLOW]],
            ]);
            foreach (['G', 'L', 'N'] as $rc) {
                $sheet->getStyle("{$rc}{$r}")->getFont()->getColor()->setRGB('FF0000');
            }
            $sheet->getStyle("A{$r}:U{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // ── Tabla resumen 1: Vigente / Vencidas / Total ──
        $r += 2;
        $this->simpleTable($sheet, $r, ['Tipo', 'Total'], [
            ['Vigente', $this->vignt],
            ['Vencidas', $this->venc],
            ['Total', $this->vignt + $this->venc],
        ]);

        // ── Tabla resumen 2: por planilla ──
        $r += 5;
        $this->planillaTable($sheet, $r);

        // ── Tabla resumen 3: por % de interés (CRÉDITO) ──
        $r += 8;
        $this->interesTable($sheet, $r);
    }

    private function simpleTable(Worksheet $sheet, int $start, array $headers, array $rows): void
    {
        $cols = ['A', 'B'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($cols[$i] . $start, $h);
        }
        $sheet->getStyle("A{$start}:B{$start}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);
        $r = $start;
        foreach ($rows as $row) {
            $r++;
            $sheet->setCellValue("A{$r}", $row[0]);
            $sheet->setCellValue("B{$r}", $row[1]);
            if ($row[0] === 'Total') $this->markTotalRow($sheet, $r, 'B');
        }
        $sheet->getStyle("A{$start}:B{$r}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
    }

    private function planillaTable(Worksheet $sheet, int $start): void
    {
        $tt = $this->tipoTotals;
        $head = ['Tipo', 'Cnt.', 'Capital', 'Interés', '50%', '33%', '25%'];
        foreach ($head as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . $start, $h);
        }
        $sheet->getStyle("A{$start}:G{$start}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);
        $rows = [
            ['Semanal', $tt['sempo'] ?? 0, $tt['totsem'] ?? 0, $tt['totintesem'] ?? 0],
            ['Mensual', $tt['mempo'] ?? 0, $tt['totmen'] ?? 0, $tt['totintemen'] ?? 0],
            ['Diario',  $tt['dempo'] ?? 0, $tt['totdia'] ?? 0, $tt['totintdiario'] ?? 0],
            ['Total',
                ($tt['sempo'] ?? 0) + ($tt['mempo'] ?? 0) + ($tt['dempo'] ?? 0),
                ($tt['totsem'] ?? 0) + ($tt['totmen'] ?? 0) + ($tt['totdia'] ?? 0),
                ($tt['totintesem'] ?? 0) + ($tt['totintemen'] ?? 0) + ($tt['totintdiario'] ?? 0)],
        ];
        $r = $start;
        foreach ($rows as $row) {
            $r++;
            $int = (float) $row[3];
            $sheet->setCellValue("A{$r}", $row[0]);
            $sheet->setCellValue("B{$r}", $row[1]);
            $sheet->setCellValue("C{$r}", number_format((float) $row[2], 2));
            $sheet->setCellValue("D{$r}", number_format($int, 2));
            $sheet->setCellValue("E{$r}", number_format($int / 2, 2));
            $sheet->setCellValue("F{$r}", number_format($int / 3, 2));
            $sheet->setCellValue("G{$r}", number_format($int / 4, 2));
            if ($row[0] === 'Total') $this->markTotalRow($sheet, $r, 'G');
        }
        $sheet->getStyle("A{$start}:G{$r}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
    }

    private function interesTable(Worksheet $sheet, int $start): void
    {
        // Título "CRÉDITO" (colspan 6)
        $sheet->mergeCells("A{$start}:F{$start}");
        $sheet->setCellValue("A{$start}", 'CRÉDITO');
        $head = ['%', 'Cnt.', 'Capital', 'Interés', 'Pagado', 'Total'];
        $hr = $start + 1;
        foreach ($head as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . $hr, $h);
        }
        $sheet->getStyle("A{$start}:F{$hr}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $sumCnt = 0; $sumCap = 0; $sumInt = 0; $sumPag = 0; $sumTot = 0;
        $r = $hr;
        foreach ($this->byInteres as $b) {
            $r++;
            $sheet->setCellValue("A{$r}", $b['porce'] ?? '');
            $sheet->setCellValue("B{$r}", $b['ncount'] ?? 0);
            $sheet->setCellValue("C{$r}", number_format($b['capital'] ?? 0, 2));
            $sheet->setCellValue("D{$r}", number_format($b['interes'] ?? 0, 2));
            $sheet->setCellValue("E{$r}", number_format($b['pago'] ?? 0, 2));
            $sheet->setCellValue("F{$r}", number_format($b['total'] ?? 0, 2));
            $sumCnt += $b['ncount'] ?? 0; $sumCap += $b['capital'] ?? 0; $sumInt += $b['interes'] ?? 0;
            $sumPag += $b['pago'] ?? 0; $sumTot += $b['total'] ?? 0;
        }
        $r++;
        $sheet->setCellValue("A{$r}", 'Total');
        $sheet->setCellValue("B{$r}", $sumCnt);
        $sheet->setCellValue("C{$r}", number_format($sumCap, 2));
        $sheet->setCellValue("D{$r}", number_format($sumInt, 2));
        $sheet->setCellValue("E{$r}", number_format($sumPag, 2));
        $sheet->setCellValue("F{$r}", number_format($sumTot, 2));
        $this->markTotalRow($sheet, $r, 'F');

        $sheet->getStyle("A{$start}:F{$r}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']]],
        ]);
    }
}
