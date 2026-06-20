<?php

namespace App\Exports;

use App\Exports\Concerns\LegacyExcelStyle;
use App\Models\Client;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Excel del listado /clients (y /clients/ceased) — réplica de clienteex.php.
 * Título "CLIENTES". DNI como texto (no pierde ceros a la izquierda).
 */
class ClientsExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    use LegacyExcelStyle;

    public function __construct(
        protected string $status = 'active',
        protected string $nexpediente = '',
        protected string $documento = '',
        protected string $nombre = '',
        protected string $ruta = '',
        protected string $ejecutivo = '',
        protected ?int $userId = null,
        protected bool $scopePropio = false,
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
            $query->where('zona', 'like', '%'.trim($this->ruta).'%');
        }

        return $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->get();
    }

    /** ¿Es el listado de cesados? Define el set de columnas (clienteex_x.php vs clienteex.php). */
    private function esCesado(): bool
    {
        return $this->status === 'inactive';
    }

    public function headings(): array
    {
        if ($this->esCesado()) {
            // clienteex_x.php (9 cols)
            return ['Nº', 'Fecha', 'Usuario', 'Exp.', 'Nombres Apellidos', 'DNI', 'Movil', 'Teléfono', 'Asesor'];
        }

        // clienteex.php (11 cols)
        return ['Nº', 'Fecha', 'Usuario', 'Exp.', 'Nombres Apellidos', 'DNI', 'Movil', 'Ruta', 'Teléfono', 'Asesor', 'Dirección'];
    }

    public function map($c): array
    {
        static $i = 0;
        $i++;

        $nombre = trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? ''));
        $fecha = $c->fecha_registro
            ? Carbon::parse($c->fecha_registro)->format('d/m/Y')
            : '';
        $asesor = $c->asesor?->name ?? $c->asesor?->username ?? '';

        if ($this->esCesado()) {
            return [
                $i,
                $fecha,
                $c->usuario,
                $c->expediente,
                $nombre,
                (string) $c->documento,
                (string) $c->celular1,
                (string) $c->celular2,
                $asesor,
            ];
        }

        return [
            $i,
            $fecha,
            $c->usuario,
            $c->expediente,
            $nombre,
            (string) $c->documento,
            (string) $c->celular1,   // Movil
            $c->zona,                // Ruta (legacy cnomzona)
            (string) $c->celular2,   // Teléfono (legacy ntelefono)
            $asesor,
            $c->direccion,
        ];
    }

    /** DNI y celulares como texto: no perder ceros a la izquierda. */
    public function columnFormats(): array
    {
        if ($this->esCesado()) {
            // Nº A, Fecha B, Usuario C, Exp D, Nombre E, DNI F, Movil G, Teléfono H, Asesor I
            return [
                'F' => NumberFormat::FORMAT_TEXT,
                'G' => NumberFormat::FORMAT_TEXT,
                'H' => NumberFormat::FORMAT_TEXT,
            ];
        }

        // Nº A, Fecha B, Usuario C, Exp D, Nombre E, DNI F, Movil G, Ruta H, Teléfono I, Asesor J, Dir K
        return [
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $titulo = $this->esCesado() ? 'CLIENTES CESADOS' : 'CLIENTES';
                $lastCol = $this->esCesado() ? 'I' : 'K';
                // Sin montos: solo centrado de datos.
                $this->styleDataRange($sheet, $lastCol);
                $this->applyLegacyStyle($sheet, $titulo, $lastCol);
            },
        ];
    }
}
