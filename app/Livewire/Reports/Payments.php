<?php

namespace App\Livewire\Reports;

use App\Models\Client;
use App\Models\ClientUbicacion;
use App\Models\Credit;
use App\Models\Payment;
use App\Support\Usernames;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Payments extends Component
{
    #[Url(as: 'tipo', except: '1')]
    public $tipo = '1';   // 1=A (cliente), 2=Motivo, 3=Asesor, 4=Usuario

    #[Url(as: 'buscar', except: '')]
    public $compra = '';

    #[Url(as: 'desde')]
    public $fei;

    #[Url(as: 'hasta')]
    public $fef;

    /**
     * 26/09: filtro exacto por cliente (id), para el botón "Reporte de pagos"
     * del cronograma. El texto de "A/" busca por LIKE en nombre y apellidos,
     * así que no sirve para aislar a UNA persona.
     */
    #[Url(as: 'cliente', except: '')]
    public $clienteId = '';

    /**
     * 26/09: filtro exacto por crédito (id). Es el que usan los botones
     * "Reporte de pagos" del cronograma y de la ficha: solo los pagos de ESE
     * crédito, no de todos los del cliente. Manda sobre el filtro de cliente.
     */
    #[Url(as: 'credito', except: '')]
    public $creditoId = '';

    /**
     * Quita el filtro de crédito/cliente y vuelve al rango de HOY. El enlace
     * del cronograma trae "desde la fecha del préstamo" (puede ser años
     * atrás): sin filtro, ese rango cargaba todos los pagos de la empresa en
     * una sola tabla y el navegador se quedaba colgado (26/09).
     */
    public function quitarFiltro(): void
    {
        $this->creditoId = '';
        $this->clienteId = '';
        $this->fei = Carbon::today()->format('Y-m-d');
        $this->fef = Carbon::today()->format('Y-m-d');
    }

    public function mount()
    {
        if (! request()->has('desde')) {
            $this->fei = Carbon::today()->format('Y-m-d');
        }
        if (! request()->has('hasta')) {
            $this->fef = Carbon::today()->format('Y-m-d');
        }
    }

    public function search() {}

    public function render()
    {
        // ─── Listado agrupado por nroentrada+fecha (legacy) ─────────────
        $base = Payment::query()
            ->with([
                'credit:id,client_id',
                // 22/10 (Antony): la columna Map abre la ventana "Direcciones" del cliente:
                // dirección escrita + ubicaciones GPS (client_ubicaciones) con su enlace a Maps.
                'credit.client:id,nombre,apellido_pat,apellido_mat,direccion,referencia,distrito,provincia,departamento,zona',
                'credit.client.ubicaciones',
            ])
            ->where('modo', 'CREDITO');

        if ($this->fei) {
            $base->where('fecha', '>=', $this->fei);
        }
        if ($this->fef) {
            $base->where('fecha', '<=', $this->fef);
        }

        $clienteFiltrado = null;
        $creditoFiltrado = null;
        if ($this->creditoId !== '' && $this->creditoId !== null) {
            $creditoFiltrado = Credit::with('client:id,nombre,apellido_pat,apellido_mat')->find((int) $this->creditoId);
            $clienteFiltrado = $creditoFiltrado?->client;
            $base->where('credit_id', (int) $this->creditoId);
        } elseif ($this->clienteId !== '' && $this->clienteId !== null) {
            $clienteFiltrado = Client::find((int) $this->clienteId);
            $base->whereHas('credit', fn ($q) => $q->where('client_id', (int) $this->clienteId));
        }

        if ($this->compra !== '') {
            $term = $this->compra;
            switch ($this->tipo) {
                case '1':
                    // 10/10 (Antony): palabra por palabra sobre el nombre completo, así
                    // "Torres Tapia Vda de Puma Julia Isabel" (o "Julia Torres") también encuentra.
                    $base->whereHas('credit.client', fn ($q) => $q->nombreContiene($term));
                    break;
                case '2':
                    $base->where('detalle', 'like', "%{$term}%");
                    break;
                case '3':
                    $base->where('asesor', 'like', "%{$term}%");
                    break;
                case '4':
                    $base->where('usuario', 'like', "%{$term}%");
                    break;
            }
        }

        // Lista plana ordenada por fecha + hora (cada movimiento en una fila)
        $payments = (clone $base)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->get();

        // Agrupacion por (credit_id + fecha) para mostrar fila por agrupacion
        $vouchers = $this->vouchersPorCobro($payments);
        $rows = [];
        $contador = 0;
        foreach ($payments->groupBy(fn ($p) => $p->credit_id.'|'.$p->fecha->format('Y-m-d')) as $clave => $group) {
            $first = $group->first();
            $cliente = $first->credit?->client;
            $contador++;
            $rows[] = [
                'n' => $contador,
                'fecha' => $first->fecha->format('Y-m-d'),
                'hora' => $first->hora,
                // Username, no nombre: los pagos migrados ya lo traen y los
                // nuevos guardan el nombre completo — Usernames::de normaliza
                'usuario' => Usernames::de($first->usuario),
                'asesor' => $first->asesor,
                'cliente' => $cliente
                    ? trim(($cliente->apellido_pat ?? '').' '.($cliente->apellido_mat ?? '').' '.($cliente->nombre ?? ''))
                    : '',
                'detalle' => $first->detalle,
                'monto' => $group->sum('monto'),
                'moneda' => $first->moneda,
                'latitud' => $first->latitud,
                'longitud' => $first->longitud,
                // 22/10 (Antony): direcciones del cliente (ventana "Direcciones") y
                // fotos del voucher del cobro (visor flotante), ambas por fila.
                'direcciones' => $cliente ? $this->direccionesDe($cliente, $first) : null,
                'vouchers' => $vouchers[$clave] ?? [],
            ];
        }

        // ─── Totales (sobre los movimientos individuales, NO agrupados) ─
        $totalsAgg = (clone $base)
            ->selectRaw("
                SUM(monto) as total,
                SUM(CASE WHEN tipo='CAPITAL' THEN monto ELSE 0 END) as capital,
                SUM(CASE WHEN tipo='INTERES' THEN monto ELSE 0 END) as interes,
                SUM(CASE WHEN tipo='EXCEDENTE' THEN monto ELSE 0 END) as excedente,
                SUM(CASE WHEN tipo='MORA' THEN monto ELSE 0 END) as mora
            ")
            ->first();

        $totals = [
            'total' => (float) ($totalsAgg->total ?? 0),
            'capital' => (float) ($totalsAgg->capital ?? 0),
            'interes' => (float) ($totalsAgg->interes ?? 0),
            'excedente' => (float) ($totalsAgg->excedente ?? 0),
            'mora' => (float) ($totalsAgg->mora ?? 0),
            'fijos' => 0, // legacy: modo='Fijos' (no aplica en payments / CREDITO)
            'otros' => 0, // legacy: modo='Otros' (no aplica en payments / CREDITO)
        ];

        $isAdmin = auth()->user()?->can('transacciones.autorizar') ?? false;

        return view('livewire.reports.payments', [
            'clienteFiltrado' => $clienteFiltrado,
            'creditoFiltrado' => $creditoFiltrado,
            'rows' => $rows,
            'totals' => $totals,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Lo que muestra la ventana "Direcciones" de una fila: la dirección escrita
     * de la ficha y las ubicaciones GPS con coordenadas (Casa, Negocio y las
     * adicionales), cada una con su enlace a Google Maps. El legacy solo
     * enlazaba las coordenadas del pago (payments.latitud/longitud), que los
     * cobros nuevos no traen; si un pago migrado las tiene, se conservan.
     *
     * @return array{cliente: string, direccion: ?string, referencia: ?string, lugar: ?string, zona: ?string, ubicaciones: list<array{id: int, nombre: string, coordenadas: string, url: string, fecha: ?string}>, pago: ?string}
     */
    private function direccionesDe(Client $cliente, Payment $pago): array
    {
        // Las coordenadas se guardan decimal(10,7): para leerlas, sin los ceros de relleno.
        $limpia = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
        $ubicaciones = [];
        foreach ($cliente->ubicaciones as $u) {
            if (! $u->tieneCoordenadas()) {
                continue;
            }
            $ubicaciones[] = [
                'id' => (int) $u->id,
                'nombre' => $u->nombre ?: (ClientUbicacion::TIPOS[$u->tipo] ?? 'Otra'),
                'coordenadas' => $limpia($u->latitud).', '.$limpia($u->longitud),
                'url' => (string) $u->url(),
                'fecha' => $u->registradoEl()?->format('d/m/Y'),
            ];
        }
        $lugar = implode(' · ', array_filter(array_map(
            fn ($v) => trim((string) $v),
            [$cliente->distrito, $cliente->provincia, $cliente->departamento]
        )));

        return [
            'cliente' => $cliente->fullName(),
            'direccion' => trim((string) $cliente->direccion) ?: null,
            'referencia' => trim((string) $cliente->referencia) ?: null,
            'lugar' => $lugar ?: null,
            'zona' => trim((string) $cliente->zona) ?: null,
            'ubicaciones' => $ubicaciones,
            'pago' => ($pago->latitud && $pago->longitud)
                ? "https://maps.google.com/?q={$pago->latitud},{$pago->longitud}"
                : null,
        ];
    }

    /**
     * Fotos del voucher de cada cobro (crédito + fecha). Un pago vía depósito
     * deja un egreso "Banco" colgado del lote (expenses.mass_deletion_id) con
     * la foto adjunta (expense_attachments, disco public). Una sola consulta
     * para todas las filas del reporte.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<string, list<array{url: string, thumb: string, name: string}>> por "credit_id|Y-m-d"
     */
    private function vouchersPorCobro(Collection $payments): array
    {
        if ($payments->isEmpty()) {
            return [];
        }

        $filas = DB::table('mass_deletions as m')
            ->join('expenses as e', 'e.mass_deletion_id', '=', 'm.id')
            ->join('expense_attachments as a', 'a.expense_id', '=', 'e.id')
            ->whereIn('m.credit_id', $payments->pluck('credit_id')->unique()->values()->all())
            ->whereIn('m.date', $payments->map(fn ($p) => $p->fecha->format('Y-m-d'))->unique()->values()->all())
            ->orderBy('m.date')->orderBy('m.time')->orderBy('a.id')
            ->get(['m.credit_id', 'm.date', 'm.time', 'a.path', 'a.thumb_path', 'a.original_name']);

        $out = [];
        foreach ($filas as $f) {
            $fecha = Carbon::parse($f->date)->format('Y-m-d');
            $out[$f->credit_id.'|'.$fecha][] = [
                'url' => '/storage/'.ltrim((string) $f->path, '/'),
                'thumb' => '/storage/'.ltrim((string) ($f->thumb_path ?: $f->path), '/'),
                'name' => 'Voucher '.Carbon::parse($fecha)->format('d/m/Y').' '.substr((string) $f->time, 0, 5)
                    .($f->original_name ? ' · '.$f->original_name : ''),
            ];
        }

        return $out;
    }
}
