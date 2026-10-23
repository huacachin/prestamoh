<?php

namespace App\Livewire\Reports;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Payment;
use App\Support\MotivoCobro;
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

    /**
     * 23/10 (Antony): sin filtro por crédito o cliente el rango se limita a
     * 3 meses. Un "desde" vacío o a medias mientras se escribía abría la
     * tabla a los 500 mil pagos de la historia y colgaba el navegador.
     */
    public const MAX_DIAS = 92;

    /**
     * Botón Buscar (y Enter en el texto). 23/10 (Antony): los filtros ya no son
     * live: la fecha, el texto y el tipo se aplican aquí, no en cada tecla.
     * Normaliza el rango para que la URL y los campos muestren lo que se aplicó.
     */
    public function search(): void
    {
        // Solo dispara el render: el rango efectivo lo calcula rangoSeguro() en cada render,
        // sin pisar lo que el usuario escribió (la vista muestra el aviso si se recortó).
    }

    /**
     * Rango con el que se consulta: fechas válidas (Y-m-d), inicio ≤ fin y, sin
     * filtro por crédito/cliente, como máximo MAX_DIAS. Una fecha vacía o
     * inválida cae al otro extremo (o a hoy), nunca a "sin límite".
     *
     * @return array{0: string, 1: string, 2: bool} [desde, hasta, recortado]
     */
    private function rangoSeguro(): array
    {
        $valida = function ($v): ?string {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }

            return checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
        };
        $hoy = Carbon::today()->format('Y-m-d');
        $fef = $valida($this->fef) ?? $hoy;
        $fei = $valida($this->fei) ?? $fef;
        if ($fei > $fef) {
            [$fei, $fef] = [$fef, $fei];
        }

        $recortado = false;
        $conFiltro = ($this->creditoId !== '' && $this->creditoId !== null)
            || ($this->clienteId !== '' && $this->clienteId !== null);
        if (! $conFiltro && Carbon::parse($fei)->diffInDays(Carbon::parse($fef)) > self::MAX_DIAS) {
            $fei = Carbon::parse($fef)->subDays(self::MAX_DIAS)->format('Y-m-d');
            $recortado = true;
        }

        return [$fei, $fef, $recortado];
    }

    public function render()
    {
        // Rango efectivo (no se pisan fei/fef: la vista y el Excel usan estos valores).
        [$fei, $fef, $rangoRecortado] = $this->rangoSeguro();

        // ─── Listado agrupado por nroentrada+fecha (legacy) ─────────────
        $base = Payment::query()
            ->with(['credit:id,client_id,cuotas,fecha_cancelacion,situacion', 'credit.client:id,nombre,apellido_pat,apellido_mat'])
            ->where('modo', 'CREDITO')
            ->where('fecha', '>=', $fei)
            ->where('fecha', '<=', $fef);

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
        $recibos = $this->recibosPorCobro($payments);
        // 23/10 (Antony): el "Motivo" se arma con las cuotas del crédito (App\Support\MotivoCobro), no con el
        // detalle legacy. Solo se traen las cuotas que tocan los pagos del rango, en una pasada.
        $cuotas = MotivoCobro::cuotasTocadas($payments);
        $rows = [];
        $contador = 0;
        foreach ($payments->groupBy(fn ($p) => $p->credit_id.'|'.$p->fecha->format('Y-m-d')) as $clave => $group) {
            $first = $group->first();
            $cliente = $first->credit?->client;
            $motivo = MotivoCobro::de(
                $group->filter(fn ($p) => $p->detalle === null || ! str_ends_with((string) $p->detalle, 'Gat')),
                $first->credit,
                $cuotas[$first->credit_id][0] ?? [],
                $cuotas[$first->credit_id][1] ?? [],
            );
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
                // 'detalle' es lo que va al Excel (una línea); 'motivo' lo que pinta la tabla en dos.
                'detalle' => $motivo['texto'],
                'motivo' => $motivo,
                'monto' => $group->sum('monto'),
                'moneda' => $first->moneda,
                'latitud' => $first->latitud,
                'longitud' => $first->longitud,
                // 22/10 (Antony): fotos del voucher del cobro (visor flotante) y recibos del
                // sistema (ventanitas), por fila. Map usa latitud/longitud del pago (arriba).
                'vouchers' => $vouchers[$clave] ?? [],
                'recibos' => $recibos[$clave] ?? [],
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
            // Efectivos (las propiedades fei/fef guardan lo escrito; Livewire las pasa también a la vista)
            'desdeEfectivo' => $fei,
            'hastaEfectivo' => $fef,
            'rangoRecortado' => $rangoRecortado,
        ]);
    }

    /**
     * Recibos que genera el sistema por cada cobro (crédito + fecha): uno por
     * lote (mass_deletions), con el link público firmado del recibo, el mismo
     * que abre "Ver recibo" en el cronograma (App\Support\RecibosCuota) y que
     * trae el botón "Copiar imagen".
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<string, list<array{lote: int, hora: string, monto: float, url: string}>> por "credit_id|Y-m-d"
     */
    private function recibosPorCobro(Collection $payments): array
    {
        if ($payments->isEmpty()) {
            return [];
        }

        $lotes = DB::table('mass_deletions')
            ->whereIn('credit_id', $payments->pluck('credit_id')->unique()->values()->all())
            ->whereIn('date', $payments->map(fn ($p) => $p->fecha->format('Y-m-d'))->unique()->values()->all())
            ->orderBy('date')->orderBy('time')->orderBy('id')
            ->get(['id', 'credit_id', 'date', 'time', 'amount']);

        $out = [];
        foreach ($lotes as $l) {
            $out[$l->credit_id.'|'.Carbon::parse($l->date)->format('Y-m-d')][] = [
                'lote' => (int) $l->id,
                'hora' => substr((string) $l->time, 0, 5),
                'monto' => (float) $l->amount,
                'url' => \Illuminate\Support\Facades\URL::signedRoute('recibo.publico', ['massDeletionId' => (int) $l->id]),
            ];
        }

        return $out;
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
