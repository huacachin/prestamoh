<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Excel del listado /clients (y /clients/ceased) — réplica de clienteex.php.
 * Título "CLIENTES". DNI como texto (no pierde ceros a la izquierda).
 */
class ClientsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents
{
    use LegacyExcelStyle;

    public function __construct(
        protected string $status = 'active',
        protected string $nexpediente = '',
        protected string $documento = '',
        protected string $nombre = '',
        protected string $ruta = '',
        protected string $ejecutivo = '',
        protected ?int   $userId = null,
        protected bool   $scopePropio = false,
    ) {}

    public function collection()
    {
        $query = Client::query()
            ->where('status', $this->status)
            ->with(['asesor:id,name,username', 'headquarter:id,name']);

        if ($this->scopePropio && $this->userId) {
            $query->where('asesor_id', $this->userId);
        }
        if (trim($this->documento) !== '') {
            $query->where('documento', trim($this->documento));
        }
        if (trim($this->nombre) !== '') {
            $t = trim($this->nombre);
            $query->where(function ($q) use ($t) {
                $q->where('nombre', 'like', "%{$t}%")
                  ->orWhere('apellido_pat', 'like', "%{$t}%")
                  ->orWhere('apellido_mat', 'like', "%{$t}%");
            });
        }
        if (trim($this->nexpediente) !== '') {
            $query->where('expediente', trim($this->nexpediente));
        }
        if (trim($this->ejecutivo) !== '') {
            if ($this->ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $query->where('asesor_id', $this->ejecutivo);
            }
        }
        if (trim($this->ruta) !== '') {
            $query->where('zona', 'like', '%' . trim($this->ruta) . '%');
        }

        return $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->get();
    }

    public function headings(): array
    {
        return ['Nº', 'Exp.', 'DNI', 'Cliente', 'Teléfono', 'Dirección', 'Ruta', 'Asesor', 'Sucursal'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $nombre = trim(($c->apellido_pat ?? '') . ' ' . ($c->apellido_mat ?? '') . ' ' . ($c->nombre ?? ''));

        return [
            $i,
            $c->expediente,
            (string) $c->documento,
            $nombre,
            (string) $c->telefono,
            $c->direccion,
            $c->zona,
            $c->asesor?->name ?? $c->asesor?->username ?? '',
            $c->headquarter?->name ?? '',
        ];
    }

    /** DNI (C) y Teléfono (E) como texto: no perder ceros a la izquierda. */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $titulo = $this->status === 'inactive' ? 'CLIENTES CESADOS' : 'CLIENTES';
                $this->applyLegacyStyle($event->sheet->getDelegate(), $titulo, 'I');
            },
        ];
    }
}
