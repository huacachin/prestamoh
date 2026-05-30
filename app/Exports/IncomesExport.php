<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Income;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Excel del listado /cash/incomes — réplica del legacy ingreso_excel.php.
 * Título "INGRESOS" + totales (General, Fijos, Otros, Capital, Interés, Mora).
 */
class IncomesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    protected float $total = 0;
    protected float $tofijo = 0;
    protected float $totros = 0;
    protected float $tocapi = 0;
    protected float $totinte = 0;
    protected float $totmora = 0;

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

        $incQ = Income::query()
            ->where('caja', 1)
            ->where(function ($q) { $q->where('modo', '<>', 'Compra')->orWhereNull('modo'); })
            ->with('user:id,name,username');

        if (!$this->crossHQ) $incQ->where('headquarter_id', $this->hqId ?? 1);
        if (!$this->editarHistorico && $this->userId) {
            $incQ->where('user_id', $this->userId)->where('reason', 'Diario');
        }
        $this->applyDateFilter($incQ, 'date', $term);
        if ($term !== '') {
            match ($this->tipo) {
                '1' => $incQ->where('reason', 'like', "%{$term}%"),
                '2' => $incQ->where('detail', 'like', "%{$term}%"),
                '3' => $incQ->where('asesor', 'like', "%{$term}%"),
                '4' => $incQ->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ),
                default => null,
            };
        }
        $incomes = $incQ->get();

        $payQ = Payment::query()
            ->with(['credit.client:id,nombre,apellido_pat,apellido_mat', 'user:id,name,username']);

        if (!$this->crossHQ) $payQ->where('headquarter_id', $this->hqId ?? 1);
        if (!$this->editarHistorico && $this->userId) $payQ->where('user_id', $this->userId);

        $this->applyDateFilter($payQ, 'fecha', $term);

        if ($term !== '') {
            match ($this->tipo) {
                '1' => $payQ->whereHas('credit.client', function ($c) use ($term) {
                    $c->where('nombre', 'like', "%{$term}%")
                      ->orWhere('apellido_pat', 'like', "%{$term}%")
                      ->orWhere('apellido_mat', 'like', "%{$term}%");
                }),
                '2' => $payQ->where('detalle', 'like', "%{$term}%"),
                '3' => $payQ->where('asesor', 'like', "%{$term}%"),
                '4' => $payQ->whereHas('user', fn ($u) =>
                    $u->where('username', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ),
                default => null,
            };
        }
        $payments = $payQ->get();

        $rows = new Collection();
        foreach ($incomes as $i) {
            $rows->push((object) [
                'id' => $i->id,
                'date' => $i->date,
                'usuario' => $i->user?->username ?? $i->user?->name ?? '',
                'asesor' => $i->asesor,
                'reason' => $i->reason,
                'detail' => $i->detail,
                'documento' => $i->documento ?? '',
                'modo' => $i->modo,
                'total' => (float) $i->total,
            ]);
        }
        foreach ($payments as $p) {
            $cli = $p->credit?->client;
            $cliN = $cli ? trim($cli->apellido_pat . ' ' . $cli->apellido_mat . ' ' . $cli->nombre) : '';
            $rows->push((object) [
                'id' => $p->id,
                'date' => $p->fecha,
                'usuario' => $p->user?->username ?? $p->user?->name ?? '',
                'asesor' => $p->asesor,
                'reason' => $cliN,
                'detail' => $p->detalle,
                'documento' => $p->tipo,
                'modo' => 'CREDITO',
                'total' => (float) $p->monto,
            ]);
        }

        $rows = $rows->sortBy('id')->values();

        foreach ($rows as $r) {
            $t = (float) $r->total;
            $this->total += $t;
            if ($r->modo === 'Fijos') $this->tofijo += $t;
            elseif ($r->modo === 'Otros') $this->totros += $t;
            $doc = (string) $r->documento;
            if ($doc === 'CAPITAL') $this->tocapi += $t;
            elseif ($doc === 'INTERES') $this->totinte += $t;
            elseif (str_contains($doc, 'MORA')) $this->totmora += $t;
        }

        return $rows;
    }

    private function applyDateFilter($q, string $col, string $term): void
    {
        if ($term !== '' && ($this->fei === '' || $this->fef === '')) return;
        if ($this->fei !== '' && $this->fef !== '') {
            $q->where($col, '>=', $this->fei)->where($col, '<=', $this->fef);
        } else {
            $q->where($col, now()->format('Y-m-d'));
        }
    }

    public function headings(): array
    {
        return ['Nº', 'Fecha', 'Usuario', 'Asesor', 'Categoría', 'Motivo', 'Documento', 'Modo', 'Total'];
    }

    public function map($r): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $r->date?->format('d/m/Y'),
            $r->usuario,
            $r->asesor,
            $r->reason,
            $r->detail,
            $r->documento,
            $r->modo,
            (float) $r->total,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'I';

                $filas = [
                    ['Total General', $this->total],
                    ['Fijos',         $this->tofijo],
                    ['Otros',         $this->totros],
                    ['Capital',       $this->tocapi],
                    ['Interés',       $this->totinte],
                    ['Mora',          $this->totmora],
                ];
                $r = $sheet->getHighestRow();
                foreach ($filas as $f) {
                    $r++;
                    $sheet->setCellValue("A{$r}", $f[0]);
                    $sheet->mergeCells("A{$r}:H{$r}");
                    $sheet->setCellValue("I{$r}", number_format($f[1], 2));
                }

                $this->applyLegacyStyle($sheet, 'INGRESOS', $lastCol);
                $firstTotal = $sheet->getHighestRow() - count($filas) + 1;
                for ($x = $firstTotal; $x <= $sheet->getHighestRow(); $x++) {
                    $this->markTotalRow($sheet, $x, $lastCol);
                }
            },
        ];
    }
}
