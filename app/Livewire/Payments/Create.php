<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    public ?Credit $credit = null;
    public string $fecpag;
    public float $monto = 0;
    public int $diasf = 0;       // descontar dias de mora
    public bool $ckmora = false; // reservar mora
    public bool $cancel = false; // marcado cancelado
    public ?string $latitud = null;
    public ?string $longitud = null;

    // Inputs adicionales del legacy
    public float $impointe2 = 0;  // Mora Interés manual
    public float $saldomora = 0;  // Mora Acumulada manual
    public float $impomora = 0;   // Mora Capital manual
    public ?string $obs = null;   // Observación libre
    public ?int $idpre = null;    // Cuota destino para mora manual

    public function mount(?int $creditId = null)
    {
        $this->fecpag = now()->format('Y-m-d');

        if ($creditId) {
            $this->credit = Credit::with(['client.asesor:id,name', 'installments' => fn ($q) => $q->orderBy('num_cuota')])
                ->find($creditId);

            if ($this->credit) {
                $this->autoCorrectCentavos();
                $this->credit->refresh();
                $this->credit->load(['installments' => fn ($q) => $q->orderBy('num_cuota')]);
            }
        }
    }

    private function autoCorrectCentavos(): void
    {
        $importeTotal = (float) $this->credit->importe;
        $interesTotal = round($importeTotal * (float) $this->credit->interes / 100, 2);

        if ((int) $this->credit->tipo_planilla === 3) {
            $interesTotal *= (int) $this->credit->cuotas;
        }

        $sums = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->selectRaw('SUM(importe_cuota) as cuo, SUM(importe_interes) as inte')->first();

        $diffCuota = round($importeTotal - (float) $sums->cuo, 2);
        $diffInte = round($interesTotal - (float) $sums->inte, 2);

        if ($diffCuota > 0 || $diffInte > 0) {
            $lastIns = DB::table('credit_installments')->where('credit_id', $this->credit->id)->orderByDesc('id')->first();
            if ($lastIns) {
                $update = [];
                if ($diffCuota > 0) $update['importe_cuota'] = $lastIns->importe_cuota + $diffCuota;
                if ($diffInte > 0)  $update['importe_interes'] = $lastIns->importe_interes + $diffInte;
                if (!empty($update)) DB::table('credit_installments')->where('id', $lastIns->id)->update($update);
            }
        }
    }

    private function buildCalcs(): array
    {
        if (!$this->credit) return $this->emptyCalcs();

        $importe = (float) $this->credit->importe;
        $interesPct = (float) $this->credit->interes;
        $interes = round($importe * $interesPct / 100, 2);
        $totalCredito = $importe + $interes;

        $totals = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->selectRaw('SUM(importe_cuota) as cuota, SUM(importe_interes) as interes,
                         SUM(importe_aplicado) as apli, SUM(interes_aplicado) as iapli, SUM(importe_mora) as mora')->first();

        $saldoPendiente = (float) $totals->cuota + (float) $totals->interes
            - (float) $totals->apli - (float) $totals->iapli;

        $minFecha = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->where('pagado', 0)->where('importe_cuota', '>', 0)->min('fecha_pago');
        $minFechaStr = $minFecha ? Carbon::parse($minFecha)->format('Y-m-d') : null;

        $diasddd = 0;
        if ($minFechaStr) {
            $diff = (int) floor(Carbon::parse($minFechaStr)->diffInDays(now(), false));
            if ($diff > 0) {
                if ((int) $this->credit->tipo_planilla === 3) {
                    $diasddd = $diff;
                } else {
                    $cur = Carbon::parse($minFechaStr);
                    for ($i = 1; $i <= $diff; $i++) {
                        $cur->addDay();
                        if (!in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) $diasddd++;
                    }
                }
            }
        }
        $diasFinal = max(0, (int) $diasddd - (int) $this->diasf);

        $tipoPlani = (int) $this->credit->tipo_planilla;
        $moraRate = (float) (($tipoPlani === 1) ? $this->credit->mora2 : $this->credit->mora1);
        $totMora = round($diasFinal * $moraRate, 2);

        $moraAcumulada = (float) DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->sum('importe');

        return [
            'importe'=>$importe,'interes_pct'=>$interesPct,'interes_total'=>$interes,'total_credito'=>$totalCredito,
            'saldo_pendiente'=>round($saldoPendiente,2),'fecha_venc'=>$minFechaStr,
            'dias_atraso'=>$diasddd,'dias_final'=>$diasFinal,
            'mora_rate'=>$moraRate,'total_mora'=>$totMora,'mora_acumulada'=>$moraAcumulada,
            'saldo_mora'=>round($saldoPendiente + $totMora, 2),
            'asesor_nombre'=>$this->credit->client?->asesor?->name,
        ];
    }

    private function emptyCalcs(): array
    {
        return ['importe'=>0,'interes_pct'=>0,'interes_total'=>0,'total_credito'=>0,
            'saldo_pendiente'=>0,'fecha_venc'=>null,'dias_atraso'=>0,'dias_final'=>0,
            'mora_rate'=>0,'total_mora'=>0,'mora_acumulada'=>0,'saldo_mora'=>0,'asesor_nombre'=>null];
    }

    public function pagar()
    {
        if (!$this->credit) {
            $this->dispatch('errorAlert', ['message' => 'No hay crédito seleccionado.']);
            return;
        }

        $this->validate([
            'monto'  => 'required|numeric|min:0',
            'fecpag' => 'required|date',
        ]);

        $user = auth()->user();
        if (!$user->hasRole('superusuario')) {
            $fechaSel = Carbon::parse($this->fecpag);
            if ($fechaSel->format('Ym') < now()->format('Ym')) {
                $this->dispatch('errorAlert', ['message' => 'No es posible registrar pago en mes anterior.']);
                return;
            }
        }

        $calcs = $this->buildCalcs();
        if ($this->monto > $calcs['saldo_pendiente'] + 0.01) {
            $this->dispatch('errorAlert', ['message' => 'El monto excede el saldo pendiente.']);
            return;
        }

        DB::transaction(function () use ($calcs) {
            $tipoPlani = (int) $this->credit->tipo_planilla;
            $obstipo = match ($tipoPlani) {1 => 'S.', 3 => 'M.', 4 => 'D.', default => ''};
            if ($this->cancel) $obstipo .= 'CANCEL.';

            $totCuotas = $this->credit->installments->count();
            $hora = now()->format('H:i:s');
            $usuario = auth()->user()?->name;
            $userId = auth()->id();
            $semodn = $this->credit->moneda ?? 'Soles';
            $totMora = (float) $calcs['total_mora'];
            $diasA = (int) $calcs['dias_atraso'];

            // ─── 1) DIAS MORA si hay descuento ─────────────────────────────
            if ($diasA > 0) {
                DB::table('dias_mora')->insert([
                    'credit_id'=>$this->credit->id,'dias'=>$diasA,'dias_descontados'=>$this->diasf,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }

            // ─── 2) CABECERA pago masivo ───────────────────────────────────
            $totalGeneral = round($this->monto + $totMora, 2);
            $massHeaderId = DB::table('mass_deletions')->insertGetId([
                'credit_id'=>$this->credit->id,'amount'=>$totalGeneral,'date'=>$this->fecpag,
                'time'=>$hora,'user'=>$usuario,'advisor'=>$this->credit->client?->asesor?->name,
                'performed_by'=>$usuario,'created_at'=>now(),'updated_at'=>now(),
            ]);

            $isMensualUnaCuota = ($tipoPlani === 3 && (int) $this->credit->cuotas === 1);

            // ─── 3) DISTRIBUCIÓN DEL MONTO ─────────────────────────────────
            if ($this->monto > 0) {
                $unpaid = CreditInstallment::where('credit_id', $this->credit->id)
                    ->where('pagado', 0)->orderBy('num_cuota')->get();
                $remaining = (float) $this->monto;

                foreach ($unpaid as $ins) {
                    if ($remaining < 0.01) break;

                    if ($isMensualUnaCuota) {
                        // BRANCH ESPECIAL: tipoplani=3 + cuotas=1 → INTERES PRIMERO
                        $apagarInt = (float) $ins->importe_interes - (float) $ins->interes_aplicado;
                        $apagarCap = (float) $ins->importe_cuota - (float) $ins->importe_aplicado;

                        $payInt = round(min($remaining, max(0, $apagarInt)), 2);
                        $remaining -= $payInt;
                        $payCap = round(min($remaining, max(0, $apagarCap)), 2);
                        $remaining -= $payCap;
                    } else {
                        // BRANCH NORMAL: capital primero, interés segundo
                        $apagarCap = (float) $ins->importe_cuota - (float) $ins->importe_aplicado;
                        $apagarInt = (float) $ins->importe_interes - (float) $ins->interes_aplicado;

                        $payCap = round(min($remaining, max(0, $apagarCap)), 2);
                        $remaining -= $payCap;
                        $payInt = round(min($remaining, max(0, $apagarInt)), 2);
                        $remaining -= $payInt;
                    }

                    if ($payCap > 0.001) {
                        $p = Payment::create([
                            'credit_id'=>$this->credit->id,'installment_id'=>$ins->id,
                            'modo'=>'CREDITO','tipo'=>'CAPITAL','documento'=>'CAPITAL',
                            'fecha'=>$this->fecpag,'hora'=>$hora,'monto'=>$payCap,'moneda'=>$semodn,
                            'detalle'=>"Pago : {$this->credit->id} Cuota:  {$ins->num_cuota}/{$totCuotas}",
                            'asesor'=>$this->credit->asesor,'usuario'=>$usuario,'user_id'=>$userId,
                            'headquarter_id'=>1,'latitud'=>$this->latitud,'longitud'=>$this->longitud,
                        ]);
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id'=>$massHeaderId,'installment_id'=>$ins->id,'payment_id'=>$p->id,
                            'amount'=>$payCap,'fecha'=>now(),'tipo'=>'C',
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                        $ins->importe_aplicado = (float) $ins->importe_aplicado + $payCap;
                    }

                    if ($payInt > 0.001) {
                        $p = Payment::create([
                            'credit_id'=>$this->credit->id,'installment_id'=>$ins->id,
                            'modo'=>'CREDITO','tipo'=>'INTERES','documento'=>'INTERES',
                            'fecha'=>$this->fecpag,'hora'=>$hora,'monto'=>$payInt,'moneda'=>$semodn,
                            'detalle'=>"Pago : {$this->credit->id} Interes:  {$ins->num_cuota}/{$totCuotas}",
                            'asesor'=>$this->credit->asesor,'usuario'=>$usuario,'user_id'=>$userId,
                            'headquarter_id'=>1,'latitud'=>$this->latitud,'longitud'=>$this->longitud,
                        ]);
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id'=>$massHeaderId,'installment_id'=>$ins->id,'payment_id'=>$p->id,
                            'amount'=>$payInt,'fecha'=>now(),'tipo'=>'I',
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                        $ins->interes_aplicado = (float) $ins->interes_aplicado + $payInt;
                    }

                    $totApli = (float) $ins->importe_aplicado + (float) $ins->interes_aplicado;
                    $totEsperado = (float) $ins->importe_cuota + (float) $ins->importe_interes;
                    if ($totApli >= $totEsperado - 0.001) {
                        $ins->pagado = 1;
                        $ins->fecha_pago = $this->fecpag;
                        $ins->observacion = $obstipo;
                    }
                    $ins->usuario = $usuario;
                    $ins->save();
                }
            }

            // ─── 4) MORA INTERÉS manual (impointe2) ────────────────────────
            if ($this->impointe2 > 0.001) {
                $p = $this->createMoraPayment('MORA INTERES', $this->impointe2, "Mora Interes", $hora, $usuario, $userId, $semodn);
                $insTarget = $this->idpre ? CreditInstallment::find($this->idpre) : null;
                if ($insTarget && $insTarget->credit_id === $this->credit->id) {
                    DB::table('credit_installments')->where('id', $insTarget->id)
                        ->increment('importe_mora', $this->impointe2);
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id'=>$massHeaderId,'installment_id'=>$insTarget->id,'payment_id'=>$p->id,
                        'amount'=>$this->impointe2,'fecha'=>now(),'tipo'=>'M',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }

            // ─── 5) MORA ACUMULADA manual (saldomora) ──────────────────────
            if ($this->saldomora > 0.001) {
                $maxIns = DB::table('credit_installments')->where('credit_id', $this->credit->id)
                    ->orderByDesc('id')->first();
                $p = $this->createMoraPayment('MORA', $this->saldomora, "Mora Acumulada", $hora, $usuario, $userId, $semodn);
                if ($maxIns) {
                    DB::table('credit_installments')->where('id', $maxIns->id)
                        ->increment('importe_mora', $this->saldomora);
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id'=>$massHeaderId,'installment_id'=>$maxIns->id,'payment_id'=>$p->id,
                        'amount'=>$this->saldomora,'fecha'=>now(),'tipo'=>'M',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }

            // ─── 6) MORA AUTO-CALCULADA (totmoraapa) ───────────────────────
            //   Si NO se reserva: pago de mora; se asocia a la última cuota con pago hoy
            if ($totMora > 0.001 && !$this->ckmora) {
                $todayStr = $this->fecpag;
                $lastIns = DB::table('credit_installments')->where('credit_id', $this->credit->id)
                    ->where('importe_aplicado', '>', 0)->whereDate('updated_at', $todayStr)
                    ->orderByDesc('id')->first();

                $p = $this->createMoraPayment('MORA', $totMora, "Mora Acumulada", $hora, $usuario, $userId, $semodn);
                if ($lastIns) {
                    DB::table('credit_installments')->where('id', $lastIns->id)
                        ->increment('importe_mora', $totMora);
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id'=>$massHeaderId,'installment_id'=>$lastIns->id,'payment_id'=>$p->id,
                        'amount'=>$totMora,'fecha'=>now(),'tipo'=>'M',
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }

            // ─── 7) MORA CAPITAL manual (impomora) ─────────────────────────
            if ($this->impomora > 0.001) {
                $this->createMoraPayment('MORA CAPITAL', $this->impomora, "Mora Capital", $hora, $usuario, $userId, $semodn);
                if ($this->idpre) {
                    DB::table('credit_installments')->where('id', $this->idpre)
                        ->increment('importe_mora', $this->impomora);
                }
            }

            // ─── 8) RESERVA MORA (ckmora) UPSERT ───────────────────────────
            if ($this->ckmora && $totMora > 0.001) {
                $existing = DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->first();
                if ($existing) {
                    DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->update([
                        'dias' => DB::raw('dias + ' . (int) $diasA),
                        'importe' => DB::raw('importe + ' . (float) $totMora),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('mora_acumulada')->insert([
                        'credit_id'=>$this->credit->id,'importe'=>$totMora,'dias'=>$diasA,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }

            // ─── 9) OBSERVACIÓN libre ──────────────────────────────────────
            if ($this->obs && $this->idpre) {
                DB::table('credit_installments')->where('id', $this->idpre)
                    ->update(['observacion' => $this->obs]);
            }

            // ─── 10) Marcar Cancelado si corresponde ───────────────────────
            $newSaldo = DB::table('credit_installments')->where('credit_id', $this->credit->id)
                ->selectRaw('SUM(importe_cuota + importe_interes - importe_aplicado - interes_aplicado) as s')
                ->value('s');

            if ($this->cancel || (float) $newSaldo < 0.01) {
                $this->credit->situacion = 'Cancelado';
                $this->credit->fecha_cancelacion = $this->fecpag;
                $this->credit->estado = 0;
                $this->credit->save();
            }
        });

        session()->flash('payment_success', 'Pago registrado correctamente.');
        return redirect()->route('credits.show', $this->credit->id);
    }

    private function createMoraPayment(string $documento, float $monto, string $detalleSuffix, string $hora, ?string $usuario, ?int $userId, string $semodn): Payment
    {
        return Payment::create([
            'credit_id'=>$this->credit->id,'installment_id'=>null,
            'modo'=>'CREDITO','tipo'=>'MORA','documento'=>$documento,
            'fecha'=>$this->fecpag,'hora'=>$hora,'monto'=>round($monto, 2),'moneda'=>$semodn,
            'detalle'=>"Pago : {$this->credit->id} {$detalleSuffix}",
            'asesor'=>$this->credit->asesor,'usuario'=>$usuario,'user_id'=>$userId,
            'headquarter_id'=>1,'latitud'=>$this->latitud,'longitud'=>$this->longitud,
        ]);
    }

    public function render()
    {
        return view('livewire.payments.create', [
            'calcs' => $this->buildCalcs(),
        ]);
    }
}
