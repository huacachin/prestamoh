<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\User;
use App\Support\Audit;
use App\Support\CuotaUniforme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class Refinance extends Component
{
    public ?Credit $credit = null;

    // Datos del nuevo crédito (mostly readonly, replica legacy)
    public $codpre_ = null;         // sin tipo estricto

    public $impopres = 0;           // Capital = saldo pendiente

    public $cuot = 0;               // Mismas cuotas que el original

    public $inte = 0;               // Mismo % interés

    public string $seletipl = '3';  // Mensual fijo

    public string $fechad = '';     // Fecha = última fecha de pago del original

    public string $fechar = '';     // Fecha registro = hoy

    public ?string $nomasesores = null; // ÚNICO campo editable

    public $moracc = 0;             // Auto

    public $moraii = 0;             // Auto

    public $intmont = 0;            // Monto interés (cap × tasa / 100)

    public bool $morai = true;

    public bool $morac = false;

    // Datos para mostrar
    public $importePagadoAlgo = 0;  // Si > 0, se permite refinanciar

    public function mount(int $creditId)
    {
        $this->credit = Credit::with(['client.asesor:id,name', 'installments' => fn ($q) => $q->orderBy('num_cuota')])
            ->findOrFail($creditId);

        $hoy = Carbon::today();
        $this->fechar = $hoy->format('Y-m-d');

        // Pagado total (suma de TODOS los aplicados)
        $totals = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->selectRaw('SUM(importe_aplicado + interes_aplicado) as pagado')
            ->first();
        $this->importePagadoAlgo = round((float) $totals->pagado, 2);

        // Capital nuevo = SALDO de la ÚLTIMA cuota (replica legacy: $rowsaldo se sobreescribe en cada iteración)
        $lastIns = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->orderBy('num_cuota')->orderBy('id')->get()->last();
        if ($lastIns) {
            $this->impopres = round(
                (float) $lastIns->importe_cuota + (float) $lastIns->importe_interes
                - (float) $lastIns->importe_aplicado - (float) $lastIns->interes_aplicado,
                2
            );
            // Legacy: Fecha Préstamo del refi = fechapago de la última cuota del original
            // (= cronograma/periodo). En Laravel ese dato vive en fecha_vencimiento,
            // NO en fecha_pago (que es NULL mientras la cuota no esté pagada).
            $this->fechad = $lastIns->fecha_vencimiento
                ? Carbon::parse($lastIns->fecha_vencimiento)->format('Y-m-d')
                : $hoy->format('Y-m-d');
            // Legacy: $rowinteress = importe_interes de la última cuota (no recalculado)
            $this->intmont = round((float) $lastIns->importe_interes, 2);
        } else {
            $this->impopres = 0;
            $this->fechad = $hoy->format('Y-m-d');
            $this->intmont = 0;
        }

        // Próximo correlativo
        $correl = (int) (DB::table('correlativos')->where('tipo', 'Credito')->value('correl') ?? 0);
        $this->codpre_ = $correl + 1;

        // Pre-llenar (del original)
        $this->inte = (float) $this->credit->interes;
        $this->cuot = (int) $this->credit->cuotas;
        $this->seletipl = '3'; // Mensual fijo (legacy hardcoded)

        $this->recalcMora();
    }

    private function recalcMora(): void
    {
        // Legacy bug homologado: tiene 2 inputs con id="impopres" (Capital arriba=importe_original
        // y Capital abajo=saldo). El JS jQuery $('#impopres') toma el PRIMER match (=importe_original).
        // Por eso aquí usamos credit->importe (no el saldo) para calcular mora.
        $cap = (float) ($this->credit?->importe ?? 0);
        $intePct = (float) $this->inte;
        $interes2 = $cap * $intePct / 100;
        // Legacy: intmont NO se recalcula (es el importe_interes original de la última cuota)
        // Legacy: mora2 (Mora Interés) = (int² × 2)/100/30, mora1 (Pago x día) = int/30
        $this->moracc = round($interes2 * ($intePct * 2) / 100 / 30, 2);
        $this->moraii = round($interes2 / 30, 2);
    }

    public function refinance()
    {
        if ($this->importePagadoAlgo <= 0) {
            $this->addError('refinance', 'No se puede refinanciar: el crédito no tiene pagos previos.');

            return;
        }

        if (! $this->nomasesores) {
            $this->addError('nomasesores', 'Indique el asesor.');

            return;
        }

        if (DB::table('credits')->where('id', $this->codpre_)->exists()) {
            $this->addError('codpre_', 'El código de préstamo ya está utilizado.');

            return;
        }

        $hqId = auth()->user()?->headquarter_id ?? 1; // G2: headquarter dinámico

        DB::transaction(function () use ($hqId) {
            $pid = (int) $this->codpre_;
            $codigopre = $this->credit->id;
            $fechaBase = Carbon::parse($this->fechad);
            $impopres = (float) $this->impopres;
            $inte = (float) $this->inte;
            $tocuota = (int) $this->cuot;
            $tipo = (int) $this->seletipl;

            DB::table('correlativos')->where('tipo', 'Credito')->update(['correl' => $pid, 'updated_at' => now()]);

            // INSERT nuevo crédito (refinanciamiento)
            DB::table('credits')->insert([
                'id' => $pid,
                'client_id' => $this->credit->client_id,
                'fecha_prestamo' => $this->fechad,
                // Legacy fechaactua = día en que entra a caja (para un refi = hoy).
                // Los reportes de caja/estadísticas filtran por fecha_actualizacion.
                'fecha_actualizacion' => $this->fechar,
                'importe' => $impopres,
                'cuotas' => $tocuota,
                'tipo_planilla' => $tipo,
                'interes' => $inte,
                'interes_total' => 0,
                'mora' => 0,
                'mora1' => $this->moraii, // Pago x día atrasado
                'mora2' => $this->moracc, // Mora Interés (legacy guarda ambas)
                'moneda' => 'Soles',
                'situacion' => 'Activo',
                'estado' => 1,
                'refinanciado' => 1,
                'cod_rem' => 'REF',
                'gat' => 0,
                'idcan' => $codigopre,
                'asesor' => Str::ucfirst($this->nomasesores),
                'user_id' => auth()->id(),
                'usuario' => Str::ucfirst(auth()->user()->username),
                'headquarter_id' => $hqId, // G2
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generar cuotas (siempre Mensual en refi)
            $selano = (int) $fechaBase->format('Y');
            $selmes = (int) $fechaBase->format('m');
            $selay = $fechaBase->format('d');

            $r = 0;
            $montototal = 0;
            $inttotal = 0;
            $fecha9 = null;
            $anoCuota = $selano;
            $mesCuotaNum = $selmes;

            $moncuoBase = round($impopres / $tocuota, 2);
            $moncuotBase = round($moncuoBase * $tocuota, 2);
            $moncuott = $impopres - $moncuotBase;
            $moncuott1 = abs($moncuott);
            $men = $moncuott < 1 ? '0' : '1';

            for ($i = 0; $i < $tocuota; $i++) {
                $r++;
                $moncuo = $moncuoBase;

                $mesCuotaNum++;
                if ($mesCuotaNum > 12) {
                    $mesCuotaNum = 1;
                    $anoCuota++;
                }
                $mesCuotaStr = str_pad($mesCuotaNum, 2, '0', STR_PAD_LEFT);

                $fechaCandidata = $anoCuota.'-'.$mesCuotaStr.'-'.$selay;
                if (! checkdate($mesCuotaNum, (int) $selay, $anoCuota)) {
                    $aux = Carbon::parse("$anoCuota-$mesCuotaStr-01")->addMonth();
                    $fechaCandidata = $aux->subDay()->format('Y-m-d');
                }
                $fecha9 = $fechaCandidata;

                if ($r === $tocuota) {
                    $moncuo = ($men === '1') ? $moncuo - $moncuott1 : $moncuo + $moncuott1;
                }

                $inter = round($impopres * ($inte / 100), 2); // Mensual

                $montototal += $moncuo;
                $inttotal += $inter;

                DB::table('credit_installments')->insert([
                    'credit_id' => $pid,
                    'num_cuota' => $r,
                    'fecha_vencimiento' => $fecha9,
                    'fecha_pago' => null, // cuota recién creada, aún no pagada
                    'importe_cuota' => $moncuo,
                    'importe_interes' => $inter,
                    'importe_aplicado' => 0,
                    'interes_aplicado' => 0,
                    'importe_mora' => 0,
                    'pagado' => false,
                    'usuario' => $this->nomasesores,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('credits')->where('id', $pid)->update([
                'fecha_vencimiento' => $fecha9,
                'interes_total' => $inttotal,
                'updated_at' => now(),
            ]);

            // Cuota uniforme redondeada a 0.10 + excedente (refi = mensual)
            $interesTotalExacto = round(round($impopres * $inte / 100, 2) * $tocuota, 2);
            CuotaUniforme::aplicar($pid, $impopres, $interesTotalExacto);

            // Marcar el ORIGINAL como Cancelado + REF
            DB::table('credits')->where('id', $codigopre)->update([
                'situacion' => 'Cancelado',
                'estado' => 0,
                'refinanciado' => 1,
                'cod_rem' => 'REF',
                'fecha_cancelacion' => now()->format('Y-m-d'),
                'updated_at' => now(),
            ]);
        });

        Audit::log("Refinanció el crédito #{$this->credit->id}", $this->credit);

        session()->flash('credit_success', "Refinanciamiento creado: nuevo crédito #{$this->codpre_}, original #{$this->credit->id} cancelado.");

        return redirect()->route('payments.index');
    }

    public function render()
    {
        $asesores = User::permission('creditos.ser-asesor-responsable')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return view('livewire.payments.refinance', compact('asesores'));
    }
}
