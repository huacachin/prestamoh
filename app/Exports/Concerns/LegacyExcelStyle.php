<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
    public const COLOR_TITULO = 'FF0000'; // rojo

    public const COLOR_HEADER = '2874A6'; // azul

    public const COLOR_TOTAL = 'CEE7FF'; // celeste claro

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
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::COLOR_TITULO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Headers (fila 2) ───────────────────────────────────────────
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
        ]);

        // ── Bordes punteados en toda la tabla (headers + datos) ─────────
        if ($highestRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '999999']],
                ],
            ]);
        }

        // Fuente base 10 + auto-ancho (común a todos los exports)
        $this->applyBaseFontAndAutosize($sheet);
    }

    /**
     * Centra (H+V) el rango de datos y, opcionalmente, aplica formato de moneda
     * a columnas de montos. Llamar ANTES de agregar las filas de totales (cuando
     * getHighestRow() aún apunta a la última fila de datos).
     *
     * @param  string[]  $moneyCols  Columnas de monto a formatear como #,##0.00
     */
    protected function styleDataRange(Worksheet $sheet, string $lastCol, array $moneyCols = [], int $firstDataRow = 3): void
    {
        $lastDataRow = $sheet->getHighestRow();
        if ($lastDataRow < $firstDataRow) {
            return; // sin filas de datos
        }

        // Centrado de todo el rango de datos.
        $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$lastDataRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Formato de moneda en columnas de montos.
        foreach ($moneyCols as $col) {
            $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
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
        return Coordinate::stringFromColumnIndex($n);
    }

    /**
     * Estilo base común a TODOS los exports:
     *  - Fuente tamaño 10 en todo el libro (default style).
     *  - Ancho de columna ajustado al contenido, con un tope máximo (evita columnas
     *    absurdamente anchas por un texto largo) y un mínimo. Ignora las celdas
     *    combinadas (título, totales, grupos) para que no inflen el ancho.
     * Llamar al FINAL del AfterSheet (cuando ya está todo escrito). Los exports NO
     * deben implementar ShouldAutoSize (sobrescribiría estos anchos).
     */
    protected function applyBaseFontAndAutosize(Worksheet $sheet, int $maxWidth = 48, int $minWidth = 5): void
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);

        // Celdas dentro de un merge no cuentan para el ancho de su columna.
        $merged = [];
        foreach ($sheet->getMergeCells() as $range) {
            foreach (Coordinate::extractAllCellReferencesInRange($range) as $ref) {
                $merged[$ref] = true;
            }
        }

        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = $sheet->getHighestRow();

        for ($cI = 1; $cI <= $highestCol; $cI++) {
            $col = Coordinate::stringFromColumnIndex($cI);
            $maxLen = 0;
            for ($r = 2; $r <= $highestRow; $r++) { // r=2: omite la fila 1 (título)
                $ref = $col . $r;
                if (isset($merged[$ref]) || ! $sheet->cellExists($ref)) {
                    continue;
                }
                $len = mb_strlen((string) $sheet->getCell($ref)->getFormattedValue());
                if ($len > $maxLen) {
                    $maxLen = $len;
                }
            }
            $dim = $sheet->getColumnDimension($col);
            $dim->setAutoSize(false);
            $dim->setWidth(max($minWidth, min($maxWidth, $maxLen + 2)));
        }
    }
}
