<?php

namespace App\Livewire\Payments;

use App\Livewire\Cash\Concerns\SavesExpenseAttachments;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Expense;
use App\Models\MassDeletion;
use App\Models\Payment;
use App\Models\ShortLink;
use App\Services\Payments\MotorPagos;
use App\Services\Printing\TicketPrinter;
use App\Support\Audit;
use App\Support\MoraExonerada;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use SavesExpenseAttachments;
    use WithFileUploads;

    public ?Credit $credit = null;

    public string $fecpag = '';

    public $monto = null;         // editable, sin tipo estricto (admite "")

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

    // Motivo del ajuste. Obligatorio cuando el override CAMBIA el monto: sin él
    // el cobro no se confirma. Queda en `mora_overrides` + auditoría, para que
    // toda rebaja de mora tenga responsable y explicación.
    public ?string $moraMotivo = null;

    // Fecha "Calcular al" de las tarjetas de escenario (simulador de cotización).
    public string $fecsim = '';

    // Opciones de la tarjeta "Cancelar crédito" (solo afectan la cotización)
    /**
     * Respuesta del cajero cuando el cobro liquida el crédito: '' | 'si' | 'no'.
     *
     * El switch "Cancelado" se marca a mano y nada avisaba si se cobraba la
     * totalidad con el switch apagado: el crédito quedaba Vigente pese a estar
     * pagado. Con esto, un cobro que cubre el total no se puede confirmar sin
     * decidir explícitamente si el crédito se cierra o sigue.
     */
    public string $decisionTotal = '';

    public bool $cancelSinMora = false;     // quitar mora y mora acumulada del total

    public bool $cancelUltimaCuota = false; // interés de todo el cronograma, no solo a la fecha

    // Vista previa del recibo que se muestra en el modal de confirmación
    // ANTES de registrar el cobro. Vacío = no hay nada que confirmar.
    public array $preview = [];

    // Último cobro registrado (mass_deletions.id), para reimprimir su ticket.
    public ?int $lastTicketId = null;

    // Aviso discreto post-cobro: ['numero' => '119695', 'total' => 283.40].
    public array $ultimoCobro = [];

    // ── Método de pago (efectivo | deposito) ───────────────────────────
    // Si es depósito, al cobrar se genera AUTOMÁTICAMENTE el egreso de caja
    // "Dep. {Banco} {Cuenta} {Cliente} ({Canal})" con los MISMOS campos que
    // los egresos manuales (caja 1, Otros, reason Banco, GUIA, Voucher) para
    // que todos los reportes lo levanten sin cambios, vinculado al cobro
    // via expenses.mass_deletion_id (la reversa lo elimina).
    public string $metodoPago = 'efectivo';

    public string $depBanco = 'Bcp';

    public string $depCuenta = 'Guilmer';

    public string $depCuentaOtra = '';

    public string $depCanal = 'Yape';

    public string $depFecha = '';

    /** Foto del voucher (opcional); queda adjunta al egreso generado. */
    public $voucherFoto = null;

    public const DEP_BANCOS = ['Bcp', 'Interbank', 'Bbva', 'Banco de la Nación'];

    public const DEP_CUENTAS = ['Guilmer', 'Corhuac', 'Flores', 'Centeno', 'Rosales', 'Malpartida', 'Ramos', 'Otra'];

    public const DEP_CANALES = ['Yape', 'Trans.', 'Ag.', 'Plin'];

    /** Id del egreso generado en el último cobro (para adjuntar el voucher). */
    public ?int $egresoCreadoId = null;

    /**
     * Descripción estandarizada del egreso por depósito — el mismo formato
     * que el personal digitaba a mano: "Dep. Bcp Guilmer Machaca Cabrera
     * Helen Janeth (Yape)" (+ dd/mm si el depósito fue otro día).
     */
    private function descripcionEgresoDeposito(): string
    {
        $cuenta = $this->depCuenta === 'Otra' ? trim($this->depCuentaOtra) : $this->depCuenta;
        $cli = $this->credit?->client;
        $nombre = $cli
            ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? ''))
            : '';

        $txt = trim("Dep. {$this->depBanco} {$cuenta} {$nombre} ({$this->depCanal})");
        if ($this->depFecha !== '' && $this->depFecha !== $this->fecpag) {
            $txt .= ' '.Carbon::parse($this->depFecha)->format('d/m');
        }

        return $txt;
    }

    /** "Licet Tafur Collantes" → "Licet T." (formato del campo Respons.). */
    private function nombreCortoUsuario(): string
    {
        $partes = preg_split('/\s+/', trim((string) auth()->user()?->name));
        $corto = $partes[0] ?? '';
        if (isset($partes[1]) && $partes[1] !== '') {
            $corto .= ' '.mb_strtoupper(mb_substr($partes[1], 0, 1)).'.';
        }

        return $corto;
    }

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
    /**
     * Crédito de cuota uniforme (nuevo esquema): capital/interés repartidos al
     * céntimo y redondeo a 0.10 guardado en importe_excedente. Los correctores
     * legacy de centavos NO deben tocar estos cronogramas.
     */
    private function esCuotaUniforme(): bool
    {
        return DB::table('credit_installments')
            ->where('credit_id', $this->credit->id)
            ->where('importe_excedente', '>', 0)
            ->exists();
    }

    private function ajusteInteresUltimaCuotaDiario(): void
    {
        if ((int) $this->credit->tipo_planilla !== 4) {
            return;
        }
        if ($this->esCuotaUniforme()) {
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
        if ($this->esCuotaUniforme()) {
            return; // el cronograma uniforme ya cuadra al céntimo
        }

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
            $this->moraMotivo = null;
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
            ->selectRaw('SUM(importe_cuota) as cuota, SUM(importe_interes) as interes, SUM(importe_excedente) as exc,
                         SUM(importe_aplicado) as apli, SUM(interes_aplicado) as iapli, SUM(excedente_aplicado) as eapli,
                         SUM(importe_mora) as mora')->first();

        $saldoPendiente = (float) $totals->cuota + (float) $totals->interes + (float) $totals->exc
            - (float) $totals->apli - (float) $totals->iapli - (float) $totals->eapli;

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
            // Ajuste REAL: el override cambió el monto. El campo viene precargado
            // con la mora calculada, así que "manual" sin cambio no es un ajuste
            // y no exige motivo.
            'mora_ajustada' => $usaManual && abs($totMora - $totMoraCalc) > 0.005,
            'mora_ajuste_diff' => round($totMora - $totMoraCalc, 2),
            'saldo_mora' => round($saldoPendiente + $totMora, 2),
            'saldo_mora_restante' => round($saldoRestante + $totMora, 2),
            'asesor_nombre' => $this->credit->client?->asesor?->name,
        ];
    }

    /**
     * Mora calculada a una fecha dada: días desde el vencimiento impago más
     * antiguo hasta $al (mensual: días calendario; semanal/diario: excluye
     * sábados y domingos) × mora diaria. Mora diaria = 5% de la cuota vencida
     * ÷7 (semanal) / ÷30 (mensual); los diarios mantienen su mora1 histórico.
     * dias_atraso puede ser negativo si aún no vence (solo display; la mora
     * queda en 0 por el max). El descuento de días se eliminó (2026-07-03);
     * la única rebaja posible es el override gerencial de Total Mora.
     */
    private function moraCalcAt(Carbon $al): array
    {
        $minIns = DB::table('credit_installments')->where('credit_id', $this->credit->id)
            ->where('pagado', 0)->where('importe_cuota', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->first(['fecha_vencimiento', 'importe_cuota', 'importe_interes', 'importe_excedente']);
        $minFechaStr = $minIns?->fecha_vencimiento ? Carbon::parse($minIns->fecha_vencimiento)->format('Y-m-d') : null;

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
        $diasFinal = max(0, (int) $diasddd);

        // La cuota real del cliente incluye el excedente de redondeo (cuota uniforme)
        $cuotaVencida = $minIns
            ? (float) $minIns->importe_cuota + (float) $minIns->importe_interes + (float) $minIns->importe_excedente
            : 0.0;
        $moraRate = $this->credit->moraDiaria($cuotaVencida);

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

    /**
     * Reglas de negocio del cobro. Devuelve el mensaje de error o null si el
     * pago puede registrarse. Se corre dos veces: al pedir la vista previa y
     * otra vez al confirmar, para que el modal no sea la única defensa.
     */
    private function validarCobro(): ?string
    {
        if (! $this->credit) {
            return 'No hay crédito seleccionado.';
        }

        // Solo créditos ACTIVOS admiten pagos (espejo del legacy, que aborta
        // con estado=0). El front oculta el formulario; esto respalda al UI
        // ante llamadas Livewire directas o estados desfasados en pantalla.
        if ($this->credit->situacion !== 'Activo') {
            return "El crédito está {$this->credit->situacion}: no admite pagos.";
        }

        $this->validate([
            'monto' => 'required|numeric|min:0',
            'fecpag' => 'required|date',
            'moraManual' => 'nullable|numeric|min:0',
            'moraMotivo' => 'nullable|string|max:255',
        ]);

        // Ajustar la mora a mano exige explicar por qué: es dinero que se deja
        // de cobrar y hasta ahora no quedaba rastro de quién ni por qué.
        if ($this->buildCalcs()['mora_ajustada'] && mb_strlen(trim((string) $this->moraMotivo)) < 5) {
            return 'Indica el motivo del ajuste de la mora (mínimo 5 caracteres).';
        }

        // Método de pago: si es depósito, valida sus campos y el voucher.
        if ($this->metodoPago === 'deposito') {
            $this->validate([
                'depBanco' => 'required|string|in:'.implode(',', self::DEP_BANCOS),
                'depCuenta' => 'required|string|in:'.implode(',', self::DEP_CUENTAS),
                'depCanal' => 'required|string|in:'.implode(',', self::DEP_CANALES),
                'depFecha' => 'nullable|date',
                'voucherFoto' => 'nullable|image|max:10240',
            ], [
                'voucherFoto.image' => 'El voucher debe ser una imagen.',
                'voucherFoto.max' => 'La imagen del voucher debe pesar máximo 10 MB.',
            ]);
            if ($this->depCuenta === 'Otra' && trim($this->depCuentaOtra) === '') {
                return 'Indica el nombre de la cuenta receptora del depósito.';
            }
        }

        if (! auth()->user()->can('caja.bypass-fecha-anterior')) {
            if (Carbon::parse($this->fecpag)->format('Ym') < now()->format('Ym')) {
                return 'No es posible registrar pago en mes anterior.';
            }
        }

        if ($this->monto > $this->buildCalcs()['saldo_pendiente'] + 0.01) {
            return 'El monto excede el saldo pendiente.';
        }

        // Gate servidor del switch Cancelado (espejo del disabled del front):
        // el monto debe cubrir al menos capital pendiente + interés a la fecha.
        if ($this->cancel) {
            $cancelarHoy = $this->deudaCalcs()['cancelar_cap_int'];
            if ($cancelarHoy - (float) $this->monto > 0.01) {
                return 'Para cancelar debes cubrir capital pendiente + interés a la fecha ('.number_format($cancelarHoy, 2).').';
            }
        }

        return null;
    }

    /**
     * Botón "Pagar": no cobra todavía. Valida, arma la vista previa del
     * recibo y abre el modal donde el cajero confirma con "Cobrar" o
     * "Cobrar e imprimir".
     */
    public function confirmarPago(): void
    {
        if ($error = $this->validarCobro()) {
            $this->dispatch('errorAlert', ['message' => $error]);

            return;
        }

        // Si el cajero ya marcó el switch, la decisión está tomada y no se le
        // vuelve a preguntar; si no, el modal exigirá que responda.
        $this->decisionTotal = $this->cancel ? 'si' : '';

        $this->preview = $this->construirPreview();
        $this->dispatch('ticket-confirm');
    }

    /**
     * ¿Este cobro liquida el crédito? Mismo umbral que habilita el switch:
     * capital pendiente + interés a la fecha (la mora va aparte).
     *
     * No depende de $cancel a propósito: la pregunta debe seguir visible en el
     * modal después de responderla.
     */
    public function cubreTotalidad(): bool
    {
        if (! $this->credit || ! is_numeric($this->monto)) {
            return false;
        }

        $cancelarHoy = $this->deudaCalcs()['cancelar_cap_int'];

        return $cancelarHoy > 0.01 && ((float) $this->monto) - $cancelarHoy >= -0.01;
    }

    /** Al responder cambia el total (la mora acumulada entra o no), así que se rehace la vista previa. */
    public function updatedDecisionTotal(): void
    {
        if ($this->decisionTotal === 'si') {
            $this->cancel = true;
        } elseif ($this->decisionTotal === 'no') {
            $this->cancel = false;
        }

        if ($this->preview) {
            $this->preview = $this->construirPreview();
        }
    }

    public function pagar(bool $imprimir = false)
    {
        if ($error = $this->validarCobro()) {
            $this->dispatch('errorAlert', ['message' => $error]);

            return;
        }

        // Cobro que liquida el crédito: no se registra sin una decisión expresa.
        // Es la causa de que quedaran créditos pagados pero Vigentes.
        if ($this->cubreTotalidad() && $this->decisionTotal === '') {
            $this->dispatch('errorAlert', ['message' => 'Este pago cubre el total: indica si se cancela el crédito o queda vigente.']);

            return;
        }

        $calcs = $this->buildCalcs();

        $massHeaderId = null;
        $totalCobrado = 0.0;

        DB::transaction(function () use ($calcs, &$massHeaderId, &$totalCobrado) {
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

            // Moras al cancelar: "Quitar mora y mora acumulada" perdona ambas.
            // Si no se quitan, la mora acumulada (Mora Exon. de cuotas pagadas
            // tarde) se cobra como pago MORA propio para que caja y reportes
            // la perciban como mora. Se calcula ANTES de distribuir el pago.
            $quitarMoras = $this->cancel && $this->cancelSinMora;
            $moraAcumACobrar = ($this->cancel && ! $quitarMoras)
                ? round(collect(MoraExonerada::porCuota($this->credit))->sum('monto'), 2)
                : 0.0;

            // ─── 1) DIAS MORA si hay descuento ─────────────────────────────
            if ($diasA > 0) {
                DB::table('dias_mora')->insert([
                    'credit_id' => $this->credit->id, 'dias' => $diasA, 'dias_descontados' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // ─── 2) CABECERA pago masivo ───────────────────────────────────
            // amount = SOLO lo realmente cobrado al cliente.
            // Si Reserva Mora está marcada, $totMora se acumula pero NO se cobra → no suma.
            // Las moras manuales (impointe2/impomora) sí se cobran siempre.
            $moraQueSeCobra = ($this->ckmora || $quitarMoras) ? 0 : $totMora;
            $totalGeneral = round(
                $this->monto
                + $moraQueSeCobra
                + $moraAcumACobrar
                + (float) $this->impointe2
                + (float) $this->impomora,
                2
            );
            $totalCobrado = $totalGeneral;
            $massHeaderId = DB::table('mass_deletions')->insertGetId([
                'credit_id' => $this->credit->id, 'amount' => $totalGeneral, 'date' => $this->fecpag,
                'time' => $hora, 'user' => $usuario, 'advisor' => $this->credit->client?->asesor?->name,
                'performed_by' => $usuario, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $isMensualUnaCuota = ($tipoPlani === 3 && (int) $this->credit->cuotas === 1);

            // ─── 2b) CANCELACIÓN ANTICIPADA: condonar interés no corrido ───
            // Si el monto cubre la cancelación a la fecha pero no el cronograma
            // completo, la diferencia es interés futuro no devengado: se rebaja
            // de las últimas cuotas impagas para que el pago cierre el crédito
            // con saldo 0.
            if ($this->cancel) {
                // El excedente es redondeo de comodidad de cobro, no deuda:
                // al cancelar solo se cobra el de cuotas ya vencidas; el de
                // cuotas futuras se condona ANTES de calcular el descuento.
                $this->condonarExcedenteFuturo();
                $this->condonarInteresFuturo();
            }

            // ─── 3) DISTRIBUCIÓN DEL MONTO ─────────────────────────────────
            // Track de cuotas tocadas EN ESTE pago (para asociar mora a la primera de ESTE lote).
            $touchedThisPayment = [];

            if ($this->monto > 0) {
                $unpaid = CreditInstallment::where('credit_id', $this->credit->id)
                    ->where('pagado', 0)->orderBy('num_cuota')->get();

                // El reparto lo decide el MotorPagos (LegacyEngine validado por
                // backtest para créditos espejo del legacy; DistribucionPago
                // para cuota uniforme). Devuelve las dos verdades del legacy:
                //  - 'caja': filas para `payments` (lo que leen los reportes)
                //  - 'rows': aplicación normalizada al cronograma + details
                $dist = $this->simularDistribucion((float) $this->monto, $unpaid, $isMensualUnaCuota);

                $porNum = $unpaid->keyBy(fn ($i) => (int) $i->num_cuota);

                // ── 3a) Filas de CAJA (payments) ───────────────────────────
                $pagoPorClave = [];   // "num|tipo" → payment_id (para enlazar details)
                $ultimoPagoId = null;
                foreach ($dist['caja'] as $r) {
                    $ins = $porNum->get($r['num']);
                    if (! $ins) {
                        continue;
                    }
                    $tipo = match ($r['tipo']) {
                        'C' => 'CAPITAL', 'I' => 'INTERES', 'E' => 'EXCEDENTE'
                    };
                    $diasInt = $ins->fecha_vencimiento
                        ? abs((int) Carbon::parse($ins->fecha_vencimiento)->diffInDays(now(), false))
                        : 0;
                    $detalle = match ($r['tipo']) {
                        'C' => "Pago : {$this->credit->id} Cuota:  {$ins->num_cuota}/{$totCuotas}",
                        'I' => "Pago : {$this->credit->id} Interes:  {$ins->num_cuota}/{$totCuotas} Dias : {$diasInt}",
                        'E' => "Pago : {$this->credit->id} Excedente:  {$ins->num_cuota}/{$totCuotas}",
                    };
                    $p = Payment::create([
                        'credit_id' => $this->credit->id, 'installment_id' => $ins->id,
                        'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
                        'fecha' => $this->fecpag, 'hora' => $hora, 'monto' => $r['monto'], 'moneda' => $semodn,
                        'detalle' => $detalle,
                        'asesor' => $this->credit->asesor, 'usuario' => $usuario, 'user_id' => $userId,
                        'headquarter_id' => $hqId, 'latitud' => $this->latitud, 'longitud' => $this->longitud,
                    ]);
                    $pagoPorClave[$r['num'].'|'.$r['tipo']] = $p->id;
                    $ultimoPagoId = $p->id;
                }

                // ── 3b) Aplicación al CRONOGRAMA + details ─────────────────
                // Los details siguen la aplicación (verdad del cronograma):
                // la reversa de Eliminar Masivo des-aplica exacto. Cada detail
                // se enlaza al payment de su cuota/tipo; si la caja lo registró
                // con otra etiqueta (divergencia legacy), al de la misma cuota
                // o al último de la operación.
                foreach ($dist['rows'] as $row) {
                    /** @var CreditInstallment $ins */
                    $ins = $row['ins'];
                    $payCap = $row['cap'];
                    $payInt = $row['int'];
                    $payExc = $row['exc'];
                    $num = (int) $ins->num_cuota;

                    if ($payCap > 0.001) {
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $massHeaderId, 'installment_id' => $ins->id,
                            'payment_id' => $pagoPorClave[$num.'|C'] ?? $pagoPorClave[$num.'|I'] ?? $ultimoPagoId,
                            'amount' => $payCap, 'fecha' => now(), 'tipo' => 'C',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $ins->importe_aplicado = (float) $ins->importe_aplicado + $payCap;
                    }

                    if ($payInt > 0.001) {
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $massHeaderId, 'installment_id' => $ins->id,
                            'payment_id' => $pagoPorClave[$num.'|I'] ?? $pagoPorClave[$num.'|C'] ?? $ultimoPagoId,
                            'amount' => $payInt, 'fecha' => now(), 'tipo' => 'I',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $ins->interes_aplicado = (float) $ins->interes_aplicado + $payInt;
                    }

                    if ($payExc > 0.001) {
                        DB::table('mass_deletion_details')->insert([
                            'mass_deletion_id' => $massHeaderId, 'installment_id' => $ins->id,
                            'payment_id' => $pagoPorClave[$num.'|E'] ?? $ultimoPagoId,
                            'amount' => $payExc, 'fecha' => now(), 'tipo' => 'E',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $ins->excedente_aplicado = (float) $ins->excedente_aplicado + $payExc;
                    }

                    if ($payCap > 0.001 || $payInt > 0.001 || $payExc > 0.001) {
                        $touchedThisPayment[] = $ins->id;
                    }

                    $totApli = (float) $ins->importe_aplicado + (float) $ins->interes_aplicado + (float) $ins->excedente_aplicado;
                    $totEsperado = (float) $ins->importe_cuota + (float) $ins->importe_interes + (float) $ins->importe_excedente;
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
            $moraPaymentId = null;
            if ($totMora > 0.001 && ! $this->ckmora && ! $quitarMoras) {
                $insTarget = $this->primeraCuotaParaMora($touchedThisPayment);

                $moraDetalle = ($calcs['mora_manual'] ?? false) ? 'Mora de cuota' : "Mora Acumulada Dias : {$diasA}";
                $p = $this->createMoraPayment('MORA', $totMora, $moraDetalle, $hora, $usuario, $userId, $hqId, $semodn);
                $moraPaymentId = $p->id;
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

            // ─── 5b) BITÁCORA DEL AJUSTE DE MORA ───────────────────────────
            // Se registra SIEMPRE que el override cambió el monto, incluso si lo
            // dejó en 0 (mora perdonada entera): ahí no hay pago MORA que mirar,
            // y es justo el caso que más importa poder auditar.
            if ($calcs['mora_ajustada'] ?? false) {
                $moraCalc = (float) $calcs['total_mora_calc'];
                $motivo = trim((string) $this->moraMotivo);

                DB::table('mora_overrides')->insert([
                    'credit_id' => $this->credit->id,
                    'payment_id' => $moraPaymentId,
                    'mass_deletion_id' => $massHeaderId,
                    'mora_calculada' => round($moraCalc, 2),
                    'mora_cobrada' => round($totMora, 2),
                    'diferencia' => round($totMora - $moraCalc, 2),
                    'dias_atraso' => $diasA,
                    'mora_diaria' => round((float) $calcs['mora_rate'], 2),
                    'motivo' => $motivo,
                    'fecha' => $this->fecpag,
                    'user_id' => $userId,
                    'usuario' => $usuario,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                // Dentro de la transacción a propósito: si el cobro se cae, el
                // rastro del ajuste se cae con él (no queda auditoría fantasma).
                Audit::log(
                    'Ajustó la mora del crédito #'.$this->credit->id.': calculada S/ '
                        .number_format($moraCalc, 2).' → cobrada S/ '.number_format($totMora, 2)
                        .' ('.$motivo.')',
                    $this->credit,
                    [
                        'mora_calculada' => round($moraCalc, 2),
                        'mora_cobrada' => round($totMora, 2),
                        'diferencia' => round($totMora - $moraCalc, 2),
                        'dias_atraso' => $diasA,
                        'motivo' => $motivo,
                        'fecha' => $this->fecpag,
                    ]
                );
            }

            // ─── 7) MORA CAPITAL manual (impomora) ─────────────────────────
            if ($this->impomora > 0.001) {
                $this->createMoraPayment('MORA CAPITAL', $this->impomora, "Mora Capital Dias : {$diasA}", $hora, $usuario, $userId, $hqId, $semodn);
                if ($this->idpre) {
                    DB::table('credit_installments')->where('id', $this->idpre)
                        ->increment('importe_mora', $this->impomora);
                }
            }

            // ─── 7b) MORA ACUMULADA cobrada al cancelar (pago MORA propio) ─
            // Documento 'MORA ACUM.' → entra a los reportes de caja como mora
            // (tipo=MORA / LIKE 'MORA%') y queda distinguible de la vigente.
            if ($moraAcumACobrar > 0.001) {
                $this->createMoraPayment('MORA ACUM.', $moraAcumACobrar, 'Mora Acumulada Cancelacion', $hora, $usuario, $userId, $hqId, $semodn);
            }

            // Al cancelar, la mora reservada del crédito muere con él
            // (se cobró como MORA ACUM. o se perdonó con "quitar moras").
            if ($this->cancel) {
                DB::table('mora_acumulada')->where('credit_id', $this->credit->id)->delete();
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

            // ─── 11) PAGO VÍA DEPÓSITO: egreso automático ──────────────────
            // El dinero entró al banco, no a la caja física: se registra el
            // egreso "Dep. ..." que el personal digitaba a mano (~20/día),
            // con los MISMOS campos que los manuales (los reportes lo
            // levantan sin cambios) y vinculado al cobro para la reversa.
            if ($this->metodoPago === 'deposito') {
                $egreso = Expense::create([
                    'date' => $this->fecpag,
                    'modo' => 'Otros',
                    'documento' => 'GUIA',
                    'caja' => 1,
                    'reason' => 'Banco',
                    'detail' => $this->descripcionEgresoDeposito(),
                    'total' => round($totalCobrado, 2),
                    'document_type' => 'Voucher',
                    'in_charge' => $this->nombreCortoUsuario(),
                    'user_id' => auth()->id(),
                    'headquarter_id' => auth()->user()?->headquarter_id ?? 1,
                    'mass_deletion_id' => $massHeaderId,
                ]);
                $this->egresoCreadoId = $egreso->id;
            }
        });

        // Foto del voucher: fuera de la transacción (mover archivos no es
        // transaccional); si falla, el egreso queda sin foto y se avisa.
        if ($this->egresoCreadoId && $this->voucherFoto) {
            try {
                $egreso = Expense::find($this->egresoCreadoId);
                if ($egreso) {
                    $this->storeExpenseAttachments($egreso, [$this->voucherFoto]);
                }
            } catch (\Throwable $e) {
                report($e);
                $this->dispatch('errorAlert', ['message' => 'El pago se registró, pero la foto del voucher no pudo guardarse: '.$e->getMessage()]);
            }
        }

        Audit::log(
            "Registró pago de {$this->monto} en el crédito #{$this->credit->id}".($this->cancel ? ' (canceló el crédito)' : '')
                .($this->metodoPago === 'deposito' ? ' vía depósito ('.$this->depCanal.')' : ''),
            $this->credit,
            ['monto' => $this->monto, 'fecha' => $this->fecpag]
        );

        // Se queda en la pantalla de pagos (cobranza en serie): cierra el
        // modal, limpia el formulario y recarga el crédito con el cronograma
        // ya actualizado. La confirmación queda en el aviso discreto de
        // "último cobro", sin alerta que tape la pantalla.
        $this->lastTicketId = $massHeaderId;
        $this->ultimoCobro = [
            'numero' => str_pad((string) $massHeaderId, 6, '0', STR_PAD_LEFT),
            'total' => round($totalCobrado, 2),
        ];

        $this->reset([
            'monto', 'obs', 'ckmora', 'cancel', 'impointe2', 'impomora',
            'idpre', 'moraManual', 'moraMotivo', 'cancelSinMora', 'cancelUltimaCuota', 'decisionTotal',
            'metodoPago', 'depBanco', 'depCuenta', 'depCuentaOtra', 'depCanal',
            'depFecha', 'voucherFoto', 'egresoCreadoId',
        ]);
        $this->preview = [];
        $this->resetValidation();

        $this->credit = Credit::with([
            'client.asesor:id,name',
            'installments' => fn ($q) => $q->orderBy('num_cuota'),
        ])->find($this->credit->id);

        $this->dispatch('ticket-close');

        if ($imprimir) {
            $this->imprimirTicket(app(TicketPrinter::class));
        }
    }

    /**
     * Imprime el recibo del último cobro en la ticketera (ESC/POS), igual que
     * el botón de la ficha del crédito. NO se imprime desde el navegador: la
     * ticketera está instalada con driver PostScript y ese camino le manda la
     * página renderizada, que ella escupe como texto basura.
     */
    public function imprimirTicket(TicketPrinter $printer): void
    {
        if (! $this->lastTicketId) {
            return;
        }

        $masivo = MassDeletion::with([
            'credit.client',
            'credit.headquarter:id,name',
            'details.installment:id,num_cuota',
        ])->find($this->lastTicketId);

        if (! $masivo) {
            $this->dispatch('errorAlert', ['message' => 'No se encontró el cobro a imprimir.']);

            return;
        }

        try {
            $printer->printPayment($masivo);
            // Éxito: se refleja en el aviso discreto, sin alerta encima.
            $this->ultimoCobro['impreso'] = true;
        } catch (\Throwable $e) {
            // El fallo sí interrumpe: el cajero necesita saber que no salió.
            $this->dispatch('errorAlert', ['message' => 'Error al imprimir: '.$e->getMessage()]);
        }
    }

    /**
     * Cronograma tal como quedará justo antes de repartir el monto: si el
     * cobro cancela el crédito, aplica EN MEMORIA las mismas condonaciones
     * que hace pagar() en la BD (excedente e interés futuros no devengados).
     * Nada de esto se guarda; es solo para simular el reparto.
     */
    private function cronogramaSimulado(): Collection
    {
        $all = CreditInstallment::where('credit_id', $this->credit->id)
            ->orderBy('num_cuota')->get();

        if (! $this->cancel) {
            return $all;
        }

        // Espejo de condonarExcedenteFuturo()
        foreach ($all as $ins) {
            if ((int) $ins->pagado === 0
                && $ins->fecha_vencimiento
                && Carbon::parse($ins->fecha_vencimiento)->gt(Carbon::parse($this->fecpag))
                && (float) $ins->importe_excedente > (float) $ins->excedente_aplicado) {
                $ins->importe_excedente = (float) $ins->excedente_aplicado;
            }
        }

        // Espejo de condonarInteresFuturo()
        $saldoPend = $this->saldoDe($all);
        $porCondonar = round($saldoPend - (float) $this->monto, 2);

        if ($porCondonar > 0.01) {
            foreach ($all->where('pagado', 0)->sortByDesc('num_cuota') as $ins) {
                if ($porCondonar <= 0.005) {
                    break;
                }
                $intPend = max(0, (float) $ins->importe_interes - (float) $ins->interes_aplicado);
                if ($intPend <= 0) {
                    continue;
                }
                $rebaja = min($intPend, $porCondonar);
                $ins->importe_interes = round((float) $ins->importe_interes - $rebaja, 2);
                $porCondonar = round($porCondonar - $rebaja, 2);
            }
        }

        return $all;
    }

    /** Saldo pendiente de un cronograma en memoria (capital + interés + excedente). */
    private function saldoDe(Collection $cuotas): float
    {
        return round($cuotas->sum(fn ($i) => (float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente
            - (float) $i->importe_aplicado - (float) $i->interes_aplicado - (float) $i->excedente_aplicado), 2);
    }

    /**
     * Recibo que se va a emitir, calculado sin escribir nada. Mismos números
     * y mismos criterios que TicketPrinter: "Cuotas" son las que reciben
     * capital y "Mora" es solo la que queda asociada a una cuota, aunque el
     * TOTAL sí incluye toda la mora cobrada.
     */
    private function construirPreview(): array
    {
        $calcs = $this->buildCalcs();
        $cronograma = $this->cronogramaSimulado();
        $unpaid = $cronograma->where('pagado', 0)->sortBy('num_cuota')->values();

        $isMensualUnaCuota = ((int) $this->credit->tipo_planilla === 3 && (int) $this->credit->cuotas === 1);
        $dist = $this->simularDistribucion((float) $this->monto, $unpaid, $isMensualUnaCuota);

        $totMora = (float) $calcs['total_mora'];
        $quitarMoras = $this->cancel && $this->cancelSinMora;
        $moraAcum = ($this->cancel && ! $quitarMoras)
            ? round(collect(MoraExonerada::porCuota($this->credit))->sum('monto'), 2)
            : 0.0;
        $moraQueSeCobra = ($this->ckmora || $quitarMoras) ? 0.0 : $totMora;

        $moraTicket = $moraQueSeCobra;
        if ((float) $this->impointe2 > 0.001 && $this->idpre) {
            $moraTicket += (float) $this->impointe2;
        }

        $total = round(
            (float) $this->monto + $moraQueSeCobra + $moraAcum
            + (float) $this->impointe2 + (float) $this->impomora,
            2
        );

        $aplicado = $dist['capital'] + $dist['interes'] + $dist['excedente'];
        $saldo = $this->cancel ? 0.0 : round($this->saldoDe($cronograma) - $aplicado, 2);

        $client = $this->credit->client;
        $nombre = $client
            ? trim(($client->apellido_pat ?? '').' '.($client->apellido_mat ?? '').' '.($client->nombre ?? ''))
            : null;

        return [
            'sede' => $this->credit->headquarter?->name,
            'cliente' => $nombre ?: null,
            'documento' => ($client && $client->documento)
                ? trim(($client->tipo_documento ?? 'DNI').' '.$client->documento)
                : null,
            'credit_id' => (int) $this->credit->id,
            'fecha' => Carbon::parse($this->fecpag)->format('d/m/Y'),
            'cuotas' => $dist['cuotas'],
            'capital' => $dist['capital'],
            'interes' => $dist['interes'],
            'excedente' => $dist['excedente'],
            'mora' => round($moraTicket, 2),
            'total' => $total,
            'saldo' => max(0.0, $saldo),
            'cancela' => $this->cancel,
            // Para la pregunta del modal cuando el cobro liquida el crédito.
            'cubre_total' => $this->cubreTotalidad(),
            'cancelar_cap_int' => $this->deudaCalcs()['cancelar_cap_int'],
            'mora_pendiente' => round($totMora, 2),
            'reserva_mora' => $this->ckmora && ! $this->cancel && $totMora > 0.001,
            // Ajuste manual de mora: se muestra en el modal para que quede a la
            // vista de quien confirma (antes solo se veía el monto final).
            'mora_ajustada' => $calcs['mora_ajustada'] ?? false,
            'mora_calculada' => round((float) ($calcs['total_mora_calc'] ?? 0), 2),
            // El valor ajustado en sí: NO usar 'mora' (esa es la del ticket, que
            // puede llevar mora interés o quedar en 0 si hay reserva).
            'mora_ajuste_final' => round($totMora, 2),
            'mora_motivo' => trim((string) $this->moraMotivo),
            // Pago vía depósito: método para el ticket + egreso que se
            // generará (transparencia total en el modal de confirmación).
            'metodo' => $this->metodoPago === 'deposito' ? "{$this->depCanal} - {$this->depBanco}" : null,
            'egreso' => $this->metodoPago === 'deposito' ? $this->descripcionEgresoDeposito() : null,
        ];
    }

    /**
     * Reparte un monto sobre las cuotas impagas SIN tocar nada. La usan tanto
     * pagar() (para persistir) como la vista previa del ticket, de modo que
     * lo que el cajero confirma en pantalla es exactamente lo que se cobra.
     * Delegado en App\Services\Payments\DistribucionPago (motor espejo del
     * legacy, validable con `payments:backtest-legacy`); ahí vive la regla
     * del sobrante por operación y su documentación.
     *
     * @param  iterable  $unpaid  cuotas impagas ordenadas por num_cuota
     * @return array{
     *   rows: array<int, array{ins: mixed, cap: float, int: float, exc: float}>,
     *   capital: float, interes: float, excedente: float, cuotas: array<int, int>
     * }
     */
    private function simularDistribucion(float $monto, iterable $unpaid, bool $isMensualUnaCuota): array
    {
        return app(MotorPagos::class)
            ->distribuir($monto, $unpaid, $isMensualUnaCuota, $this->esCuotaUniforme());
    }

    /**
     * Cancelación anticipada: condona el interés futuro no devengado.
     * Descuento = saldo pendiente del cronograma − monto pagado. Se rebaja
     * del interés de las cuotas impagas empezando por la ÚLTIMA (el interés
     * más lejano es el menos devengado), y queda auditado.
     */
    /**
     * Cancelación: condona el excedente de redondeo (cuota uniforme) de las
     * cuotas que aún no vencen a la fecha de pago. El de cuotas ya vencidas
     * se mantiene (se cobra como parte de la deuda al día).
     */
    private function condonarExcedenteFuturo(): void
    {
        DB::table('credit_installments')
            ->where('credit_id', $this->credit->id)
            ->where('pagado', 0)
            ->whereDate('fecha_vencimiento', '>', $this->fecpag)
            ->whereColumn('importe_excedente', '>', 'excedente_aplicado')
            ->update(['importe_excedente' => DB::raw('excedente_aplicado')]);
    }

    private function condonarInteresFuturo(): void
    {
        $saldoPend = (float) DB::table('credit_installments')
            ->where('credit_id', $this->credit->id)
            ->selectRaw('SUM(importe_cuota + importe_interes + importe_excedente - importe_aplicado - interes_aplicado - excedente_aplicado) s')
            ->value('s');
        $descuento = round($saldoPend - (float) $this->monto, 2);
        if ($descuento <= 0.01) {
            return; // el monto cubre el cronograma completo: nada que condonar
        }

        $porCondonar = $descuento;
        $unpaid = DB::table('credit_installments')
            ->where('credit_id', $this->credit->id)
            ->where('pagado', 0)
            ->orderByDesc('num_cuota')
            ->get(['id', 'importe_interes', 'interes_aplicado']);

        foreach ($unpaid as $ins) {
            if ($porCondonar <= 0.005) {
                break;
            }
            $intPend = max(0, (float) $ins->importe_interes - (float) $ins->interes_aplicado);
            if ($intPend <= 0) {
                continue;
            }
            $rebaja = min($intPend, $porCondonar);
            DB::table('credit_installments')->where('id', $ins->id)
                ->update(['importe_interes' => round((float) $ins->importe_interes - $rebaja, 2)]);
            $porCondonar = round($porCondonar - $rebaja, 2);
        }

        Audit::log(sprintf(
            'Cancelación anticipada del crédito %d: descuento de interés no devengado %s',
            $this->credit->id,
            number_format($descuento - max(0, $porCondonar), 2)
        ), $this->credit);
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
                'exc_hoy' => 0.0, 'exc_prox' => 0.0,
                'cap_hoy' => 0.0, 'int_hoy' => 0.0, 'cap_prox' => 0.0, 'int_prox' => 0.0,
                'mora' => 0.0, 'mora_dias' => 0, 'mora_rate' => 0.0,
                'total_hoy' => 0.0, 'total_prox' => 0.0,
                'cap_pendiente_total' => 0.0, 'saldo_credito' => 0.0,
                'int_cancelar' => 0.0, 'cancelar_cap_int' => 0.0, 'total_cancelar' => 0.0,
                'ultima_venc' => null, 'dias_adelanto' => 0,
                'primera_vencida_num' => null, 'primera_vencida_fecha' => null,
                'proxima_num' => null, 'proxima_fecha' => null];
        }

        $al = $al ?? now();

        $installments = $this->credit->installments;
        $vencidas = $installments->filter(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->lte($al));

        // Pendiente de cuotas ya vencidas
        $capVenc = $vencidas->sum(fn ($i) => max(0, (float) $i->importe_cuota - (float) $i->importe_aplicado));
        $intVenc = $vencidas->sum(fn ($i) => max(0, (float) $i->importe_interes - (float) $i->interes_aplicado));
        // Excedente de redondeo (cuota uniforme) pendiente de cuotas vencidas
        $excVenc = $vencidas->sum(fn ($i) => max(0, (float) $i->importe_excedente - (float) $i->excedente_aplicado));
        $nVencidas = $vencidas->filter(fn ($i) => ! $i->pagado)->count();

        $capHoy = $capVenc;
        $intHoy = $intVenc;
        $capProx = $capVenc;
        $intProx = $intVenc;
        $excProx = $excVenc;

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
                    // Cuotas completas adelantadas: incluyen su excedente
                    $excProx += $periodos * (float) ($cuotaPeriodo->importe_excedente ?? 0);
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

        $ultimaVenc = $installments->max('fecha_vencimiento');
        $ultimaVenc = $ultimaVenc ? Carbon::parse($ultimaVenc) : null;

        // Referencias para los disclaimers de las tarjetas
        $primeraVencida = $vencidas->first(fn ($i) => ! $i->pagado);
        $proximaCuota = $installments->first(fn ($i) => $i->fecha_vencimiento && $i->fecha_vencimiento->gt($al));

        // El capital adeudado nunca excede el capital pendiente del cronograma
        $capHoy = min($capHoy, $capPendTotal);
        $capProx = min($capProx, $capPendTotal);

        // Interés para cancelar: topado al interés pendiente del cronograma.
        // Pasada la última cuota el interés corrido (int_hoy) sigue creciendo
        // por día, pero esa demora se cobra vía mora; sin el tope, el gate del
        // switch Cancelado exigiría más que el saldo pagable y sería imposible
        // de habilitar.
        $intCancelar = min($intHoy, max(0, $saldoCredito - $capPendTotal));

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
            'exc_hoy' => round($excVenc, 2),
            'exc_prox' => round($excProx, 2),
            'mora' => $mora,
            'mora_dias' => $moraInfo['dias_final'],
            'mora_rate' => $moraInfo['mora_rate'],
            'total_hoy' => round($capHoy + $intHoy + $excVenc + $mora, 2),
            'total_prox' => round($capProx + $intProx + $excProx + $mora, 2),
            'cap_pendiente_total' => $capPendTotal,
            'saldo_credito' => $saldoCredito,
            // Cancelar crédito: todo el capital pendiente + interés corrido A LA
            // FECHA (no el interés futuro del cronograma, y topado al pendiente
            // del cronograma) + excedente de cuotas ya vencidas (el de futuras
            // se condona al cancelar). Mora aparte.
            'int_cancelar' => round($intCancelar, 2),
            'cancelar_cap_int' => round($capPendTotal + $intCancelar + $excVenc, 2),
            'total_cancelar' => round($capPendTotal + $intCancelar + $excVenc + $mora, 2),
            // Adelanto vs la última cuota del cronograma (para el disclaimer
            // del descuento de interés al cancelar antes de tiempo)
            'ultima_venc' => $ultimaVenc?->format('Y-m-d'),
            'dias_adelanto' => $ultimaVenc ? max(0, (int) floor($al->diffInDays($ultimaVenc, false))) : 0,
            // Referencias de periodo/cuota para los disclaimers
            'primera_vencida_num' => $primeraVencida?->num_cuota,
            'primera_vencida_fecha' => $primeraVencida?->fecha_vencimiento?->format('Y-m-d'),
            'proxima_num' => $proximaCuota?->num_cuota,
            'proxima_fecha' => $proximaCuota?->fecha_vencimiento?->format('Y-m-d'),
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
            'sim' => $this->deudaCalcs($alSim),    // a la fecha simulada: tarjetas
            'cancelarHoy' => $this->deudaCalcs()['cancelar_cap_int'], // gate del switch Cancelado
            'fecsimMin' => $fecsimMin,
            'moraExon' => $this->credit ? MoraExonerada::porCuota($this->credit) : [],
            'recibos' => $this->recibosPorCuota(),
        ]);
    }

    /**
     * Último cobro (mass_deletion) que tocó cada cuota + links del recibo
     * para la columna "Recibo" del cronograma:
     *  - 'wa': WhatsApp al celular1 del cliente con el link PÚBLICO firmado
     *    del recibo (URL::signedRoute, permanente — el cliente lo abre sin
     *    login; un id alterado da 403).
     *  - 'pdf': descarga del recibo en PDF (misma firma).
     * Cuotas sin cobro asociado no aparecen (la columna va vacía).
     *
     * @return array<int, array{wa: ?string, pdf: string, ver: string}> por installment_id
     */
    private function recibosPorCuota(): array
    {
        if (! $this->credit) {
            return [];
        }

        $ultimoPorCuota = DB::table('mass_deletion_details')
            ->join('mass_deletions', 'mass_deletions.id', '=', 'mass_deletion_details.mass_deletion_id')
            ->where('mass_deletions.credit_id', $this->credit->id)
            ->whereNotNull('mass_deletion_details.installment_id')
            ->groupBy('mass_deletion_details.installment_id')
            ->selectRaw('mass_deletion_details.installment_id, MAX(mass_deletion_details.mass_deletion_id) as md_id')
            ->pluck('md_id', 'installment_id');

        if ($ultimoPorCuota->isEmpty()) {
            return [];
        }

        $cobros = DB::table('mass_deletions')
            ->whereIn('id', $ultimoPorCuota->unique())
            ->get(['id', 'amount', 'date'])
            ->keyBy('id');

        $tel = preg_replace('/\D/', '', (string) $this->credit->client?->celular1);
        $empresa = (string) config('printer.company_name', 'HUACACHIN');

        $out = [];
        foreach ($ultimoPorCuota as $insId => $mdId) {
            $cobro = $cobros->get($mdId);
            if (! $cobro) {
                continue;
            }

            $ver = URL::signedRoute('recibo.publico', ['massDeletionId' => $mdId]);
            $pdf = URL::signedRoute('recibo.pdf', ['massDeletionId' => $mdId]);

            $wa = null;
            if ($tel !== '') {
                $msg = $empresa.': su recibo de pago #'.str_pad((string) $mdId, 6, '0', STR_PAD_LEFT)
                    .' del '.Carbon::parse($cobro->date)->format('d/m/Y')
                    .' por S/ '.number_format((float) $cobro->amount, 2)
                    // Link corto (/s/{code}): la URL firmada mide ~130 caracteres
                    // y dentro del mensaje espanta; el corto es idempotente por
                    // recibo, así que la tabla no crece con cada render.
                    .'. Vealo aqui: '.ShortLink::para($ver);
                $wa = 'https://api.whatsapp.com/send?phone=51'.$tel.'&text='.rawurlencode($msg);
            }

            $out[(int) $insId] = ['wa' => $wa, 'pdf' => $pdf, 'ver' => $ver];
        }

        return $out;
    }
}
