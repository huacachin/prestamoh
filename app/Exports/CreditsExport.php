<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\ExchangeRate;
use App\Models\Payment;
use Carbon\Carbon;
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
 * Excel del listado /credits — réplica de reporte1.php. Título "RESUMEN DE CREDITO".
 * Tabla principal de 21 columnas con cabecera de 2 filas (grupo "Interes": TC/%/S//C.),
 * filas Total Soles/Dólares, clasificación MORA/ACTIVOS/TOTAL y 2 tablas resumen.
 *
 * Nota: la 3ª tabla resumen del legacy (desglose por % de interés) leía de la tabla
 * temporal huaca_tmp poblada por otro proceso; no se replica aquí.
 */
class CreditsExport implements FromCollection, WithMapping, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    private const COLOR_DARK = '005F8C';
    private const COLOR_YELLOW = 'FFFF00';

    protected array $aggMap = [];   // credit_id => ['iapli','aplido','otop']
    protected array $lastPay = [];  // credit_id => fecha
    protected int $rowCount = 0;
    /** @var array<int,array{tipo:int,estado:string,ref:bool}> estilo por fila de datos */
    protected array $rowMeta = [];
    /** @var array<string,array> desglose por % de interés (key = porce) */
    protected array $byInteres = [];

    // Totales generales
    protected float $totototo = 0;  // Capital
    protected float $wewe = 0;      // S/ (interés, créditos con cuotas pendientes)
    protected float $rcapi = 0;     // Total
    protected float $todf = 0;      // Pagado
    protected float $trtr = 0;      // Saldo

    // Mora / Activos
    protected float $morisidad = 0; protected int $persomoro = 0; protected float $impo_2 = 0; protected float $impo_inte = 0; protected float $mes_mes = 0;
    protected float $morisidadin = 0; protected int $persomoroin = 0; protected float $impo_2in = 0; protected float $impo_intein = 0; protected float $mes_mesin = 0;

    // Conteos / planilla
    protected int $vignt = 0; protected int $venc = 0;
    protected int $sempo = 0; protected int $mempo = 0; protected int $dempo = 0;
    protected float $totsem = 0; protected float $totmen = 0; protected float $totdia = 0;
    protected float $totintesem = 0; protected float $totintemen = 0; protected float $totintdiario = 0;

    public function __construct(
        protected string $nombre = '',
        protected string $codigo = '',
        protected string $ejecutivo = '',
        protected string $seletipl = '',
    ) {}

    /** Datos desde fila 4: fila1=título, filas 2-3=cabecera multi-fila. */
    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $query = Credit::query()
            ->with([
                'client:id,expediente,nombre,apellido_pat,apellido_mat,documento,celular1,asesor_id',
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
            $query->where('id', 'like', '%' . trim($this->codigo) . '%');
        }
        if (trim($this->ejecutivo) !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }
        if (trim($this->seletipl) !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }

        $credits = $query->orderBy('fecha_vencimiento')->get();
        $this->rowCount = $credits->count();

        $ids = $credits->pluck('id')->all();
        if (!empty($ids)) {
            $sums = CreditInstallment::whereIn('credit_id', $ids)
                ->selectRaw('credit_id, sum(importe_aplicado) iapli, sum(interes_aplicado) aplido, sum(case when pagado = 0 and importe_cuota <> 0 then 1 else 0 end) otop')
                ->groupBy('credit_id')->get();
            foreach ($sums as $s) {
                $this->aggMap[$s->credit_id] = ['iapli' => (float) $s->iapli, 'aplido' => (float) $s->aplido, 'otop' => (int) $s->otop];
            }
            foreach (Payment::whereIn('credit_id', $ids)->selectRaw('credit_id, max(fecha) m')->groupBy('credit_id')->get() as $p) {
                $this->lastPay[$p->credit_id] = $p->m;
            }
        }

        $hoy = Carbon::today()->format('Y-m-d');
        foreach ($credits as $c) {
            $iapli  = $this->aggMap[$c->id]['iapli'] ?? 0;
            $aplido = $this->aggMap[$c->id]['aplido'] ?? 0;
            $otop   = $this->aggMap[$c->id]['otop'] ?? 0;
            $int    = round(($c->importe * $c->interes) / 100, 2);
            $saldo  = round((float) $c->importe + $int - $iapli - $aplido, 2);
            $venc   = $c->fecha_vencimiento?->format('Y-m-d');

            $this->totototo += (float) $c->importe;
            $this->wewe     += $otop > 0 ? $int : 0;
            $this->rcapi    += (float) $c->importe + $int;
            $this->todf     += $iapli + $aplido;
            $this->trtr     += $saldo;

            // Mora (vencida con saldo) vs Activos
            if ($venc && $venc < $hoy && $saldo > 0) {
                $this->morisidad += $saldo; $this->persomoro++;
                $this->impo_2 += (float) $c->importe; $this->impo_inte += $int; $this->mes_mes += $saldo;
            } else {
                $this->morisidadin += $saldo; $this->persomoroin++;
                $this->impo_2in += (float) $c->importe; $this->impo_intein += $int; $this->mes_mesin += $saldo;
            }

            // Vigente / Vencida
            if ($venc && $venc > $hoy) { $this->vignt++; } else { $this->venc++; }

            // Planilla
            match ((int) $c->tipo_planilla) {
                1 => [$this->sempo++, $this->totsem += (float) $c->importe, $this->totintesem += $int],
                3 => [$this->mempo++, $this->totmen += (float) $c->importe, $this->totintemen += $int],
                4 => [$this->dempo++, $this->totdia += (float) $c->importe, $this->totintdiario += $int],
                default => null,
            };

            // Desglose por % de interés
            $key = (string) (float) $c->interes;
            if (!isset($this->byInteres[$key])) {
                $this->byInteres[$key] = ['porce' => $key, 'ncount' => 0, 'capital' => 0, 'interes' => 0, 'pago' => 0, 'total' => 0];
            }
            $pagapaga = $iapli + $aplido;
            $this->byInteres[$key]['ncount']++;
            $this->byInteres[$key]['capital'] += (float) $c->importe;
            $this->byInteres[$key]['interes'] += $int;
            $this->byInteres[$key]['pago'] += $pagapaga;
            $this->byInteres[$key]['total'] += ((float) $c->importe + $int) - $pagapaga;
        }

        ksort($this->byInteres, SORT_NATURAL);

        return $credits;
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $cli = $c->client;
        $nombre = $cli ? trim(($cli->apellido_pat ?? '') . ' ' . ($cli->apellido_mat ?? '') . ' ' . ($cli->nombre ?? '')) : '';
        $iapli  = $this->aggMap[$c->id]['iapli'] ?? 0;
        $aplido = $this->aggMap[$c->id]['aplido'] ?? 0;
        $otop   = $this->aggMap[$c->id]['otop'] ?? 0;
        $int    = round(($c->importe * $c->interes) / 100, 2);
        $hoy    = Carbon::today();
        $venc   = $c->fecha_vencimiento;
        $estado = $venc && $venc->format('Y-m-d') > $hoy->format('Y-m-d') ? 'Vigente' : 'Vencida';
        $tc     = match ((int) $c->tipo_planilla) { 1 => 'S.', 3 => 'M.', 4 => 'D.', default => '' };

        // Estilo por fila (datos desde la fila 4)
        $this->rowMeta[3 + $i] = [
            'tipo'   => (int) $c->tipo_planilla,
            'estado' => $estado,
            'ref'    => ($c->cod_rem === 'REF'),
        ];

        return [
            $i,                                          // A  Nº
            $cli?->expediente,                           // B  Exp
            $c->id,                                      // C  Codigo
            (string) ($cli?->documento ?? ''),           // D  DNI
            $nombre,                                     // E  Nombre y Apellidos
            $c->cod_rem,                                 // F  Dt.
            (float) $c->importe,                         // G  Capital
            $tc,                                         // H  TC
            (float) $c->interes,                         // I  %
            $int,                                        // J  S/
            $otop,                                       // K  C.
            (float) $c->importe + $int,                  // L  Total
            $iapli + $aplido,                            // M  Pago
            round((float) $c->importe + $int - $iapli - $aplido, 2), // N  Saldo
            $c->fecha_actualizacion?->format('Y-m-d'),   // O  Fec/Cred
            $venc?->format('Y-m-d'),                      // P  Fec/Venc
            $this->lastPay[$c->id] ?? '',                // Q  Fec/Ult/Pag
            (string) ($cli?->celular1 ?? ''),            // R  Cel/Titu
            $estado,                                     // S  Estado
            $this->tiempoTexto($venc),                   // T  Tiempo
            $cli?->asesor?->name ?? $cli?->asesor?->username ?? '', // U  Ases
        ];
    }

    private function tiempoTexto($venc): string
    {
        if (!$venc) return '';
        $abs = abs(Carbon::today()->diffInDays(Carbon::parse($venc), false));
        $meses = intdiv($abs, 30);
        $dias = $abs % 30;
        return ($meses > 0 ? $meses . ' mes' . ($meses > 1 ? 'es' : '') . ' ' : '') . $dias . ' día' . ($dias != 1 ? 's' : '');
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
        $sheet->setCellValue('A1', 'RESUMEN DE CREDITO');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $merge = [
            'A2:A3' => 'Nº', 'B2:B3' => 'Exp', 'C2:C3' => 'Codigo', 'D2:D3' => 'DNI', 'E2:E3' => 'Nombre y Apellidos',
            'F2:F3' => 'Dt.', 'G2:G3' => 'Capital', 'H2:K2' => 'Interes', 'L2:L3' => 'Total', 'M2:M3' => 'Pago',
            'N2:N3' => 'Saldo', 'O2:O3' => 'Fec/Cred', 'P2:P3' => 'Fec/Venc', 'Q2:Q3' => 'Fec/Ult/Pag',
            'R2:R3' => 'Cel/Titu', 'S2:S3' => 'Estado', 'T2:T3' => 'Tiempo', 'U2:U3' => 'Ases',
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
        // Anchos de columna (réplica aproximada de reporte1.php)
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

        // Alineación centrada de todos los datos
        $sheet->getStyle("A{$first}:U{$last}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Formato de moneda (2 decimales + miles) en columnas numéricas
        foreach (['G', 'J', 'L', 'M', 'N'] as $col) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Colores por fila/celda (réplica del legacy)
        foreach ($this->rowMeta as $row => $m) {
            // Filas refinanciadas (REF) con fondo amarillo
            if (!empty($m['ref'])) {
                $sheet->getStyle("A{$row}:U{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_YELLOW]],
                ]);
            }
            // Dt. (cod_rem) en rojo
            $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB('FF0000');
            // T.C.: Semanal azul, Mensual rojo
            if ($m['tipo'] === 1) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('0000FF');
            } elseif ($m['tipo'] === 3) {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
            // Estado "Vencida" en rojo
            if (($m['estado'] ?? '') === 'Vencida') {
                $sheet->getStyle("S{$row}")->getFont()->getColor()->setRGB('FF0000');
            }
        }
    }

    private function buildTotals(Worksheet $sheet): void
    {
        $r = 3 + $this->rowCount;

        // ── Total Soles ──
        $r++;
        $sheet->mergeCells("B{$r}:E{$r}"); $sheet->setCellValue("B{$r}", 'Total Soles');
        $sheet->setCellValue("G{$r}", number_format($this->totototo, 2));
        $sheet->setCellValue("J{$r}", number_format($this->wewe, 2));
        $sheet->setCellValue("L{$r}", number_format($this->rcapi, 2));
        $sheet->setCellValue("M{$r}", number_format($this->todf, 2));
        $sheet->setCellValue("N{$r}", number_format($this->trtr, 2));
        $this->markTotalRow($sheet, $r, 'U');

        // ── Total Dólares ──
        $tc = (float) (ExchangeRate::orderByDesc('fecha')->value('compra') ?: 0);
        if ($tc > 0) {
            $r++;
            $sheet->mergeCells("B{$r}:E{$r}"); $sheet->setCellValue("B{$r}", 'Total Dolares');
            $sheet->setCellValue("G{$r}", number_format($this->totototo / $tc, 2));
            $sheet->setCellValue("J{$r}", number_format($this->wewe / $tc, 2));
            $sheet->setCellValue("L{$r}", number_format($this->rcapi / $tc, 2));
            $sheet->setCellValue("M{$r}", number_format($this->todf / $tc, 2));
            $sheet->setCellValue("N{$r}", number_format($this->trtr / $tc, 2));
            $this->markTotalRow($sheet, $r, 'U');
        }

        // ── MORA / ACTIVOS / TOTAL ──
        $den = $this->trtr != 0 ? $this->trtr : 1;
        $blocks = [
            ['pct' => ($this->morisidad * 100) / $den, 'lbl' => 'MORA', 'lblColor' => 'FF0000', 'cnt' => $this->persomoro, 'cap' => $this->impo_2, 'int' => $this->impo_inte, 'tot' => $this->impo_inte + $this->impo_2, 'mes' => $this->mes_mes],
            ['pct' => ($this->morisidadin * 100) / $den, 'lbl' => 'ACTIVOS', 'lblColor' => '008000', 'cnt' => $this->persomoroin, 'cap' => $this->impo_2in, 'int' => $this->impo_intein, 'tot' => $this->impo_intein + $this->impo_2in, 'mes' => $this->mes_mesin],
            ['pct' => 100, 'lbl' => 'TOTAL', 'lblColor' => self::COLOR_DARK, 'cnt' => $this->persomoro + $this->persomoroin, 'cap' => $this->impo_2 + $this->impo_2in, 'int' => $this->impo_inte + $this->impo_intein, 'tot' => $this->impo_inte + $this->impo_2 + $this->impo_intein + $this->impo_2in, 'mes' => $this->mes_mes + $this->mes_mesin],
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
            $sheet->setCellValue("N{$r}", number_format($b['mes'], 2));
            // Estilos: B coloreado, C/D azul oscuro, G..N amarillo
            // Columna A: el % con el color del bloque (MORA rojo / ACTIVOS verde / TOTAL azul)
            $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $b['lblColor']]]]);
            $sheet->getStyle("B{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $b['lblColor']]]]);
            $sheet->getStyle("C{$r}:F{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_DARK]]]);
            $sheet->getStyle("G{$r}:N{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_YELLOW]],
            ]);
            // Capital (G), Total (L) y Saldo (N) en rojo dentro de las celdas amarillas
            foreach (['G', 'L', 'N'] as $rc) {
                $sheet->getStyle("{$rc}{$r}")->getFont()->getColor()->setRGB('FF0000');
            }
            $sheet->getStyle("A{$r}:U{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // ── Tabla resumen 1: Vigente / Vencidas / Total ──
        $r += 2;
        $this->summaryTable($sheet, $r, ['Tipo', 'Total'], [
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

    /** Tabla por % de interés: CRÉDITO (%, Cnt., Capital, Interés, Pagado, Total). */
    private function interesTable(Worksheet $sheet, int $start): void
    {
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

    /** Tabla resumen simple (cabecera azul + filas, última = Total celeste). */
    private function summaryTable(Worksheet $sheet, int $start, array $headers, array $rows): void
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

    /** Tabla resumen por planilla: Tipo, Cantidad, Capital, Interes, 50%, 33%, 25%. */
    private function planillaTable(Worksheet $sheet, int $start): void
    {
        $head = ['Tipo', 'Cantidad', 'Capital', 'Interes', '50%', '33%', '25%'];
        foreach ($head as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1) . $start, $h);
        }
        $sheet->getStyle("A{$start}:G{$start}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        $rows = [
            ['Semanal', $this->sempo, $this->totsem, $this->totintesem],
            ['Mensual', $this->mempo, $this->totmen, $this->totintemen],
            ['Diario',  $this->dempo, $this->totdia, $this->totintdiario],
            ['Total', $this->sempo + $this->mempo + $this->dempo, $this->totsem + $this->totmen + $this->totdia, $this->totintesem + $this->totintemen + $this->totintdiario],
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
}
