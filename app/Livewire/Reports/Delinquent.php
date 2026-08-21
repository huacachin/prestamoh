<?php

namespace App\Livewire\Reports;

use App\Models\CreditInstallment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Delinquent extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** true = sin paginar (lo usa el export a Excel para traer todo). */
    public bool $todos = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['selemes0', 'selecano0', 'seletipl0', 'exp', 'codigo', 'cdni', 'cnombre', 'casesor', 'fechai', 'fechaf'], true)) {
            $this->resetPage();
        }
    }

    #[Url(as: 'mes', except: '')]
    public $selemes0 = '';

    #[Url(as: 'anio', except: '')]
    public $selecano0 = '';

    #[Url(as: 'tipo', except: '')]
    public $seletipl0 = '';

    #[Url(as: 'expediente', except: '')]
    public $exp = '';

    #[Url(as: 'codigo', except: '')]
    public $codigo = '';

    #[Url(as: 'dni', except: '')]
    public $cdni = '';

    #[Url(as: 'nombre', except: '')]
    public $cnombre = '';

    #[Url(as: 'asesor', except: '')]
    public $casesor = '';

    #[Url(as: 'desde', except: '')]
    public $fechai = '';

    #[Url(as: 'hasta', except: '')]
    public $fechaf = '';

    public function search() {}

    public function render()
    {
        // ─── QUERY: cuotas pendientes (flpago=0) de créditos no cancelados ─
        $query = CreditInstallment::query()
            ->join('credits as c', 'credit_installments.credit_id', '=', 'c.id')
            ->join('clients as cl', 'c.client_id', '=', 'cl.id')
            ->leftJoin('users as u', 'cl.asesor_id', '=', 'u.id')
            ->where('c.situacion', '<>', 'Cancelado')
            ->where('credit_installments.pagado', false);

        // Columnas del listado: se aplican DESPUÉS de clonar la base para los
        // agregados (si vivieran en la base, el selectRaw agregado se les
        // sumaría y only_full_group_by lo rechaza).
        $columnas = [
            'credit_installments.*',
            'c.id as credit_id',
            'c.importe as credit_importe',
            'c.interes as credit_interes',
            'c.cuotas as credit_cuotas',
            'c.tipo_planilla',
            'c.fecha_actualizacion',
            'c.cod_rem',
            'c.situacion',
            'cl.id as client_id',
            'cl.expediente',
            'cl.documento as cli_dni',
            'cl.nombre as cli_nombre',
            'cl.apellido_pat as cli_pat',
            'cl.apellido_mat as cli_mat',
            'cl.celular1 as cli_cel',
            'u.username as asesor_user',
            'u.name as asesor_name',
        ];

        if ($this->selemes0 !== '' && $this->selecano0 !== '') {
            // Rango sargable (whereYear/whereMonth anulaban el índice de fecha)
            $ini = sprintf('%04d-%02d-01', (int) $this->selecano0, (int) $this->selemes0);
            $fin = Carbon::parse($ini)->endOfMonth()->format('Y-m-d');
            $query->whereBetween('c.fecha_actualizacion', [$ini, $fin]);
        }
        if ($this->exp !== '') {
            $query->where('cl.expediente', $this->exp);
        }
        if ($this->codigo !== '') {
            $query->where('c.id', $this->codigo);
        }
        if ($this->cdni !== '') {
            $query->where('cl.documento', $this->cdni);
        }
        if ($this->cnombre !== '') {
            $term = $this->cnombre;
            $query->where(function ($q) use ($term) {
                $q->where('cl.nombre', 'like', "%{$term}%")
                    ->orWhere('cl.apellido_pat', 'like', "%{$term}%")
                    ->orWhere('cl.apellido_mat', 'like', "%{$term}%");
            });
        }
        if ($this->casesor !== '') {
            $term = $this->casesor;
            $query->where(function ($q) use ($term) {
                $q->where('u.username', 'like', "%{$term}%")
                    ->orWhere('u.name', 'like', "%{$term}%");
            });
        }
        if ($this->fechai !== '' && $this->fechaf !== '') {
            $query->where('credit_installments.fecha_vencimiento', '>=', $this->fechai)
                ->where('credit_installments.fecha_vencimiento', '<=', $this->fechaf);
        }

        // ─── AGREGADOS POR SQL (pantalla): antes se hidrataban las ~4,000
        // cuotas como modelos y se sumaba todo en PHP (~1.5s de CPU en prod);
        // ahora los totales/conteos salen de 2 queries agregadas y solo se
        // procesa en PHP la página visible. El Excel ($todos) conserva la
        // ruta completa porque necesita todas las filas.
        $porPagina = 100;
        $pagina = $this->getPage();
        $offset = 0;
        $aggTotals = null;

        if (! $this->todos) {
            $agg = (clone $query)->toBase()->selectRaw('
                COUNT(*) n,
                COALESCE(SUM(credit_installments.importe_cuota), 0) cuota,
                COALESCE(SUM(credit_installments.importe_interes), 0) interes,
                COALESCE(SUM(credit_installments.importe_cuota + credit_installments.importe_interes), 0) total,
                COALESCE(SUM(credit_installments.importe_aplicado + credit_installments.interes_aplicado), 0) pago,
                COALESCE(SUM(credit_installments.importe_cuota + credit_installments.importe_interes
                    - credit_installments.importe_aplicado - credit_installments.interes_aplicado), 0) saldo,
                COALESCE(SUM(CASE WHEN credit_installments.fecha_vencimiento > CURDATE() THEN 1 ELSE 0 END), 0) vignt
            ')->first();
            $tipos = (clone $query)->toBase()
                ->selectRaw('c.tipo_planilla tp, COUNT(DISTINCT c.id) n')
                ->groupBy('c.tipo_planilla')
                ->pluck('n', 'tp');
            $aggTotals = ['agg' => $agg, 'tipos' => $tipos];

            $offset = ($pagina - 1) * $porPagina;
            // Tiebreaker por id: sin él, LIMIT/OFFSET puede repetir u omitir
            // filas entre páginas cuando hay empates de fecha (y coincide con
            // el orden natural del índice InnoDB, que sufija la PK).
            $items = $query->select($columnas)
                ->orderBy('credit_installments.fecha_vencimiento', 'asc')
                ->orderBy('credit_installments.id', 'asc')
                ->skip($offset)->take($porPagina)->get();
        } else {
            // Export: mismo orden de siempre (solo fecha) para no alterar el
            // orden histórico de las filas del Excel dentro de cada día.
            $items = $query->select($columnas)
                ->orderBy('credit_installments.fecha_vencimiento', 'asc')->get();
        }

        // ─── PROCESAR (solo la página en pantalla; todo en el Excel) ───
        $today = Carbon::today();
        $rows = [];

        $totals = [
            'cuota' => 0,
            'interes' => 0,
            'total' => 0,
            'pago' => 0,
            'saldo' => 0,
        ];

        // Por tipo planilla (cuenta créditos únicos)
        $sempo = $mempo = $dempo = 0;
        $vignt = $venc = 0;
        $creditTipoSeen = [];

        $rrrr = $offset; // correlativo global aunque la tabla esté paginada
        foreach ($items as $i) {
            $rrrr++;
            $importeCuota = (float) $i->importe_cuota;
            $importeInteres = (float) $i->importe_interes;
            $pagado = (float) $i->importe_aplicado + (float) $i->interes_aplicado;
            $totalCuota = $importeCuota + $importeInteres;
            $saldo = $totalCuota - $pagado;
            $interesPct = (float) $i->credit_interes;
            $tipo = (int) $i->tipo_planilla;

            // Conteo por tipo (1 vez por crédito)
            if (! isset($creditTipoSeen[$i->credit_id])) {
                $creditTipoSeen[$i->credit_id] = true;
                if ($tipo === 1) {
                    $sempo++;
                } elseif ($tipo === 3) {
                    $mempo++;
                } elseif ($tipo === 4) {
                    $dempo++;
                }
            }

            $tcLabel = match ($tipo) {
                1 => 'S', 3 => 'M', 4 => 'D', default => '',
            };

            // Estado: Vencida si fecha_vencimiento <= hoy
            $fecVenc = $i->fecha_vencimiento ? Carbon::parse($i->fecha_vencimiento) : null;
            $estd = ($fecVenc && $fecVenc->gt($today)) ? 'Vigente' : 'Vencida';
            if ($estd === 'Vigente') {
                $vignt++;
            } else {
                $venc++;
            }

            // Tiempo
            $tiempo = '';
            if ($fecVenc) {
                $diff = $today->diff($fecVenc);
                $parts = [];
                if ($diff->y > 0) {
                    $parts[] = $diff->y.' año'.($diff->y > 1 ? 's' : '');
                }
                if ($diff->m > 0) {
                    $parts[] = $diff->m.' mes'.($diff->m > 1 ? 'es' : '');
                }
                if ($diff->d > 0) {
                    $parts[] = $diff->d.' día'.($diff->d > 1 ? 's' : '');
                }
                $tiempo = implode(', ', $parts);
            }

            // WhatsApp message (legacy)
            $waMsg = '';
            if ($i->cli_cel && $fecVenc) {
                $cliName = trim($i->cli_pat.' '.$i->cli_mat.' '.$i->cli_nombre);
                $waMsg = rawurlencode("Sr.(a) *{$cliName}*,\n*Huacachin* le recuerda que la cuota de su préstamo vence el ".$fecVenc->format('d/m/Y'));
            }

            $rows[] = [
                'n' => $rrrr,
                'exp' => $i->expediente,
                'client_id' => $i->client_id,
                'codigo' => $i->credit_id,
                'dni' => $i->cli_dni,
                'cliente' => trim(($i->cli_pat ?? '').' '.($i->cli_mat ?? '').' '.($i->cli_nombre ?? '')),
                'cod_rem' => $i->cod_rem ?? '',
                'cuota' => $importeCuota,
                'tc_label' => $tcLabel,
                'tipo_planilla' => $tipo,
                'interes_pct' => $interesPct,
                'interes_monto' => $importeInteres,
                'cuotas' => $i->credit_cuotas,
                'pago' => $pagado,
                'saldo' => $saldo,
                'total' => $totalCuota,
                'fecha_cred' => $i->fecha_actualizacion ? Carbon::parse($i->fecha_actualizacion)->format('Y-m-d') : null,
                'fecha_venc' => $fecVenc?->format('Y-m-d'),
                'celular' => $i->cli_cel,
                'estado' => $estd,
                'tiempo' => $tiempo,
                'asesor' => $i->asesor_user ?? $i->asesor_name ?? '',
                'wa_phone' => $i->cli_cel,
                'wa_msg' => $waMsg,
            ];

            $totals['cuota'] += $importeCuota;
            $totals['interes'] += $importeInteres;
            $totals['total'] += $totalCuota;
            $totals['pago'] += $pagado;
            $totals['saldo'] += $saldo;
        }

        $tc = (float) (DB::table('exchange_rates')->orderByDesc('fecha')->value('compra') ?? 1);
        if ($tc <= 0) {
            $tc = 1;
        }

        // Pantalla: los totales/conteos del pie salen de las queries agregadas
        // (el loop de arriba solo procesó la página visible).
        if (! $this->todos) {
            $agg = $aggTotals['agg'];
            $tipos = $aggTotals['tipos'];

            $totals = [
                'cuota' => (float) $agg->cuota,
                'interes' => (float) $agg->interes,
                'total' => (float) $agg->total,
                'pago' => (float) $agg->pago,
                'saldo' => (float) $agg->saldo,
            ];
            $sempo = (int) ($tipos[1] ?? 0);
            $mempo = (int) ($tipos[3] ?? 0);
            $dempo = (int) ($tipos[4] ?? 0);
            $vignt = (int) $agg->vignt;
            $venc = (int) $agg->n - $vignt;

            $rows = new LengthAwarePaginator(
                $rows, (int) $agg->n, $porPagina, $pagina,
                ['path' => request()->url(), 'pageName' => 'page']
            );
        }

        return view('livewire.reports.delinquent', [
            'rows' => $rows,
            'totals' => $totals,
            'tc' => $tc,
            'tipoTotals' => [
                'sempo' => $sempo, 'mempo' => $mempo, 'dempo' => $dempo,
            ],
            'vignt' => $vignt,
            'venc' => $venc,
        ]);
    }
}
