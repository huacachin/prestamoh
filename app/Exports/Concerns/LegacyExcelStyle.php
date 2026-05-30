<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Estilo de Excel homologado al legacy (gastos_excel.php, pagosex2.php, etc.).
 *
 * Estructura visual del legacy:
 *   Fila 1  → TÍTULO en rojo, bold, centrado, celda combinada A1:{lastCol}1.
 *   Fila 2  → headers con fondo azul #2874A6, texto blanco, bold, centrado.
 *   Datos   → bordes punteados (dotted), centrados.
 *   Totales → fondo celeste #CEE7FF, bold (se marcan con markTotalRow()).
 *
 * Uso en un Export:
 *   use LegacyExcelStyle;
 *   implements WithCustomStartCell  → startCell() ya provisto (A2).
 *   en registerEvents(): $this->applyLegacyStyle($sheet, 'TITULO', 'H');
 */
trait LegacyExcelStyle
{
    public const COLOR_TITULO  = 'FF0000'; // rojo
    public const COLOR_HEADER  = '2874A6'; // azul
    public const COLOR_TOTAL   = 'CEE7FF'; // celeste claro

    /** WithCustomStartCell: headers en fila 2, datos desde fila 3 (fila 1 = título). */
    public function startCell(): string
    {
        return 'A2';
    }

    /**
     * Aplica título combinado + estilo de header + bordes a toda la tabla.
     * Llamar desde registerEvents() en el AfterSheet.
     */
    protected function applyLegacyStyle(Worksheet $sheet, string $title, string $lastCol, int $headerRow = 2): void
    {
        $highestRow = $sheet->getHighestRow();

        // ── Título (fila 1) ────────────────────────────────────────────
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Headers (fila 2) ───────────────────────────────────────────
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        // ── Bordes punteados en toda la tabla (headers + datos) ─────────
        if ($highestRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']],
                ],
            ]);
        }
    }

    /** Marca una fila como "total": fondo celeste + bold. */
    protected function markTotalRow(Worksheet $sheet, int $row, string $lastCol): void
    {
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_TOTAL]],
        ]);
    }

    /** Convierte un número de columna (1-based) a letra Excel (1→A, 27→AA). */
    protected function colLetter(int $n): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
    }
}
