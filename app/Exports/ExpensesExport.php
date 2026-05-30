<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /cash/expenses — réplica del legacy gastos_excel.php.
 * Título "EGRESOS" + totales (General, Fijos, Otros, Diario/Mensual/D.M).
 */
class ExpensesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $total = 0;
    protected float $tofijo = 0;
    protected float $totros = 0;
    protected float $sumdiario = 0;
    protected float $summensu = 0;
    protected float $sumdm = 0;

    public function __construct(
        protected string $tipo = '1',
        protected string $compra = '',
        protected string $fei = '',
        protected string $fef = '',
        protected ?int   $userId = null,
        protected bool   $crossHQ = false,
        protected ?int   $hqId = 1,
        protected bool   $editarHistorico = false,
    ) {}

    public function collection()
    {
        $term = trim($this->compra);

        $query = Expense::query()
            ->where('caja', 1)
            ->where(function ($q) {
                $q->where('modo', '<>', 'Compra')->orWhereNull('modo');
            })
            ->with('user:id,name,username');

        if (!$this->crossHQ) {
            $query->where('headquarter_id', $this->hqId ?? 1);
        }
        if (!$this->editarHistorico && $this->userId) {
            $query->where('user_id', $this->userId)->where('reason', 'Diario');
        }

        if ($term !== '' && ($this->fei === '' || $this->fef === '')) {
            // solo búsqueda
        } elseif ($this->fei !== '' && $this->fef !== '') {
            $query->where('date', '>=', $this->fei)->where('date', '<=', $this->fef);
        } else {
            $query->where('date', now()->format('Y-m-d'));
        }

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $query->where('reason', 'like', "%{$term}%"),
                '2' => $query->where('detail', 'like', "%{$term}%"),
                '3' => $query->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ),
                '4' => $query->where('in_charge', 'like', "%{$term}%"),
                default => null,
            };
        }

        $rows = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        foreach ($rows as $e) {
            $t = (float) $e->total;
            $this->total += $t;
            if ($e->modo === 'Fijos') $this->tofijo += $t; else $this->totros += $t;
            if ($e->reason === 'Diario')  $this->sumdiario += $t;
            elseif ($e->reason === 'Mensual') $this->summensu += $t;
            elseif ($e->reason === 'D.M')     $this->sumdm += $t;
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Usuario', 'Responsable', 'Modo', 'Categoría', 'Motivo', 'Documento', 'Total'];
    }

    public function map($e): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $e->date?->format('d/m/Y'),
            $e->user?->username ?? $e->user?->name ?? '',
            $e->in_charge,
            $e->modo,
            $e->reason,
            $e->detail,
            $e->documento,
            (float) $e->total,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'I';
                $dm2 = round($this->sumdm / 2, 2);
                $valor1 = $this->sumdiario + $dm2;
                $valor2 = $this->summensu + $dm2;
                $valor3 = $valor1 + $valor2;

                // Filas de totales (réplica de gastos_excel.php)
                $r = $sheet->getHighestRow();
                $filas = [
                    ['Total General', number_format($this->total, 2), false],
                    ['Fijos',         number_format($this->tofijo, 2), true],  // rojo
                    ['Otros',         number_format($this->totros, 2), false],
                    ['Diario → ' . number_format($valor1, 2),   number_format($this->sumdiario, 2), false],
                    ['Mensual → ' . number_format($valor2, 2),  number_format($this->summensu, 2), false],
                    ['D.M → ' . number_format($dm2, 2),         number_format($this->sumdm, 2), false],
                    ['Fijos Total', number_format($valor3, 2), true],         // rojo
                ];
                foreach ($filas as $f) {
                    $r++;
                    $sheet->setCellValue("A{$r}", $f[0]);
                    $sheet->mergeCells("A{$r}:H{$r}");
                    $sheet->setCellValue("I{$r}", $f[1]);
                    if ($f[2]) {
                        $sheet->getStyle("A{$r}:I{$r}")->getFont()->getColor()->setRGB('FF0000');
                    }
                }

                $this->applyLegacyStyle($sheet, 'EGRESOS', $lastCol);
                // Marcar todas las filas de total en celeste
                $firstTotal = $sheet->getHighestRow() - count($filas) + 1;
                for ($x = $firstTotal; $x <= $sheet->getHighestRow(); $x++) {
                    $this->markTotalRow($sheet, $x, $lastCol);
                }
            },
        ];
    }
}
