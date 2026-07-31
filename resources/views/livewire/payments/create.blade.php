<div class="container-fluid">    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">PAGO DE CRÉDITO MASIVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Pago Masivo</span></li>
            </ul>
        </div>
    </div>

    @if(!$credit)
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted">No se ha seleccionado un crédito.</p>
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    @else
        @php $c = $calcs; @endphp

        @php
            // Para cancelar el crédito el Monto a Pagar debe cubrir el capital
            // pendiente + interés a la fecha ($cancelarHoy). El interés futuro
            // no devengado se condona al pagar; la mora se cobra o reserva
            // aparte (switch "Reserva Mora").
            $cancelDisabled = ($cancelarHoy - (float) $monto) > 0.01;

            // Solo los créditos ACTIVOS admiten pagos: en cancelados,
            // refinanciados o eliminados se ocultan el formulario de pago,
            // las tarjetas de escenario y el botón Pagar (queda la consulta:
            // datos, cronograma y recibos). Espejo del legacy, que aborta
            // con estado=0. El backend lo refuerza en validarCobro().
            $esPagable = $credit->situacion === 'Activo';
        @endphp

        @unless($esPagable)
            @php
                $bannerColor = match ($credit->situacion) {
                    'Cancelado' => 'alert-secondary',
                    'Refinanciado' => 'alert-warning',
                    default => 'alert-danger',
                };
            @endphp
            <div class="alert {{ $bannerColor }} d-flex align-items-center gap-2 py-2 mb-2">
                <i class="ti ti-lock f-s-18"></i>
                <div>
                    <strong>Crédito {{ strtoupper($credit->situacion) }}</strong>
                    @if($credit->situacion === 'Cancelado' && $credit->fecha_cancelacion)
                        el {{ $credit->fecha_cancelacion->format('d/m/Y') }}
                    @endif
                    — no admite pagos. Puedes consultar el cronograma y reenviar o descargar los recibos.
                </div>
            </div>
        @endunless

        {{-- Formulario --}}
        <form wire:submit.prevent="confirmarPago" class="pago-form">
            <style>
                /* Inputs sin negrita en el value (sin tamaño custom; usa el del tema). */
                .pago-form .form-control { font-weight: 400 !important; }
                /* Campos de alerta: rojo puro y texto SIEMPRE blanco (readonly,
                   disabled o editable por igual). */
                .pago-form .input-rojo,
                .pago-form .input-rojo:disabled,
                .pago-form .input-rojo[readonly] {
                    background: #ff0000 !important;
                    color: #fff !important;
                }
                .pago-form .input-rojo::placeholder { color: #ffd6d6; }
                /* Valores en negrita (gana al font-weight 400 de arriba) */
                .pago-form .input-bold { font-weight: 700 !important; }
                /* Readonly informativos: plomo más oscuro que el bg-light del tema
                   (incluye el "Calcular al" de las tarjetas, fuera del form) */
                .pago-form .form-control.bg-light,
                .form-control.bg-light.dates-dyn {
                    background-color: #93a1b1 !important;
                }
            </style>
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- ── Datos del Crédito (solo lectura) ── --}}
                    <h6 class="mb-1" style="color:red;">Datos del Crédito</h6>
                    <div class="row g-2">
                        <div class="col-md-1">
                            <label class="form-label mb-0 small fw-semibold">Expediente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->client?->expediente }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Cliente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->id }}-{{ $credit->client?->fullName() }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">DNI</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->client?->documento }}" maxlength="8" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Ejecutivo</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['asesor_nombre'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Moneda</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->moneda ?: 'Soles' }}" readonly>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Capital</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ number_format($c['importe'], 2) }}" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label mb-0 small fw-semibold">INT. %</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ number_format($c['interes_pct'], 0) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Interés</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ number_format($c['interes_total'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Total</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ number_format($c['total_credito'], 2) }}" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label mb-0 small fw-semibold">MOR. %</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ \App\Models\Credit::TASA_MORA_PCT }}%" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Pago x día</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red;"
                                   value="{{ number_format($c['mora_rate'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Vencimiento</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['fecha_venc'] }}" readonly>
                        </div>
                    </div>

                    @if($esPagable)
                    {{-- ── Atraso ── --}}
                    <h6 class="mb-1 mt-3" style="color:red;">Atraso</h6>
                    <div class="row g-2">
                        {{-- Mora acumulada (exonerada) histórica: informativa, igual al
                             total de la columna Mora Exon. del cronograma. NO entra en
                             la operación de pago. --}}
                        @php
                            $moraAcumDias = collect($moraExon)->sum('dias');
                            $moraAcumTotal = collect($moraExon)->sum('monto');
                        @endphp
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Días Mora Acumulada</label>
                            <input type="text" class="form-control form-control-sm input-bold"
                                   style="background-color:#fac10f; color:#000;"
                                   value="{{ $moraAcumDias }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Total Mora Acumulada</label>
                            <input type="text" class="form-control form-control-sm input-bold"
                                   style="background-color:#fac10f; color:#000;"
                                   value="{{ number_format($moraAcumTotal, 2) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Días Transcurridos</label>
                            @php $diasAtr = (int) $c['dias_atraso']; @endphp
                            @if(!$c['fecha_venc'])
                                <input type="text" class="form-control form-control-sm bg-light"
                                       value="Sin cuotas pendientes" readonly>
                            @elseif($diasAtr > 0)
                                <input type="text" class="form-control form-control-sm input-rojo"
                                       value="{{ $diasAtr }} {{ $diasAtr === 1 ? 'día' : 'días' }} de atraso" readonly>
                            @elseif($diasAtr === 0)
                                <input type="text" class="form-control form-control-sm bg-success text-white"
                                       value="Vence hoy" readonly>
                            @else
                                <input type="text" class="form-control form-control-sm bg-success text-white"
                                       value="Al día · vence en {{ abs($diasAtr) }} {{ abs($diasAtr) === 1 ? 'día' : 'días' }}" readonly>
                            @endif
                        </div>
                        <div class="col-md-3">
                            @php $puedeMora = auth()->user()->can('pagos.mora-manual'); @endphp
                            <label class="form-label mb-0 small fw-semibold">
                                Total Mora
                                @if($puedeMora)
                                    <i class="ti {{ ((float)$monto) > 0 ? 'ti-pencil' : 'ti-lock' }} f-s-12"
                                       title="{{ ((float)$monto) > 0 ? 'Editable (override gerencial)' : 'Escribe el Monto a Pagar para habilitar' }}"></i>
                                @endif
                            </label>
                            @if($puedeMora)
                                <input type="number" name="moraManual" autocomplete="off" step="0.01" min="0"
                                       class="form-control form-control-sm input-rojo"
                                       wire:model.live.debounce.400ms="moraManual"
                                       placeholder="{{ number_format($c['total_mora_calc'], 2) }}"
                                       @disabled(((float)$monto) <= 0)
                                       title="{{ ((float)$monto) > 0 ? 'Total Mora editable (reemplaza la calculada)' : 'Escribe primero el Monto a Pagar' }}">
                            @else
                                <input type="text" class="form-control form-control-sm input-rojo"
                                       value="{{ number_format($c['total_mora'], 2) }}" readonly>
                            @endif
                        </div>
                    </div>

                    {{-- ── Registrar Pago ──
                         (El legacy tenía "Mora ¿?" para la mora extra al sobrepagar; aquí el
                         sobrepago está bloqueado —Monto ≤ Saldo— y la mora va en Total Mora.) --}}
                    <h6 class="mb-1 mt-3" style="color:red;">Registrar Pago</h6>
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Saldo Pendiente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:white; font-size:15px;"
                                   value="{{ number_format($c['saldo_restante'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Saldo P. + Mora</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:white; font-size:15px;"
                                   value="{{ number_format($c['saldo_mora_restante'], 2) }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Monto a Pagar</label>
                            <input type="number" name="monto" autocomplete="off" class="form-control form-control-sm"
                                   wire:model.live.debounce.400ms="monto"
                                   min="0.00" max="{{ $c['saldo_pendiente'] }}" step="0.01"
                                   style="background:#fff9c4;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Pago</label>
                            <input type="text" name="fecpag" autocomplete="off" class="form-control form-control-sm bg-light dates3"
                                   wire:model="fecpag" readonly>
                        </div>
                    </div>


                    {{-- Switches --}}
                    <div class="d-flex gap-4 mt-3 align-items-center flex-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   wire:model.live="ckmora" id="ckmora">
                            <label class="form-check-label fw-semibold ms-1" for="ckmora" style="color:red;">
                                Reserva Mora
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            {{-- Al MARCAR se intercepta el click y se pide
                                 confirmación en el modal #cancelarModal
                                 (ver @script); desmarcar pasa directo. --}}
                            <input class="form-check-input" type="checkbox" role="switch"
                                   wire:model="cancel" id="cancel"
                                   {{ $cancelDisabled ? 'disabled' : '' }}>
                            <label class="form-check-label fw-semibold ms-1" for="cancel"
                                   style="color:{{ $cancelDisabled ? '#999' : 'red' }};">
                                Cancelado
                                @if($cancelDisabled)
                                    <small class="text-muted">
                                        (se habilita al cubrir capital + interés a la fecha: {{ number_format($cancelarHoy, 2) }})
                                    </small>
                                @endif
                            </label>
                        </div>
                    </div>

                    {{-- ── Método de pago ── --}}
                    <h6 class="mb-1 mt-3" style="color:red;">Método de pago</h6>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Método de pago">
                        <button type="button" wire:click="$set('metodoPago', 'efectivo')"
                                class="btn {{ $metodoPago === 'efectivo' ? 'btn-dark' : 'btn-outline-dark' }}">
                            <i class="ti ti-cash"></i> Efectivo
                        </button>
                        <button type="button" wire:click="$set('metodoPago', 'deposito')"
                                class="btn {{ $metodoPago === 'deposito' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ti ti-building-bank"></i> Depósito
                        </button>
                    </div>

                    @if($metodoPago === 'deposito')
                        <div class="mt-2 p-3 rounded-3"
                             style="background:#f4f8ff; border-left:4px solid #2a78d6;">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1 small fw-semibold">Banco</label>
                                    <select class="form-select form-select-sm" wire:model.live="depBanco">
                                        @foreach(\App\Livewire\Payments\Create::DEP_BANCOS as $b)
                                            <option value="{{ $b }}">{{ $b }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1 small fw-semibold">Cuenta receptora</label>
                                    <select class="form-select form-select-sm" wire:model.live="depCuenta">
                                        @foreach(\App\Livewire\Payments\Create::DEP_CUENTAS as $cta)
                                            <option value="{{ $cta }}">{{ $cta }}</option>
                                        @endforeach
                                    </select>
                                    @if($depCuenta === 'Otra')
                                        <input type="text" class="form-control form-control-sm mt-1"
                                               wire:model.live="depCuentaOtra" placeholder="Nombre del titular">
                                    @endif
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1 small fw-semibold">Canal</label>
                                    <select class="form-select form-select-sm" wire:model.live="depCanal">
                                        @foreach(\App\Livewire\Payments\Create::DEP_CANALES as $ch)
                                            <option value="{{ $ch }}">{{ $ch }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1 small fw-semibold">
                                        Fecha del depósito
                                        <span class="text-muted fw-normal">(vacío = hoy)</span>
                                    </label>
                                    <input type="date" class="form-control form-control-sm"
                                           wire:model.live="depFecha" max="{{ now()->format('Y-m-d') }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label mb-1 small fw-semibold">
                                        <i class="ti ti-camera"></i> Foto del voucher
                                        <span class="text-muted fw-normal">(opcional)</span>
                                    </label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        @if($voucherFoto)
                                            <div class="position-relative">
                                                <img src="{{ $voucherFoto->temporaryUrl() }}" alt="Voucher"
                                                     style="height:64px; border-radius:8px; border:1px solid #cfe0f5;">
                                                <button type="button" wire:click="$set('voucherFoto', null)"
                                                        class="btn btn-sm btn-danger p-0 position-absolute top-0 start-100 translate-middle rounded-circle"
                                                        style="width:20px; height:20px; line-height:1;" title="Quitar foto">
                                                    <i class="ti ti-x" style="font-size:12px;"></i>
                                                </button>
                                            </div>
                                            <span class="small text-success"><i class="ti ti-circle-check"></i> Voucher listo — quedará adjunto al egreso</span>
                                        @else
                                            <label class="btn btn-sm btn-outline-primary mb-0">
                                                <i class="ti ti-upload"></i>
                                                <span wire:loading.remove wire:target="voucherFoto">Seleccionar imagen</span>
                                                <span wire:loading wire:target="voucherFoto">Subiendo…</span>
                                                <input type="file" class="d-none" wire:model="voucherFoto" accept="image/*">
                                            </label>
                                        @endif
                                    </div>
                                    @error('voucherFoto') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 pt-2" style="border-top:1px dashed #cfe0f5;">
                                    <span class="small">
                                        <i class="ti ti-circle-check text-primary"></i>
                                        Egreso automático al cobrar:
                                        <code style="background:#fff; padding:2px 8px; border-radius:5px; border:1px solid #cfe0f5; color:#1d4ed8;">Dep. {{ $depBanco }} {{ $depCuenta === 'Otra' ? ($depCuentaOtra ?: '…') : $depCuenta }} {{ $credit->client?->fullName() }} ({{ $depCanal }}){{ $depFecha !== '' && $depFecha !== $fecpag ? ' '.\Carbon\Carbon::parse($depFecha)->format('d/m') : '' }}</code>
                                        por el total cobrado.
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @endif {{-- $esPagable: Atraso + Registrar Pago --}}

                    {{-- Botones --}}
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        @if($esPagable)
                            <button type="submit" class="btn btn-sm btn-dark"
                                    wire:loading.attr="disabled" wire:target="confirmarPago">
                                <i class="ti ti-thumb-up"></i>
                                <span wire:loading.remove wire:target="confirmarPago">Pagar</span>
                                <span wire:loading wire:target="confirmarPago">Calculando…</span>
                            </button>
                            @if((int) $credit->cuotas === 1)
                                <a href="{{ route('payments.refinance', $credit->id) }}" class="btn btn-sm btn-warning">
                                    <i class="ti ti-refresh"></i> Refinanciar
                                </a>
                            @else
                                <a href="#" class="btn btn-sm btn-warning disabled"
                                   title="Solo se puede refinanciar un préstamo de una cuota">
                                    <i class="ti ti-refresh"></i> Refinanciar
                                </a>
                            @endif
                            <a href="#" class="btn btn-sm btn-success disabled" title="Próximamente">
                                <i class="ti ti-file-spreadsheet"></i> Excel
                            </a>
                        @else
                            <a href="{{ route('credits.show', $credit->id) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-eye"></i> Ver ficha del crédito
                            </a>
                        @endif
                        <a href="{{ route('clients.show', $credit->client_id) }}"
                           class="btn btn-sm btn-danger ms-auto">
                            <i class="ti ti-arrow-back"></i> Regresar
                        </a>
                    </div>

                    {{-- Aviso discreto del último cobro: reemplaza a la alerta
                         emergente, que tapaba la pantalla en cobranza en serie. --}}
                    @if($ultimoCobro)
                        <div class="d-flex align-items-center gap-2 mt-2 small text-success">
                            <i class="ti ti-circle-check"></i>
                            <span>
                                Último cobro registrado: recibo <strong>#{{ $ultimoCobro['numero'] }}</strong>
                                por <strong>S/ {{ number_format($ultimoCobro['total'], 2) }}</strong>
                                @if($ultimoCobro['impreso'] ?? false)
                                    · ticket impreso
                                @endif
                            </span>
                            @if($lastTicketId)
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                        wire:click="imprimirTicket" wire:loading.attr="disabled" wire:target="imprimirTicket">
                                    <i class="ti ti-printer"></i>
                                    <span wire:loading.remove wire:target="imprimirTicket">Reimprimir</span>
                                    <span wire:loading wire:target="imprimirTicket">Enviando…</span>
                                </button>
                                <a href="{{ route('payments.ticket', $lastTicketId) }}" target="_blank"
                                   class="btn btn-link btn-sm p-0 text-decoration-none">
                                    <i class="ti ti-eye"></i> Ver recibo
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </form>

        {{-- ═══ Modal: confirmar el switch "Cancelado" ═══ --}}
        {{-- Reemplaza al confirm() nativo del navegador. SIEMPRE en el DOM
             (misma regla que el modal de cobro: si se condiciona, el
             backdrop puede quedar huérfano en un morph de Livewire). --}}
        <div class="modal fade" id="cancelarModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0">
                            <i class="ti ti-alert-triangle text-danger"></i> Cancelar crédito
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="mb-2">
                            Si marcas <strong>Cancelado</strong>, este crédito
                            <strong class="text-danger">ya no podrá ser refinanciado</strong>.
                        </p>
                        <p class="mb-0 small text-muted">
                            La cancelación se aplica recién al confirmar el cobro: se condona el
                            interés no devengado y el crédito se cierra con saldo 0.
                        </p>
                    </div>
                    <div class="modal-footer justify-content-between py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                            Volver
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="btn-confirmar-cancelado">
                            <i class="ti ti-circle-check"></i> Sí, marcar Cancelado
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Modal de confirmación del cobro ═══ --}}
        {{-- OJO: SIEMPRE en el DOM, aunque el crédito no sea pagable. Si se
             envuelve en @if($esPagable), al CANCELAR un crédito desde el
             propio modal Livewire lo elimina del DOM antes de que Bootstrap
             lo cierre y el backdrop negro queda huérfano/pegado (bug real
             del 24/07). El backend ya bloquea pagos en no-activos. --}}
        {{-- El botón Pagar NO cobra: abre esto con el recibo ya calculado
             (confirmarPago). Recién "Cobrar" / "Cobrar e imprimir" escriben.
             La impresión sale por ESC/POS a la ticketera; NUNCA por el diálogo
             del navegador, que está instalada con driver PostScript y en ese
             camino escupe la página renderizada como texto basura. --}}
        <div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true" wire:ignore.self
             x-data="{ modal: null }"
             x-init="modal = bootstrap.Modal.getOrCreateInstance($el)"
             x-on:ticket-confirm.window="modal.show()"
             x-on:ticket-close.window="modal.hide()">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0">Confirmar cobro</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body py-3" style="background:#e9ecef;">
                        @if($preview)
                            {{-- Vista previa del ticket, con la misma pinta que sale impreso --}}
                            <div class="ticket-preview mx-auto bg-white">
                                <div class="text-center">
                                    <div class="tp-empresa">{{ config('printer.company_name', 'HUACACHIN') }}</div>
                                    @if($preview['sede'])
                                        <div>{{ $preview['sede'] }}</div>
                                    @endif
                                </div>

                                <div class="tp-sep-dbl"></div>
                                <div class="text-center fw-bold">RECIBO DE PAGO</div>
                                <div class="tp-sep"></div>

                                <div class="tp-row"><span>Fecha:</span><span>{{ $preview['fecha'] }}</span></div>
                                @if(!empty($preview['metodo']))
                                    <div class="tp-row"><span>Pago:</span><span>{{ $preview['metodo'] }}</span></div>
                                @endif
                                @if($preview['cliente'])
                                    <div class="tp-row"><span>Cliente:</span><span>{{ $preview['cliente'] }}</span></div>
                                @endif
                                @if($preview['documento'])
                                    <div class="tp-row"><span>Doc:</span><span>{{ $preview['documento'] }}</span></div>
                                @endif
                                <div class="tp-row"><span>Credito:</span><span>#{{ $preview['credit_id'] }}</span></div>

                                <div class="tp-sep"></div>

                                @if($preview['cuotas'])
                                    <div class="tp-row"><span>Cuotas:</span><span>{{ implode(',', $preview['cuotas']) }}</span></div>
                                @endif
                                <div class="tp-row"><span>Capital:</span><span>{{ number_format($preview['capital'], 2) }}</span></div>
                                <div class="tp-row"><span>Interes:</span><span>{{ number_format($preview['interes'], 2) }}</span></div>
                                @if($preview['excedente'] > 0.001)
                                    <div class="tp-row"><span>Excedente:</span><span>{{ number_format($preview['excedente'], 2) }}</span></div>
                                @endif
                                @if($preview['mora'] > 0.001)
                                    <div class="tp-row"><span>Mora:</span><span>{{ number_format($preview['mora'], 2) }}</span></div>
                                @endif

                                <div class="tp-sep"></div>
                                <div class="tp-row tp-total"><span>TOTAL</span><span>S/ {{ number_format($preview['total'], 2) }}</span></div>
                                <div class="tp-sep"></div>
                                <div class="tp-row"><span>Saldo restante:</span><span>S/ {{ number_format($preview['saldo'], 2) }}</span></div>
                            </div>

                            @if($preview['cancela'])
                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small text-center">
                                    <i class="ti ti-alert-triangle"></i> Este cobro <strong>cancela</strong> el crédito.
                                </div>
                            @endif
                            @if($preview['reserva_mora'])
                                <div class="alert alert-info py-1 px-2 mt-2 mb-0 small text-center">
                                    Mora reservada: no se cobra ahora, queda acumulada.
                                </div>
                            @endif
                            @if(!empty($preview['egreso']))
                                <div class="alert alert-primary py-1 px-2 mt-2 mb-0 small">
                                    <i class="ti ti-cash"></i> Pago vía <strong>{{ $preview['metodo'] }}</strong>.
                                    Se registrará el egreso:<br>
                                    <strong>{{ $preview['egreso'] }}</strong> por S/ {{ number_format($preview['total'], 2) }}
                                    @if($voucherFoto)
                                        <div class="mt-1 text-center">
                                            <img src="{{ $voucherFoto->temporaryUrl() }}" alt="Voucher"
                                                 style="max-height:90px; border-radius:6px; border:1px solid #dee2e6;">
                                            <div class="text-muted" style="font-size:.7rem;">Voucher adjunto</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Cobro que liquida el crédito: hay que decidir si se cierra.
                         Antes se podía cobrar el total con el switch apagado y el
                         crédito quedaba Vigente sin que nadie avisara. --}}
                    @if($preview['cubre_total'] ?? false)
                        <div class="mx-3 mb-2 p-2 rounded" style="background:#fff4e5; border:1px solid #ffb74d;">
                            <div class="fw-bold mb-1" style="color:#b26a00;">
                                <i class="ti ti-alert-triangle"></i> Este pago cubre el TOTAL del crédito
                            </div>
                            <div class="d-flex gap-3 flex-wrap small mb-2">
                                <span>Capital + interés: <b>S/ {{ number_format($preview['cancelar_cap_int'], 2) }}</b></span>
                                @if(($preview['mora_pendiente'] ?? 0) > 0.001)
                                    <span style="color:#c0392b;">Mora pendiente: <b>S/ {{ number_format($preview['mora_pendiente'], 2) }}</b></span>
                                @endif
                            </div>
                            <div class="fw-semibold small mb-1">¿Cancelar el crédito?</div>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="decSi"
                                           value="si" wire:model.live="decisionTotal">
                                    <label class="form-check-label small fw-semibold" for="decSi">
                                        Sí, cancelar el crédito
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="decNo"
                                           value="no" wire:model.live="decisionTotal">
                                    <label class="form-check-label small fw-semibold" for="decNo">
                                        No, dejarlo vigente
                                    </label>
                                </div>
                            </div>
                            @if($decisionTotal === '')
                                <div class="small text-muted mt-1">
                                    Elige una opción para poder cobrar.
                                </div>
                            @endif
                        </div>
                    @endif

                    @php
                        // Sin respuesta no se puede cobrar: es el punto donde se perdían.
                        $faltaDecidir = ($preview['cubre_total'] ?? false) && $decisionTotal === '';
                    @endphp

                    <div class="modal-footer justify-content-between py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"
                                wire:loading.attr="disabled" wire:target="pagar">
                            Cancelar
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    wire:click="pagar(false)"
                                    @disabled($faltaDecidir)
                                    wire:loading.attr="disabled" wire:target="pagar">
                                Cobrar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary"
                                    wire:click="pagar(true)"
                                    @disabled($faltaDecidir)
                                    wire:loading.attr="disabled" wire:target="pagar">
                                <i class="ti ti-printer"></i>
                                <span wire:loading.remove wire:target="pagar">Cobrar e imprimir</span>
                                <span wire:loading wire:target="pagar">Procesando…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .ticket-preview {
                width: 260px;
                padding: 10px 12px;
                font-family: "Courier New", Courier, monospace;
                font-size: 11px;
                line-height: 1.35;
                color: #000;
            }
            .ticket-preview .tp-empresa { font-size: 13px; font-weight: bold; }
            .ticket-preview .tp-row { display: flex; justify-content: space-between; gap: 8px; }
            .ticket-preview .tp-row > span:last-child { text-align: right; white-space: nowrap; }
            .ticket-preview .tp-total { font-size: 13px; font-weight: bold; }
            .ticket-preview .tp-sep { border-top: 1px dashed #000; margin: 4px 0; }
            .ticket-preview .tp-sep-dbl { border-top: 3px double #000; margin: 4px 0; }
        </style>

        @script
        <script>
            // Barrendero de backdrops: si el modal ya no está visible pero
            // Bootstrap dejó un backdrop huérfano (p. ej. por un morph de
            // Livewire a mitad del cierre), se limpia. Corre tras cada
            // ticket-close con un pequeño margen para la animación.
            window.addEventListener('ticket-close', () => {
                setTimeout(() => {
                    if (! document.querySelector('#ticketModal.show')) {
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('padding-right');
                    }
                }, 350);
            });

            // Switch "Cancelado": al MARCAR se revierte el click y se pide
            // confirmación en el modal; el botón del modal aplica el check
            // y dispara 'change' para que wire:model lo sincronice.
            // Delegación en document: sobrevive los morphs de Livewire.
            document.addEventListener('click', (e) => {
                const chk = e.target.closest?.('#cancel');
                if (chk && chk.checked) {
                    e.preventDefault(); // revierte el marcado
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelarModal')).show();
                    return;
                }
                if (e.target.closest?.('#btn-confirmar-cancelado')) {
                    const sw = document.getElementById('cancel');
                    if (sw) {
                        sw.checked = true;
                        sw.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    bootstrap.Modal.getInstance(document.getElementById('cancelarModal'))?.hide();
                }
            });

            // Capturar GPS si el navegador lo permite
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        $wire.set('latitud', pos.coords.latitude.toString(), false);
                        $wire.set('longitud', pos.coords.longitude.toString(), false);
                    },
                    (err) => { /* silenciosamente ignorar */ }
                );
            }
        </script>
        @endscript

        @if($esPagable)
        {{-- Tarjetas de escenario: ¿cuánto paga el cliente? --}}
        @php $simEsHoy = $sim['fecha'] === now()->format('Y-m-d'); @endphp
        <div class="card shadow-sm mt-2">
            <div class="card-body pb-2">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="mb-0">
                        ¿CUÁNTO PAGA EL CLIENTE?
                        @unless($simEsHoy)
                            <span class="badge bg-info ms-1">Simulado al {{ \Carbon\Carbon::parse($sim['fecha'])->format('d/m/Y') }}</span>
                        @endunless
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 small fw-semibold">Calcular al:</label>
                        <input type="text" autocomplete="off" readonly
                               class="form-control form-control-sm bg-light dates-dyn" style="width:110px;"
                               wire:model.live="fecsim" data-mindate="{{ $fecsimMin }}"
                               title="Se puede retroceder hasta el último pago registrado ({{ \Carbon\Carbon::parse($fecsimMin)->format('d/m/Y') }})">
                    </div>
                </div>
                <div class="row g-2">
                    {{-- Escenario 1: ponerse al día --}}
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100 d-flex flex-column">
                            <div class="fw-bold small text-uppercase mb-1">Ponerse al día</div>
                            <div class="d-flex justify-content-between small"><span>Capital</span><span>{{ number_format($sim['cap_hoy'], 2) }}</span></div>
                            <div class="d-flex justify-content-between small"><span>Interés</span><span>{{ number_format($sim['int_hoy'], 2) }}</span></div>
                            @if($sim['exc_hoy'] > 0)
                                <div class="d-flex justify-content-between small"><span>Excedente</span><span>{{ number_format($sim['exc_hoy'], 2) }}</span></div>
                            @endif
                            <div class="d-flex justify-content-between small">
                                <span>Mora @if($sim['mora_dias'] > 0)({{ $sim['mora_dias'] }} {{ $sim['mora_dias'] === 1 ? 'día' : 'días' }} × {{ number_format($sim['mora_rate'], 2) }})@endif</span>
                                <span style="color:red;">{{ number_format($sim['mora'], 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>Total</span><span>{{ number_format($sim['total_hoy'], 2) }}</span></div>

                            <div class="rounded p-2 mt-2 mb-2 small text-muted" style="background:#f2f4f7;">
                                {{ $sim['cuotas_vencidas'] }} cuota(s) vencida(s)
                                @if($sim['dias_adic'] > 0)
                                    + {{ $sim['dias_adic'] }} día(s) del período en curso ({{ number_format($sim['cap_dia'] + $sim['int_dia'], 2) }}/día)
                                @endif
                            </div>

                            @if($sim['primera_vencida_fecha'])
                                <div class="rounded p-2 mb-2 small" style="background:#e7f0fa; color:#1d5b96;">
                                    <i class="ti ti-calendar f-s-12"></i>
                                    Pagas el periodo de la cuota <b>{{ $sim['primera_vencida_num'] }}</b>
                                    ({{ \Carbon\Carbon::parse($sim['primera_vencida_fecha'])->format('d/m/Y') }})
                                    al <b>{{ \Carbon\Carbon::parse($sim['fecha'])->format('d/m/Y') }}</b>
                                </div>
                            @endif

                            @if($simEsHoy)
                                <button type="button" class="btn btn-sm btn-dark mt-auto"
                                        wire:click="usarMonto({{ $sim['cap_hoy'] + $sim['int_hoy'] + $sim['exc_hoy'] }})">
                                    Usar {{ number_format($sim['cap_hoy'] + $sim['int_hoy'] + $sim['exc_hoy'], 2) }} en Monto a Pagar
                                </button>
                            @endif
                        </div>
                    </div>
                    {{-- Escenario 2: hasta la próxima cuota --}}
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100 d-flex flex-column">
                            <div class="fw-bold small text-uppercase mb-1">Hasta la próx. cuota</div>
                            <div class="d-flex justify-content-between small"><span>Capital</span><span>{{ number_format($sim['cap_prox'], 2) }}</span></div>
                            <div class="d-flex justify-content-between small"><span>Interés</span><span>{{ number_format($sim['int_prox'], 2) }}</span></div>
                            @if($sim['exc_prox'] > 0)
                                <div class="d-flex justify-content-between small"><span>Excedente</span><span>{{ number_format($sim['exc_prox'], 2) }}</span></div>
                            @endif
                            <div class="d-flex justify-content-between small">
                                <span>Mora @if($sim['mora_dias'] > 0)({{ $sim['mora_dias'] }} {{ $sim['mora_dias'] === 1 ? 'día' : 'días' }} × {{ number_format($sim['mora_rate'], 2) }})@endif</span>
                                <span style="color:red;">{{ number_format($sim['mora'], 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>Total</span><span>{{ number_format($sim['total_prox'], 2) }}</span></div>

                            <div class="rounded p-2 mt-2 mb-2 small text-muted" style="background:#f2f4f7;">
                                {{ $sim['cuotas_vencidas'] }} cuota(s) vencida(s)
                                @if($sim['periodos'] > 0)
                                    + {{ $sim['periodos'] }} cuota(s) completa(s) del período en curso
                                @endif
                            </div>

                            @if($sim['proxima_fecha'])
                                <div class="rounded p-2 mb-2 small" style="background:#e7f0fa; color:#1d5b96;">
                                    <i class="ti ti-calendar f-s-12"></i>
                                    Pagas hasta la cuota <b>{{ $sim['proxima_num'] }}</b>
                                    ({{ \Carbon\Carbon::parse($sim['proxima_fecha'])->format('d/m/Y') }})
                                </div>
                            @endif

                            @if($simEsHoy)
                                <button type="button" class="btn btn-sm btn-dark mt-auto"
                                        wire:click="usarMonto({{ $sim['cap_prox'] + $sim['int_prox'] + $sim['exc_prox'] }})">
                                    Usar {{ number_format($sim['cap_prox'] + $sim['int_prox'] + $sim['exc_prox'], 2) }} en Monto a Pagar
                                </button>
                            @endif
                        </div>
                    </div>
                    {{-- Escenario 3: cancelar el crédito --}}
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100 d-flex flex-column">
                            @php
                                // Interés: a la fecha (topado al pendiente del cronograma), o
                                // todo el pendiente (saldo_credito = cap + int) si se marca el check.
                                $intCancelar = $cancelUltimaCuota
                                    ? round($sim['saldo_credito'] - $sim['cap_pendiente_total'], 2)
                                    : $sim['int_cancelar'];
                                $morasCancelar = $cancelSinMora ? 0.0 : round($sim['mora'] + $moraAcumTotal, 2);
                                // Excedente (cuota uniforme): solo el de cuotas vencidas;
                                // el de cuotas futuras se condona al cancelar.
                                $capIntCancelar = round($sim['cap_pendiente_total'] + $intCancelar + $sim['exc_hoy'], 2);
                                $totalCancelarCard = round($capIntCancelar + $morasCancelar, 2);
                                // El botón llena solo capital + interés: la mora vigente y la
                                // mora acumulada se cobran automáticamente como pagos MORA al
                                // cancelar (salvo "quitar moras"), así caja recibe el Total.
                                $tachado = 'color:red; text-decoration:line-through; opacity:.6;';
                            @endphp
                            <div class="fw-bold small text-uppercase mb-1">Cancelar crédito</div>
                            <div class="d-flex justify-content-between small"><span>Capital pendiente</span><span>{{ number_format($sim['cap_pendiente_total'], 2) }}</span></div>
                            <div class="d-flex justify-content-between small">
                                <span>{{ $cancelUltimaCuota ? 'Interés hasta la última cuota' : 'Interés a la fecha' }}</span>
                                <span>{{ number_format($intCancelar, 2) }}</span>
                            </div>
                            @if($sim['exc_hoy'] > 0)
                                <div class="d-flex justify-content-between small"><span>Excedente vencido</span><span>{{ number_format($sim['exc_hoy'], 2) }}</span></div>
                            @endif
                            <div class="d-flex justify-content-between small">
                                <span>Mora @if($sim['mora_dias'] > 0)({{ $sim['mora_dias'] }} {{ $sim['mora_dias'] === 1 ? 'día' : 'días' }} × {{ number_format($sim['mora_rate'], 2) }})@endif</span>
                                <span style="{{ $cancelSinMora ? $tachado : 'color:red;' }}">{{ number_format($sim['mora'], 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span>Mora acumulada @if($moraAcumDias > 0)({{ $moraAcumDias }} {{ $moraAcumDias === 1 ? 'día' : 'días' }})@endif</span>
                                <span style="{{ $cancelSinMora ? $tachado : 'color:#b8860b;' }}">{{ number_format($moraAcumTotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>Total</span><span>{{ number_format($totalCancelarCard, 2) }}</span></div>

                            {{-- Disclaimer: adelanto vs la última cuota y descuento de interés --}}
                            @php
                                $descuentoInt = round(($sim['saldo_credito'] - $sim['cap_pendiente_total']) - $sim['int_cancelar'], 2);
                            @endphp
                            @if(!$cancelUltimaCuota && $sim['dias_adelanto'] > 0 && $descuentoInt > 0)
                                <div class="rounded p-2 mt-2 small" style="background:#e7f4ea; color:#1e7b34;">
                                    <i class="ti ti-discount-2 f-s-12"></i>
                                    Te adelantas <b>{{ $sim['dias_adelanto'] }} {{ $sim['dias_adelanto'] === 1 ? 'día' : 'días' }}</b>
                                    a la última cuota ({{ \Carbon\Carbon::parse($sim['ultima_venc'])->format('d/m/Y') }}):
                                    descuento de interés <b>{{ number_format($descuentoInt, 2) }}</b>
                                </div>
                            @endif

                            {{-- Opciones de la cotización --}}
                            <div class="rounded p-2 mt-2 mb-2" style="background:#f2f4f7;">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="cancelSinMora" wire:model.live="cancelSinMora">
                                    <label class="form-check-label small fw-semibold" for="cancelSinMora">
                                        Quitar mora y mora acumulada
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="cancelUltimaCuota" wire:model.live="cancelUltimaCuota">
                                    <label class="form-check-label small fw-semibold" for="cancelUltimaCuota">
                                        Cancelar hasta la última cuota
                                    </label>
                                </div>
                            </div>

                            @if($simEsHoy)
                                <button type="button" class="btn btn-sm btn-dark mt-auto"
                                        wire:click="usarMonto({{ $capIntCancelar }})">
                                    Usar {{ number_format($capIntCancelar, 2) }} en Monto a Pagar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    El botón llena "Monto a Pagar" con capital + interés; la mora se cobra aparte
                    (según el switch "Reserva Mora").
                    @unless($simEsHoy)
                        <span class="fw-semibold">Cotización simulada: para registrar el pago, vuelve la fecha a hoy.</span>
                    @endunless
                </div>
            </div>
        </div>

        @endif {{-- $esPagable: tarjetas de escenario --}}

        {{-- Cronograma de cuotas (homologado con /credits/{id}) --}}
        <div class="card shadow-sm mt-2">
            <div class="card-body pb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="mb-0">CRONOGRAMA DE CUOTAS</h6>
                    <x-scroll-bottom-btn scrollable="#tabla-cronograma" />
                </div>
                @php $tieneExc = $credit->installments->sum('importe_excedente') > 0; @endphp
                <div id="tabla-cronograma" class="table-responsive tableFixHead" style="max-height: 500px; overflow:auto;">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="bg-primary">
                        <tr>
                            <th>Cuota</th>
                            <th>Fecha Venc.</th>
                            <th>Capital</th>
                            <th>Interés</th>
                            @if($tieneExc)
                                <th>Excedente</th>
                            @endif
                            <th>Pagado Cap.</th>
                            <th>Pagado Int.</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th style="color:#ffd6d6;">Mora Exon.</th>
                            <th>Estado</th>
                            <th>Fecha Pago</th>
                            <th class="text-center">Recibo</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($credit->installments as $inst)
                            @php
                                $saldo = $inst->saldoPendiente();
                                $vencida = !$inst->pagado && $inst->fecha_vencimiento?->isPast();
                            @endphp
                            <tr class="{{ $vencida ? 'table-danger' : '' }}">
                                <td>{{ $inst->num_cuota }}</td>
                                <td>{{ $inst->fecha_vencimiento?->format('d/m/Y') }}</td>
                                <td class="text-end">{{ number_format($inst->importe_cuota, 2) }}</td>
                                <td class="text-end">{{ number_format($inst->importe_interes, 2) }}</td>
                                @if($tieneExc)
                                    <td class="text-end">{{ number_format($inst->importe_excedente, 2) }}</td>
                                @endif
                                <td class="text-end">{{ number_format($inst->importe_aplicado, 2) }}</td>
                                <td class="text-end">{{ number_format($inst->interes_aplicado, 2) }}</td>
                                <td class="text-end">{{ number_format($inst->importe_aplicado + $inst->interes_aplicado + $inst->excedente_aplicado, 2) }}</td>
                                <td class="text-end">{{ number_format($saldo, 2) }}</td>
                                @php $me = $moraExon[$inst->num_cuota] ?? null; @endphp
                                <td class="text-end" style="color:red; white-space:nowrap;">{{ number_format($me['monto'] ?? 0, 2) }}@if($me) - D. {{ $me['dias'] }}@endif</td>
                                <td>
                                    @if($inst->pagado)
                                        <span class="badge bg-success">Pagado</span>
                                    @elseif($vencida)
                                        <span class="badge bg-danger">Vencida</span>
                                    @else
                                        <span class="badge bg-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td>{{ $inst->fecha_pago?->format('d/m/Y') }}</td>
                                <td class="text-center" style="white-space:nowrap;">
                                    @php $rec = $recibos[$inst->id] ?? null; @endphp
                                    @if($rec)
                                        @if($rec['wa'])
                                            <a href="{{ $rec['wa'] }}" target="_blank" rel="noopener"
                                               class="btn btn-sm btn-success py-0 px-1"
                                               title="Enviar recibo por WhatsApp al cliente">
                                                <i class="ti ti-brand-whatsapp"></i>
                                            </a>
                                        @endif
                                        <a href="{{ $rec['pdf'] }}"
                                           class="btn btn-sm btn-secondary py-0 px-1"
                                           title="Descargar recibo en PDF">
                                            <i class="ti ti-file-download"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        @php
                            $tCap    = $credit->installments->sum('importe_cuota');
                            $tInt    = $credit->installments->sum('importe_interes');
                            $tExc    = $credit->installments->sum('importe_excedente');
                            $tPagCap = $credit->installments->sum('importe_aplicado');
                            $tPagInt = $credit->installments->sum('interes_aplicado');
                            $tPagExc = $credit->installments->sum('excedente_aplicado');
                            $tSaldo  = $credit->installments->sum(fn ($i) => $i->saldoPendiente());
                        @endphp
                        <tfoot>
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="2" class="text-end">Totales</td>
                                <td class="text-end">{{ number_format($tCap, 2) }}</td>
                                <td class="text-end">{{ number_format($tInt, 2) }}</td>
                                @if($tieneExc)
                                    <td class="text-end">{{ number_format($tExc, 2) }}</td>
                                @endif
                                <td class="text-end">{{ number_format($tPagCap, 2) }}</td>
                                <td class="text-end">{{ number_format($tPagInt, 2) }}</td>
                                <td class="text-end">{{ number_format($tPagCap + $tPagInt + $tPagExc, 2) }}</td>
                                <td class="text-end">{{ number_format($tSaldo, 2) }}</td>
                                @php
                                    $tExon = collect($moraExon)->sum('monto');
                                    $tExonDias = collect($moraExon)->sum('dias');
                                @endphp
                                {{-- Mora exonerada: informativa, no suma a los demás totales --}}
                                <td class="text-end" style="color:red; white-space:nowrap;">{{ number_format($tExon, 2) }}@if($tExonDias > 0) - D. {{ $tExonDias }}@endif</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
<span id="final"></span>
</div>
