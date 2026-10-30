<?php

namespace App\Exports;

use App\Support\RentaSegundaCategoria;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Excel 2da categoría" (30/10/2026, Antony): una hoja con el mes elegido, igual al Excel manual
 * "2-cat-prestamoh" (formato de julio en adelante): título en rojo, cabecera gris de dos filas, una fila
 * por crédito (RentaSegundaCategoria) y el pie con fórmulas — TOTAL CAPITAL (gris), TOTAL CAPITAL MÁS
 * INTERES (verde), A PAGAR IMP. 5% (azul) y el redondeo para la SUNAT (amarillo, letra roja).
 * Descripción va vacía (pedido de Antony).
 */
class RentaSegundaCategoriaExport implements FromArray, WithEvents, WithTitle
{
    private const CONTABLE = '_-* #,##0.00_-;\-* #,##0.00_-;_-* "-"??_-;_-@_-';

    private const FECHA = 'dd/mm/yyyy';

    /** Gris del encabezado (blanco −35 %), gris del total (blanco −50 %) y azul de la letra de las filas. */
    private const GRIS_CABECERA = 'A6A6A6';

    private const GRIS_TOTAL = '808080';

    private const AZUL_LETRA = '2F5597';

    /** @var list<array<string, mixed>> */
    private array $filas;

    public function __construct(private int $anio, private int $mes)
    {
        $this->filas = RentaSegundaCategoria::filas($anio, $mes);
    }

    /** Todo se escribe en AfterSheet: la hoja tiene cabecera de dos filas, celdas combinadas y pie. */
    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return RentaSegundaCategoria::nombreHoja($this->mes);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $event) => $this->armar($event->sheet->getDelegate())];
    }

    private function armar(Worksheet $hoja): void
    {
        $hoja->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        foreach (['A' => 3.7, 'B' => 4.3, 'C' => 11.7, 'D' => 30.4, 'E' => 11.7, 'F' => 11.3, 'G' => 4.4, 'H' => 12.0, 'I' => 10.4, 'J' => 10.4, 'K' => 13.7, 'L' => 24.0, 'M' => 3.7] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        // Título
        $hoja->mergeCells('B1:L1');
        $hoja->setCellValue('B1', 'CREDITOS CANCELADOS - '.RentaSegundaCategoria::nombreMes($this->anio, $this->mes));
        $hoja->getStyle('B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FF0000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Cabecera de dos filas
        foreach (['B' => 'N°', 'C' => 'Dni', 'D' => 'Apellidos y Nombres', 'E' => 'Capital', 'F' => 'Préstamo', 'I' => 'Fec/Cred', 'J' => 'Fec/Venc', 'K' => 'Fecha Cancelado', 'L' => 'Descripción'] as $col => $texto) {
            $hoja->mergeCells("{$col}2:{$col}3");
            $hoja->setCellValue("{$col}2", $texto);
        }
        $hoja->mergeCells('G2:H2');
        $hoja->setCellValue('G2', 'Interes');
        $hoja->setCellValue('G3', '%');
        $hoja->setCellValue('H3', 'S/');
        $hoja->getStyle('B2:L3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::GRIS_CABECERA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Una fila por crédito
        $fila = 4;
        foreach ($this->filas as $n => $f) {
            $hoja->setCellValue("B{$fila}", $n + 1);
            $hoja->setCellValueExplicit("C{$fila}", $f['dni'], DataType::TYPE_STRING);
            $hoja->setCellValue("D{$fila}", $f['nombre']);
            $hoja->setCellValue("E{$fila}", $f['capital']);
            $hoja->setCellValue("F{$fila}", $f['prestamo']);
            $hoja->setCellValue("G{$fila}", $f['pct']);
            $hoja->setCellValue("H{$fila}", $f['interes']);
            foreach (['I' => 'fec_cred', 'J' => 'fec_venc', 'K' => 'fec_cancelado'] as $col => $clave) {
                if ($f[$clave] !== null) {
                    $hoja->setCellValue("{$col}{$fila}", Date::PHPToExcel(Carbon::parse($f[$clave])->startOfDay()));
                }
            }
            $fila++;
        }
        $ultima = $fila - 1;
        if ($ultima >= 4) {
            $hoja->getStyle("B4:L{$ultima}")->applyFromArray([
                'font' => ['color' => ['rgb' => self::AZUL_LETRA]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => [
                    'left' => ['borderStyle' => Border::BORDER_THIN], 'right' => ['borderStyle' => Border::BORDER_THIN],
                    'top' => ['borderStyle' => Border::BORDER_DOTTED], 'bottom' => ['borderStyle' => Border::BORDER_DOTTED],
                    'vertical' => ['borderStyle' => Border::BORDER_THIN], 'horizontal' => ['borderStyle' => Border::BORDER_DOTTED],
                ],
            ]);
            $hoja->getStyle("B4:B{$ultima}")->getFont()->getColor()->setRGB('000000');
            $hoja->getStyle("C4:C{$ultima}")->getNumberFormat()->setFormatCode('@');
            $hoja->getStyle("D4:D{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            foreach (['E', 'F', 'H'] as $col) {
                $hoja->getStyle("{$col}4:{$col}{$ultima}")->getNumberFormat()->setFormatCode(self::CONTABLE);
            }
            $hoja->getStyle("I4:K{$ultima}")->getNumberFormat()->setFormatCode(self::FECHA);
            $hoja->getStyle("L4:L{$ultima}")->getFont()->setSize(11);
        }

        // Pie con fórmulas, como el manual
        $suma = fn (string $col) => $ultima >= 4 ? "=SUM({$col}4:{$col}{$ultima})" : 0;
        $t = $fila;
        $hoja->mergeCells("B{$t}:D{$t}");
        $hoja->setCellValue("B{$t}", 'TOTAL CAPITAL');
        $hoja->setCellValue("E{$t}", $suma('E'));
        $hoja->setCellValue("F{$t}", $suma('F'));
        $hoja->setCellValue("G{$t}", 'INT.');
        $hoja->setCellValue("H{$t}", $suma('H'));
        $this->pie($hoja, "B{$t}:H{$t}", self::GRIS_TOTAL, 'FFFFFF', '#,##0.00');

        $hoja->mergeCells('B'.($t + 1).':E'.($t + 1));
        $hoja->mergeCells('F'.($t + 1).':H'.($t + 1));
        $hoja->setCellValue('B'.($t + 1), 'TOTAL CAPITAL MÁS INTERES');
        $hoja->setCellValue('F'.($t + 1), "=+F{$t}+H{$t}");
        $this->pie($hoja, 'B'.($t + 1).':H'.($t + 1), '00B050', 'FFFFFF', '#,##0.00');

        $hoja->mergeCells('B'.($t + 2).':G'.($t + 2));
        $hoja->setCellValue('B'.($t + 2), 'A PAGAR IMP. 5%');
        $hoja->setCellValue('H'.($t + 2), "=H{$t}*5%");
        $this->pie($hoja, 'B'.($t + 2).':H'.($t + 2), '0070C0', 'FFFFFF', self::CONTABLE);

        $hoja->mergeCells('B'.($t + 3).':G'.($t + 3));
        $hoja->setCellValue('B'.($t + 3), 'Obs: Se redondea para realizar el pago a la Sunat');
        $hoja->setCellValue('H'.($t + 3), '=ROUND(H'.($t + 2).',0)');
        $this->pie($hoja, 'B'.($t + 3).':H'.($t + 3), 'FFFF00', 'FF0000', self::CONTABLE);
        $hoja->getStyle('B'.($t + 3).':H'.($t + 3))->getFont()->setName('Calibri Light');

        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0);
        $hoja->setSelectedCell('A1');
    }

    private function pie(Worksheet $hoja, string $rango, string $fondo, string $letra, string $formato): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $letra]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fondo]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'numberFormat' => ['formatCode' => $formato],
        ]);
    }
}
