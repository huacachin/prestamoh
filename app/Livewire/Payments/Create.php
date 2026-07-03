<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use App\Support\Audit;
use App\Support\MoraExonerada;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    public ?Credit $credit = null;

    public string $fecpag = '';

    public $monto = null;         // editable, sin tipo estricto (admite "")

    public $diasf = null;         // editable, sin tipo estricto

    public bool $ckmora = false;

    public bool $cancel = false;

    public ?string $latitud = null;

    public ?string $longitud = null;

    // Inputs adicionales del legacy
    public $impointe2 = null;     // editable

    public $impomora = null;      // editable

    public ?string $obs = null;

    public $idpre = null;         // editable, ?int da problemas con select vacío

    // Total Mora editable (override) — solo roles con permiso 'pagos.mora-manual'.
    // null = usar la mora auto-calculada; valor numérico = reemplaza la mora a cobrar.
    public $moraManual = null;

    // Fecha "Calcular al" de las tarjetas de escenario (simulador de cotización).
    public string $fecsim = '';

    public function mount(?int $creditId = null)
    {
        $this->fecpag = now()->format('Y-m-d');
        $this->fecsim = now()->format('Y-m-d');

        if ($creditId) {
            $this->credit = Credit::with(['client.asesor:id,name', 'installments' => fn ($q) => $q->orderBy('num_cuota')])
                ->find($creditId);

            if ($this->credit) {
                $this->autoCorrectCentavos();
                $this->ajusteInteresUltimaCuotaDiario(); // C13
                $this->credit->refresh();
                $this->credit->load(['installments' => fn ($q) => $q->orderBy('num_cuota')]);
            }
        }
    }

    /**
     * C13 — Réplica del ajuste legacy en pagossmasivo.php (L743–754).
     * Tipo Diario (4) con fecha_prestamo > 2021-10-06: la última cuota recibe el
     * residual del interés total dividido entre 22 cuotas-base × 1.
     */
    private function ajusteInteresUltimaCuotaDiario(): void
    {
        if ((int) $this->credit->tipo_planilla !== 4) {
            return;
        }
        if (! $this->credit->fecha_prestamo) {
            return;
        }
        if (Carbon::parse($this->credit->fecha_prestamo)->lte(Carbon::parse('2021-10-06'))) {
            return;
        }

        $importe = (float) $this->credit->importe;
        $interesPct = (float) $this->credit->interes;
        $interesT = round(($importe * $interesPct) / 100, 2);
        $interesC = round($interesT / 22, 2);
        $interesCu = round($interesC * 21, 2);
        $interesUC = round($interesT - $interesCu, 2);

        $maxIns = DB::table('credit_installments')
            ->where('credit_id', $this->credit->id)
            ->where('importe_cuota', '>', 0)
            ->orderByDesc('id')->first();

        if ($maxIns && abs((float) $maxIns->importe_interes - $interesUC) > 0.01) {
            DB::table('credit_installments')
                ->where('id', $maxIns->id)
                ->update(['importe_interes' => $interesUC]);
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
                if ($diffCuota > 0) {
                    $update['importe_cuota'] = $lastIns->importe_cuota + $diffCuota;
                }
                if ($diffInte > 0) {
                    $update['importe_interes'] = $lastIns->importe_interes + $diffInte;
                }
                if (! empty($update)) {
                    DB::table('credit_installments')->where('id', $lastIns->id)->update($update);
                }
            }
        }
    }

    /** Solo estos roles (vía permiso) pueden editar/override el Total Mora. */
    public function canEditMora(): bool
    {
        return auth()->user()?->can('pagos.mora-manual') ?? false;
    }

    /**
     * Al escribir el Monto a Pagar (>0) se desbloquea el Total Mora para los
     * roles autorizados, precargado con la mora calculada para que puedan
     * ajustarlo. Si el monto vuelve a 0/vacío, se rebloquea y vuelve al cálculo.
     */
    public function updatedMonto(): void
    {
        if (! $this->canEditMora()) {
            return;
        }

        if ((float) $this->monto > 0) {
            if ($this->moraManual === null || $this->moraManual === '') {
                $this->moraManual = $this->buildCalcs()['total_mora_calc'];
            }
        } else {
            $this->moraManual = null;
        }
    }

    /**
     * Homologado al legacy diasatraso(): al cambiar "Descontar Días" la mora se
     * recalcula (días final × tasa) y reescribe el Total Mora, para que Total
     * Mora y Saldo P.+Mora reflejen el nuevo cálculo en vivo. Sin esto, el
     * override auto-precargado en updatedMonto() congelaba la mora y los totales
     * dejaban de recalcular para los roles con permiso 'pagos.mora-manual'.
     */
    public function updatedDiasf(): void
    {
        if ($this->canEditMora() && (float) $this->monto > 0) {
            $this->moraManual = $this->buildCalcs()['total_mora_calc'];
        } else {
            $this->moraManual = null;
        }
    }

    private function buildCalcs(): array
    {
        if (! $this->credit) {
            return $this->emptyCalcs();
        }

        $importe = (float) $this->credit->importe;
        $interesPct = (float) $this->credit->interes;
        $interes = round($importe * $interesPct / 100, 2);
        $totalCredito = $importe + $interes;

        $totals = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->selectRaw('SUM(importe_cuota) as cuota, SUM(importe_interes) as interes,
                         SUM(importe_aplicado) as apli, SUM(interes_aplicado) as iapli, SUM(importe_mora) as mora')->first();

        $saldoPendiente = (float) $totals->cuota + (float) $totals->interes
            - (float) $totals->apli - (float) $totals->iapli;

        $moraInfo = $this->moraCalcAt(now());
        $minFechaStr = $moraInfo['fecha_venc'];
        $diasddd = $moraInfo['dias_atraso'];
        $diasFinal = $moraInfo['dias_final'];
        $moraRate = $moraInfo['mora_rate'];
        $totMoraCalc = $moraInfo['mora_calc'];

        // Override de Total Mora: solo si el usuario tiene permiso y escribió un
        // valor numérico válido. El gate de permiso vive aquí (servidor), así que
        // un usuario sin permiso no puede inyectar mora manipulando el front.
        $usaManual = $this->canEditMora()
            && $this->moraManual !== null && $this->moraManual !== ''
            && is_numeric($this->moraManual);
        $totMora = $usaManual ? round((float) $this->moraManual, 2) : $totMoraCalc;

        $moraAcumulada = (float) DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->sum('importe');

        // Homologado al legacy calimp(): al escribir el Monto a Pagar, el Saldo
        // Pendiente mostrado refleja lo que quedaría DESPUÉS del pago
        // (saldo - monto), y el Saldo P.+Mora usa ese restante. saldo_pendiente
        // (completo) se conserva aparte para validación y la lógica de Cancelado.
        $montoNum = (float) $this->monto;
        $saldoRestante = round($saldoPendiente - $montoNum, 2);

        return [
            'importe' => $importe, 'interes_pct' => $interesPct, 'interes_total' => $interes, 'total_credito' => $totalCredito,
            'saldo_pendiente' => round($saldoPendiente, 2), 'fecha_venc' => $minFechaStr,
            'saldo_restante' => $saldoRestante,
            'dias_atraso' => $diasddd, 'dias_final' => $diasFinal,
            'mora_rate' => $moraRate, 'total_mora' => $totMora, 'total_mora_calc' => $totMoraCalc,
            'mora_manual' => $usaManual, 'mora_acumulada' => $moraAcumulada,
            'saldo_mora' => round($saldoPendiente + $totMora, 2),
            'saldo_mora_restante' => round($saldoRestante + $totMora, 2),
            'asesor_nombre' => $this->credit->client?->asesor?->name,
        ];
    }

    /**
     * Mora calculada a una fecha dada, con las mismas reglas del legacy:
     * días desde el vencimiento impago más antiguo hasta $al (mensual: días
     * calendario; semanal/diario: excluye sábados y domingos), menos
     * "Descontar Días" (diasf), × tasa de mora del crédito (mora2 semanal /
     * mora1 resto). dias_atraso puede ser negativo si aún no vence (solo
     * display; la mora queda en 0 por el max).
     */
    private function moraCalcAt(Carbon $al): array
    {
        $minFecha = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->where('pagado', 0)->where('importe_cuota', '>', 0)->min('fecha_vencimiento');
        $minFechaStr = $minFecha ? Carbon::parse($minFecha)->format('Y-m-d') : null;

        $diasddd = 0;
        if ($minFechaStr) {
            $diff = (int) floor(Carbon::parse($minFechaStr)->diffInDays($al, false));
            if ($diff > 0) {
                if ((int) $this->credit->tipo_planilla === 3) {
                    $diasddd = $diff;
                } else {
                    $cur = Carbon::parse($minFechaStr);
                    for ($i = 1; $i <= $diff; $i++) {
                        $cur->addDay();
                        if (! in_array($cur->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                            $diasddd++;
                        }
                    }
                }
            } elseif ($diff < 0) {
                $diasddd = $diff;
            }
        }
        $diasFinal = max(0, (int) $diasddd - (int) $this->diasf);

        $moraRate = (float) (((int) $this->credit->tipo_planilla === 1) ? $this->credit->mora2 : $this->credit->mora1);

        return [
            'fecha_venc' => $minFechaStr,
            'dias_atraso' => $diasddd,
            'dias_final' => $diasFinal,
            'mora_rate' => $moraRate,
            'mora_calc' => round($diasFinal * $moraRate, 2),
        ];
    }

    private function emptyCalcs(): array
    {
        return ['importe' => 0, 'interes_pct' => 0, 'interes_total' => 0, 'total_credito' => 0,
            'saldo_pendiente' => 0, 'fecha_venc' => null, 'saldo_restante' => 0, 'dias_atraso' => 0, 'dias_final' => 0,
            'mora_rate' => 0, 'total_mora' => 0, 'total_mora_calc' => 0, 'mora_manual' => false,
            'mora_acumulada' => 0, 'saldo_mora' => 0, 'saldo_mora_restante' => 0, 'asesor_nombre' => null];
    }

    public function pagar()
    {
        if (! $this->credit) {
            $this->dispatch('errorAlert', ['message' => 'No hay crédito seleccionado.']);

            return;
        }

        $this->validate([
            'monto' => 'required|numeric|min:0',
            'fecpag' => 'required|date',
            'moraManual' => 'nullable|numeric|min:0',
        ]);

        $user = auth()->user();
        if (! $user->can('caja.bypass-fecha-anterior')) {
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
            $obstipo = match ($tipoPlani) {
                1 => 'S.', 3 => 'M.', 4 => 'D.', default => ''
            };
            if ($this->cancel) {
                $obstipo .= 'CANCEL.';
            }

            $totCuotas = $this->credit->installments->count();
            $hora = now()->format('H:i:s');
            $usuario = auth()->user()?->name;
            $userId = auth()->id();
            $hqId = auth()->user()?->headquarter_id ?? 1; // C10
            $semodn = $this->credit->moneda ?? 'Soles';
            $totMora = (float) $calcs['total_mora'];
            $diasA = (int) $calcs['dias_atraso'];

            // ─── 1) DIAS MORA si hay descuento ─────────────────────────────
            if ($diasA > 0) {
                DB::table('dias_mora')->insert([
                    'credit_id' => $this->credit->id, 'dias' => $diasA, 'dias_descontados' => (int) $this->diasf,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // ─── 2) CABECERA pago masivo ───────────────────────────────────
            // amount = SOLO lo realmente cobrado al cliente.
            // Si Reserva Mora está marcada, $totMora se acumula pero NO se cobra → no suma.
            // Las moras manuales (impointe2/impomora) sí se cobran siempre.
            $moraQueSeCobra = $this->ckmora ? 0 : $totMora;
            $totalGeneral = round(
                $this->monto
                + $moraQueSeCobra
                + (float) $this->impointe2
                + (float) $this->impomora,
                2
            );
            $massHeaderId = DB::table('mass_deletions')->insertGetId([
                'credit_id' => $this->credit->id, 'amount' => $totalGeneral, 'date' => $this->fecpag,
                'time' => $hora, 'user' => $usuario, 'advisor' => $this->credit->client?->asesor?->name,
                'performed_by' => $usuario, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $isMensualUnaCuota = ($tipoPlani === 3 && (int) $this->credit->cuotas === 1);

            // ─── 3) DISTRIBUCIÓN DEL MONTO ─────────────────────────────────
            // Track de cuotas tocadas EN ESTE pago (para asociar mora a la primera de ESTE lote).
            $touchedThisPayment = [];

            if ($this->monto > 0) {
                $unpaid = CreditInstallment::where('credit_id', $this->credit->id)
                    ->where('pagado', 0)->orderBy('num_cuota')->get();
                $remaining = (float) $this->monto;

                foreach ($unpaid as $ins) {
                    if ($remaining < 0.01) {
                        break;
                    }

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
                            'credit_id' => $this->credit->id, 'installment_id' => $ins->id,
                            'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
                            'fecha' => $this->fecpag, 'hora' => $hora, 'monto' => $payCap, 'moneda' => $semodn,
                            'detalle' => "Pago : {$this->credit->id} Cuota:  {$ins->num_cuota}/{$totCuotas}",
                            'asesor' => $this->credit->asesor, 'usuario' => $usuario, 'user_id' => $userId,
                            'headquarter_id' => $hqId, 'latitud' => $this->latitud, 'longitud' => $this->longitud,
                        ]);
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $massHeaderId, 'installment_id' => $ins->id, 'payment_id' => $p->id,
                            'amount' => $payCap, 'fecha' => now(), 'tipo' => 'C',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $ins->importe_aplicado = (float) $ins->importe_aplicado + $payCap;
                    }

                    if ($payInt > 0.001) {
                        $diasInt = $ins->fecha_vencimiento
                            ? abs((int) Carbon::parse($ins->fecha_vencimiento)->diffInDays(now(), false))
                            : 0;
                        $p = Payment::create([
                            'credit_id' => $this->credit->id, 'installment_id' => $ins->id,
                            'modo' => 'CREDITO', 'tipo' => 'INTERES', 'documento' => 'INTERES',
                            'fecha' => $this->fecpag, 'hora' => $hora, 'monto' => $payInt, 'moneda' => $semodn,
                            'detalle' => "Pago : {$this->credit->id} Interes:  {$ins->num_cuota}/{$totCuotas} Dias : {$diasInt}",
                            'asesor' => $this->credit->asesor, 'usuario' => $usuario, 'user_id' => $userId,
                            'headquarter_id' => $hqId, 'latitud' => $this->latitud, 'longitud' => $this->longitud,
                        ]);
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $massHeaderId, 'installment_id' => $ins->id, 'payment_id' => $p->id,
                            'amount' => $payInt, 'fecha' => now(), 'tipo' => 'I',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $ins->interes_aplicado = (float) $ins->interes_aplicado + $payInt;
                    }

                    if ($payCap > 0.001 || $payInt > 0.001) {
                        $touchedThisPayment[] = $ins->id;
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
            // C6: el legacy actualiza columna `impomorai` (mora INTERÉS), separada de `impomora` (mora capital).
            // En Laravel: actualiza `mora_interes` (no `importe_mora` que es mora capital).
            if ($this->impointe2 > 0.001) {
                $p = $this->createMoraPayment(
                    'MORA INTERES',
                    $this->impointe2,
                    "Mora Interes Dias : {$diasA}",
                    $hora, $usuario, $userId, $hqId, $semodn
                );
                $insTarget = $this->idpre ? CreditInstallment::find($this->idpre) : null;
                if ($insTarget && $insTarget->credit_id === $this->credit->id) {
                    DB::table('credit_installments')->where('id', $insTarget->id)
                        ->increment('mora_interes', $this->impointe2); // C6
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id' => $massHeaderId, 'installment_id' => $insTarget->id, 'payment_id' => $p->id,
                        'amount' => $this->impointe2, 'fecha' => now(), 'tipo' => 'M',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // ─── 5) MORA AUTO-CALCULADA (totmoraapa) ───────────────────────
            // Se asocia a la PRIMERA cuota tocada hoy (origen del atraso) o, si no hay
            // ninguna, a la primera cuota vencida pendiente. Más intuitivo que el legacy.
            if ($totMora > 0.001 && ! $this->ckmora) {
                $insTarget = $this->primeraCuotaParaMora($touchedThisPayment);

                $moraDetalle = ($calcs['mora_manual'] ?? false) ? 'Mora manual' : "Mora Acumulada Dias : {$diasA}";
                $p = $this->createMoraPayment('MORA', $totMora, $moraDetalle, $hora, $usuario, $userId, $hqId, $semodn);
                if ($insTarget) {
                    DB::table('credit_installments')->where('id', $insTarget->id)
                        ->increment('importe_mora', $totMora);
                    DB::table('mass_deletion_details')->insert([
                        'mass_deletion_id' => $massHeaderId, 'installment_id' => $insTarget->id, 'payment_id' => $p->id,
                        'amount' => $totMora, 'fecha' => now(), 'tipo' => 'M',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // ─── 7) MORA CAPITAL manual (impomora) ─────────────────────────
            if ($this->impomora > 0.001) {
                $this->createMoraPayment('MORA CAPITAL', $this->impomora, "Mora Capital Dias : {$diasA}", $hora, $usuario, $userId, $hqId, $semodn);
                if ($this->idpre) {
                    DB::table('credit_installments')->where('id', $this->idpre)
                        ->increment('importe_mora', $this->impomora);
                }
            }

            // ─── 8) RESERVA MORA (ckmora) UPSERT ───────────────────────────
            // Reserva + cancelar → la mora se PERDONA (no se acumula): no tiene
            // sentido dejar mora como deuda futura sobre un crédito que se cierra.
            // Reserva + sin cancelar → acumula en mora_acumulada para cobrarla
            // más adelante (deuda viva).
            if ($this->ckmora && ! $this->cancel && $totMora > 0.001) {
                $existing = DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->first();
                if ($existing) {
                    DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->update([
                        'dias' => DB::raw('dias + '.(int) $diasA),
                        'importe' => DB::raw('importe + '.(float) $totMora),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('mora_acumulada')->insert([
                        'credit_id' => $this->credit->id, 'importe' => $totMora, 'dias' => $diasA,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // ─── 9) OBSERVACIÓN libre ──────────────────────────────────────
            if ($this->obs && $this->idpre) {
                DB::table('credit_installments')->where('id', $this->idpre)
                    ->update(['observacion' => $this->obs]);
            }

            // ─── 10) Marcar Cancelado SOLO si el usuario lo marcó ──────────
            // C8: el legacy NO auto-cancela cuando saldo=0; solo cancela si cancel=on.
            if ($this->cancel) {
                $this->credit->situacion = 'Cancelado';
                $this->credit->fecha_cancelacion = $this->fecpag;
                $this->credit->estado = 0;
                $this->credit->save();
            }
        });

        Audit::log(
            "Registró pago de {$this->monto} en el crédito #{$this->credit->id}".($this->cancel ? ' (canceló el crédito)' : ''),
            $this->credit,
            ['monto' => $this->monto, 'fecha' => $this->fecpag]
        );

        session()->flash('payment_success', 'Pago registrado correctamente.');

        return redirect()->route('credits.show', $this->credit->id);
    }

    /**
     * Cuota destino para la mora cobrada en ESTE pago.
     *  1. Primera cuota tocada en este pago (origen del atraso de este lote).
     *  2. Fallback: primera cuota vencida pendiente (caso "solo mora, sin pago de capital").
     *  3. Fallback final: primera cuota pendiente cualquiera.
     */
    private function primeraCuotaParaMora(array $touchedThisPayment = [])
    {
        $cid = $this->credit->id;
        $hoy = now()->toDateString();

        if (! empty($touchedThisPayment)) {
            $ins = DB::table('credit_installments')
                ->where('credit_id', $cid)
                ->whereIn('id', $touchedThisPayment)
                ->orderBy('num_cuota')->first();
            if ($ins) {
                return $ins;
            }
        }

        $ins = DB::table('credit_installments')
            ->where('credit_id', $cid)
            ->where('pagado', 0)
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->orderBy('num_cuota')->first();
        if ($ins) {
            return $ins;
        }

        return DB::table('credit_installments')
            ->where('credit_id', $cid)
            ->where('pagado', 0)
            ->orderBy('num_cuota')->first();
    }

    private function createMoraPayment(string $documento, float $monto, string $detalleSuffix, string $hora, ?string $usuario, ?int $userId, ?int $hqId, string $semodn): Payment
    {
        return Payment::create([
            'credit_id' => $this->credit->id, 'installment_id' => null,
            'modo' => 'CREDITO', 'tipo' => 'MORA', 'documento' => $documento,
            'fecha' => $this->fecpag, 'hora' => $hora, 'monto' => round($monto, 2), 'moneda' => $semodn,
            'detalle' => "Pago : {$this->credit->id} {$detalleSuffix}",
            'asesor' => $this->credit->asesor, 'usuario' => $usuario, 'user_id' => $userId,
            'headquarter_id' => $hqId ?? 1, 'latitud' => $this->latitud, 'longitud' => $this->longitud,
        ]);
    }

    /**
     * Deuda del cronograma a una fecha dada ($al, por defecto hoy): capital e
     * interés pendientes de las cuotas ya vencidas más el prorrateo por los
     * días corridos del período en curso (cuota del período ÷ días del
     * período: semanal 7 / mensual 30 / diario 1). También la misma deuda
     * "redondeada" a la próxima cuota (períodos en curso completos en lugar
     * del prorrateo), la mora a esa fecha y el desglose para las tarjetas.
     */
    private function deudaCalcs(?Carbon $al = null): array
    {
        if (! $this->credit) {
            return ['fecha' => null, 'cuotas_vencidas' => 0, 'cap_vencidas' => 0.0, 'int_vencidas' => 0.0,
                'dias_adic' => 0, 'periodos' => 0, 'cap_dia' => 0.0, 'int_dia' => 0.0, 'periodo_dias' => 0,
                'cap_hoy' => 0.0, 'int_hoy' => 0.0, 'cap_prox' => 0.0, 'int_prox' => 0.0,
                'mora' => 0.0, 'mora_dias' => 0, 'mora_rate' => 0.0,
                'total_hoy' => 0.0, 'total_prox' => 0.0,
                'cap_pendiente_total' => 0.0, 'saldo_credito' => 0.0, 'total_cancelar' => 0.0];
        }

        $al = $al ?? now();

        $installments = $this->credit->installments;
        $vencidas = $installments->filter(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->lte($al));

        // Pendiente de cuotas ya vencidas
        $capVenc = $vencidas->sum(fn ($i) => max(0, (float) $i->importe_cuota - (float) $i->importe_aplicado));
        $intVenc = $vencidas->sum(fn ($i) => max(0, (float) $i->importe_interes - (float) $i->interes_aplicado));
        $nVencidas = $vencidas->filter(fn ($i) => ! $i->pagado)->count();

        $capHoy = $capVenc;
        $intHoy = $intVenc;
        $capProx = $capVenc;
        $intProx = $intVenc;

        $diasAdic = 0;
        $periodos = 0;
        $capDia = 0.0;
        $intDia = 0.0;

        $periodoDias = match ((int) $this->credit->tipo_planilla) {
            1 => 7, 4 => 1, default => 30
        };
        $ultimaVencida = $vencidas->max('fecha_vencimiento');
        if ($ultimaVencida && $periodoDias > 0) {
            $diasAdic = (int) floor($ultimaVencida->copy()->startOfDay()->diffInDays($al->copy()->startOfDay(), false));
            if ($diasAdic > 0) {
                // Con próxima cuota en el cronograma corren capital e interés;
                // con el cronograma agotado (típico mensual 1 cuota atrasado)
                // solo sigue corriendo el interés — el capital no crece.
                $proxima = $installments->first(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->gt($al));
                $cuotaPeriodo = $proxima ?? $installments->last();
                $capDia = $proxima ? (float) ($cuotaPeriodo->importe_cuota ?? 0) / $periodoDias : 0.0;
                $intDia = (float) ($cuotaPeriodo->importe_interes ?? 0) / $periodoDias;

                $capHoy += round($diasAdic * $capDia, 2);
                $intHoy += round($diasAdic * $intDia, 2);

                // Redondeo a la próxima fecha de pago: períodos en curso completos
                $periodos = (int) ceil($diasAdic / $periodoDias);
                if ($proxima) {
                    $capProx += $periodos * (float) ($cuotaPeriodo->importe_cuota ?? 0);
                }
                $intProx += $periodos * (float) ($cuotaPeriodo->importe_interes ?? 0);
            } else {
                $diasAdic = 0;
            }
        }

        // Mora a la fecha simulada. Si es hoy, respeta el override manual
        // (mismo gate de permiso que buildCalcs).
        $moraInfo = $this->moraCalcAt($al);
        $usaManual = $al->isSameDay(now()) && $this->canEditMora()
            && $this->moraManual !== null && $this->moraManual !== ''
            && is_numeric($this->moraManual);
        $mora = $usaManual ? round((float) $this->moraManual, 2) : $moraInfo['mora_calc'];

        $capPendTotal = round($installments->sum('importe_cuota') - $installments->sum('importe_aplicado'), 2);
        $saldoCredito = round(
            $installments->sum('importe_cuota') + $installments->sum('importe_interes')
            - $installments->sum('importe_aplicado') - $installments->sum('interes_aplicado'), 2
        );

        // El capital adeudado nunca excede el capital pendiente del cronograma
        $capHoy = min($capHoy, $capPendTotal);
        $capProx = min($capProx, $capPendTotal);

        return [
            'fecha' => $al->format('Y-m-d'),
            'cuotas_vencidas' => $nVencidas,
            'cap_vencidas' => round($capVenc, 2),
            'int_vencidas' => round($intVenc, 2),
            'dias_adic' => $diasAdic,
            'periodos' => $periodos,
            'cap_dia' => round($capDia, 2),
            'int_dia' => round($intDia, 2),
            'periodo_dias' => $periodoDias,
            'cap_hoy' => round($capHoy, 2),
            'int_hoy' => round($intHoy, 2),
            'cap_prox' => round($capProx, 2),
            'int_prox' => round($intProx, 2),
            'mora' => $mora,
            'mora_dias' => $moraInfo['dias_final'],
            'mora_rate' => $moraInfo['mora_rate'],
            'total_hoy' => round($capHoy + $intHoy + $mora, 2),
            'total_prox' => round($capProx + $intProx + $mora, 2),
            'cap_pendiente_total' => $capPendTotal,
            'saldo_credito' => $saldoCredito,
            'total_cancelar' => round($saldoCredito + $mora, 2),
        ];
    }

    /**
     * Botón "Usar monto" de las tarjetas de escenario: llena el Monto a Pagar
     * (solo capital + interés; la mora se cobra aparte, como siempre) y
     * dispara el mismo hook que al tipearlo (precarga de mora editable).
     */
    public function usarMonto(float $monto): void
    {
        $saldo = (float) $this->buildCalcs()['saldo_pendiente'];
        $this->monto = number_format(min(round($monto, 2), $saldo), 2, '.', '');
        $this->updatedMonto();
    }

    public function render()
    {
        // Límite inferior del simulador: la fecha del último pago registrado.
        // Desde ahí los aplicados de las cuotas no han cambiado, así que el
        // cálculo hacia atrás es exacto; antes de esa fecha mezclaría el
        // estado actual con una fecha en la que la deuda era otra.
        $fecsimMin = null;
        if ($this->credit) {
            $fecsimMin = Payment::where('credit_id', $this->credit->id)->max('fecha')
                ?: $this->credit->fecha_prestamo;
        }
        $fecsimMin = $fecsimMin ? Carbon::parse($fecsimMin)->format('Y-m-d') : now()->format('Y-m-d');

        $alSim = null;
        if ($this->fecsim) {
            try {
                $alSim = Carbon::parse($this->fecsim);
                // Clamp servidor: el atributo min del input es solo front
                if ($alSim->lt(Carbon::parse($fecsimMin))) {
                    $alSim = Carbon::parse($fecsimMin);
                }
            } catch (\Exception) {
                $alSim = null;
            }
        }

        return view('livewire.payments.create', [
            'calcs' => $this->buildCalcs(),
            'deuda' => $this->deudaCalcs(),        // a hoy: filas del tfoot
            'sim' => $this->deudaCalcs($alSim),    // a la fecha simulada: tarjetas
            'fecsimMin' => $fecsimMin,
            'moraExon' => $this->credit ? MoraExonerada::porCuota($this->credit) : [],
        ]);
    }
}
