<div class="container-fluid"
     x-data
     x-on:monto-usado.window="const el = document.getElementById('monto-a-pagar'); if (!el) return; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus({ preventScroll: true }); const tarjeta = el.closest('.saldo-kpi'); tarjeta.classList.remove('saldo-resaltado'); void tarjeta.offsetWidth; tarjeta.classList.add('saldo-resaltado'); setTimeout(() => tarjeta.classList.remove('saldo-resaltado'), 2600)">
    <div class="row">
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
        @php
            $c = $calcs;
            // 17/10 (Antony): de qué cuota(s) es el Total Mora. Si viene del desglose
            // de "elegir cuotas" (con permiso), nombra las cuotas elegidas con mora;
            // si no, es la calculada de la cuota impaga más antigua.
            $cuotaMora = $c['mora_cuota'] ?? null;
            // 30/10: con "Calcular cancelación" (sin Total Mora tecleado) también nombra varias:
            // la mora es la suma de cada cuota vencida.
            $cuotasMora = ! empty($c['mora_cuotas'])
                ? $c['mora_cuotas']
                : ($cuotaMora ? [$cuotaMora] : []);
            $moraDeVencidas = ! ($c['mora_manual'] ?? false) && count($cuotasMora) > 1;
            $rotuloMora = \App\Support\RotuloCuotas::de($cuotasMora);
            $totalMoraTxt = $rotuloMora !== '' ? ' · '.$rotuloMora : '';
            // Rótulo de la mora en las tarjetas de escenario (a la fecha simulada).
            $etqMoraSim = count($sim['mora_cuotas'] ?? []) > 1
                ? 'Mora '.\App\Support\RotuloCuotas::de($sim['mora_cuotas']).' ('.$sim['mora_dias_total'].' días en total)'
                : 'Mora'.($cuotaMora ? ' cuota '.$cuotaMora : '')
                    .($sim['mora_dias'] > 0 ? ' ('.$sim['mora_dias'].' '.($sim['mora_dias'] === 1 ? 'día' : 'días').' × '.number_format($sim['mora_rate'], 2).')' : '');
        @endphp

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

        {{-- Formulario. 22/10 (Antony): "Pagar" pide primero la ubicación del navegador
             (pagarConUbicacion, en el @script de abajo) y recién llama a confirmarPago. --}}
        <form onsubmit="pagarConUbicacion(); return false;" class="pago-form">
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

                    {{-- ── Datos del Crédito (solo lectura) ──
                         Homologado a la banda de tarjetas (05/09) pero con el PESO
                         VISUAL MÁS BAJO de la pantalla: son datos de contexto, no
                         cifras de acción. Sin borde de color ni número grande —
                         etiqueta pequeña arriba y valor sobrio debajo. --}}
                    <h6 class="mb-1" style="color:red;">Datos del Crédito</h6>
                    <div class="dato-band">
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Expediente</div>
                            <div class="dato-val">{{ $credit->client?->expediente }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 4;">
                            <div class="dato-top">Cliente</div>
                            <div class="dato-val">{{ $credit->id }}-{{ $credit->client?->fullName() }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">DNI</div>
                            <div class="dato-val">{{ $credit->client?->documento }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 3;">
                            <div class="dato-top">Ejecutivo</div>
                            <div class="dato-val">{{ $c['asesor_nombre'] }}</div>
                        </div>
                        <div class="dato">
                            <div class="dato-top">Moneda</div>
                            <div class="dato-val">{{ $credit->moneda ?: 'Soles' }}</div>
                        </div>

                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Capital</div>
                            <div class="dato-val">{{ number_format($c['importe'], 2) }}</div>
                        </div>
                        <div class="dato">
                            <div class="dato-top">Int. %</div>
                            <div class="dato-val">{{ number_format($c['interes_pct'], 0) }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Interés</div>
                            <div class="dato-val">{{ number_format($c['interes_total'], 2) }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Total</div>
                            <div class="dato-val fw-bold">{{ number_format($c['total_credito'], 2) }}</div>
                        </div>
                        <div class="dato">
                            <div class="dato-top">Mor. %</div>
                            <div class="dato-val">{{ \App\Models\Credit::TASA_MORA_PCT }}%</div>
                        </div>
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Pago x día</div>
                            <div class="dato-val text-danger">{{ number_format($c['mora_rate'], 2) }}</div>
                        </div>
                        <div class="dato" style="grid-column: span 2;">
                            <div class="dato-top">Vencimiento</div>
                            <div class="dato-val">{{ $c['fecha_venc'] }}</div>
                        </div>
                    </div>

                    @if($esPagable)
                    {{-- ── Atraso ──
                         Peso visual INTERMEDIO: conserva el semáforo (ámbar la mora
                         acumulada, rojo la vigente) pero como tinte suave y cifra
                         mediana, para que "Registrar Pago" siga mandando. Antes eran
                         barras saturadas que le ganaban a los saldos. --}}
                    <h6 class="mb-1 mt-3" style="color:red;">Atraso</h6>
                    @php
                        // Mora acumulada (exonerada) histórica: informativa, igual al
                        // total de la columna Mora Exon. del cronograma. NO entra en
                        // la operación de pago.
                        $moraAcumDias = collect($moraExon)->sum('dias');
                        $moraAcumTotal = collect($moraExon)->sum('monto');
                        $diasAtr = (int) $c['dias_atraso'];
                        $puedeMora = auth()->user()->can('pagos.mora-manual');
                        // 17/10 (Antony): los días transcurridos son siempre de la impaga más
                        // antigua; el Total Mora nombra sus cuotas ($rotuloMora, arriba).
                        $cuotaMoraTxt = $cuotaMora ? ' · cuota '.$cuotaMora : '';
                        $diasTitle = $cuotaMora
                            ? "Días desde el vencimiento de la cuota {$cuotaMora}, la impaga más antigua (".\Carbon\Carbon::parse($c['fecha_venc'])->format('d/m/Y').')'
                            : '';
                        $cuotaMoraTitle = count($cuotasMora) > 1
                            ? ($moraDeVencidas
                                ? "Mora de las {$rotuloMora}: suma de la mora de cada cuota vencida (\"Calcular cancelación\" las cancela todas)."
                                : "Mora de las {$rotuloMora}: suma de la mora de cada cuota marcada en \"elegir cuotas\".")
                            : ($cuotaMora
                                ? "Mora de la cuota {$cuotaMora} (la impaga más antigua, vencida el ".\Carbon\Carbon::parse($c['fecha_venc'])->format('d/m/Y')."). No es la suma de la mora de todas las cuotas vencidas: para cobrar varias, usa \"elegir cuotas\"."
                                : '');
                    @endphp
                    <div class="atraso-band">
                        <div class="atraso-kpi atraso-ambar" title="Mora exonerada de cuotas que ya se pagaron con atraso (columna Mora Exon. del cronograma). No entra en este cobro.">
                            <div class="atraso-top">Días Mora Acumulada</div>
                            <div class="atraso-val">{{ $moraAcumDias }}</div>
                            <div class="atraso-pie">de cuotas ya pagadas</div>
                        </div>
                        <div class="atraso-kpi atraso-ambar" title="Mora exonerada de cuotas que ya se pagaron con atraso (columna Mora Exon. del cronograma). No entra en este cobro.">
                            <div class="atraso-top">Total Mora Acumulada</div>
                            <div class="atraso-val">{{ number_format($moraAcumTotal, 2) }}</div>
                            <div class="atraso-pie">de cuotas ya pagadas</div>
                        </div>
                        <div class="atraso-kpi {{ ! $c['fecha_venc'] ? 'atraso-gris' : ($diasAtr > 0 ? 'atraso-rojo' : 'atraso-verde') }}" title="{{ $diasTitle }}">
                            <div class="atraso-top">Días Transcurridos</div>
                            @if(! $c['fecha_venc'])
                                <div class="atraso-val">—</div>
                                <div class="atraso-pie">sin cuotas pendientes</div>
                            @elseif($diasAtr > 0)
                                <div class="atraso-val">{{ $diasAtr }}</div>
                                <div class="atraso-pie">{{ $diasAtr === 1 ? 'día' : 'días' }} de atraso{{ $cuotaMoraTxt }}</div>
                            @elseif($diasAtr === 0)
                                <div class="atraso-val">Hoy</div>
                                <div class="atraso-pie">vence hoy{{ $cuotaMoraTxt }}</div>
                            @else
                                <div class="atraso-val">Al día</div>
                                <div class="atraso-pie">vence en {{ abs($diasAtr) }} {{ abs($diasAtr) === 1 ? 'día' : 'días' }}{{ $cuotaMoraTxt }}</div>
                            @endif
                        </div>
                        <div class="atraso-kpi {{ $c['total_mora'] > 0 ? 'atraso-rojo' : 'atraso-verde' }}" title="{{ $cuotaMoraTitle }}">
                            <div class="atraso-top">
                                Total Mora
                                @if($puedeMora)
                                    <i class="ti {{ ((float)$monto) > 0 ? 'ti-pencil' : 'ti-lock' }} f-s-12"
                                       title="{{ ((float)$monto) > 0 ? 'Editable (override gerencial)' : 'Escribe el Monto a Pagar para habilitar' }}"></i>
                                @endif
                            </div>
                            @if($cancel && $quitarMora)
                                {{-- Condonada por el switch: manda sobre el campo (editable o no) --}}
                                <div class="atraso-val" style="text-decoration: line-through;"
                                     title="Mora exonerada al cancelar (switch Exonerar mora)">0.00</div>
                                <div class="atraso-pie">exonerada</div>
                            @elseif($puedeMora)
                                <input type="number" name="moraManual" autocomplete="off" step="0.01" min="0"
                                       class="atraso-val atraso-input"
                                       wire:model.live.debounce.400ms="moraManual"
                                       placeholder="{{ number_format($c['total_mora_calc'], 2) }}"
                                       @disabled(((float)$monto) <= 0)
                                       title="{{ ((float)$monto) > 0 ? 'Total Mora editable (reemplaza la calculada)' : 'Escribe primero el Monto a Pagar' }}">
                                <div class="atraso-pie">{{ ((float)$monto) > 0 ? 'editable' : 'calculada' }}{{ $totalMoraTxt }}</div>
                            @else
                                <div class="atraso-val">{{ number_format($c['total_mora'], 2) }}</div>
                                <div class="atraso-pie">calculada{{ $totalMoraTxt }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="row g-2">
                        {{-- Motivo del ajuste: aparece solo cuando el override CAMBIÓ el
                             monto. Sin él el cobro no se confirma — toda rebaja de mora
                             queda con responsable y explicación en `mora_overrides`. --}}
                        @if($puedeMora && ($c['mora_ajustada'] ?? false))
                            @php $diff = $c['mora_ajuste_diff'] ?? 0; @endphp
                            <div class="col-12">
                                <label class="form-label mb-0 small fw-semibold text-danger">
                                    <i class="ti ti-alert-triangle f-s-14"></i>
                                    Motivo del ajuste de mora
                                    <span class="fw-normal">
                                        — calculada <b>{{ number_format($c['total_mora_calc'], 2) }}</b>,
                                        se cobrará <b>{{ number_format($c['total_mora'], 2) }}</b>
                                        ({{ $diff < 0 ? 'rebaja' : 'aumento' }} de
                                        <b>{{ number_format(abs($diff), 2) }}</b>)
                                    </span>
                                </label>
                                <input type="text" name="moraMotivo" autocomplete="off" maxlength="255"
                                       class="form-control form-control-sm @error('moraMotivo') is-invalid @enderror"
                                       placeholder="{{ ($diff ?? 0) > 0 ? 'Explica por qué se cobra más mora que la calculada…' : 'Motivo del ajuste…' }}"
                                       wire:model.live.debounce.500ms="moraMotivo"
                                       placeholder="Ej.: acuerdo con el cliente, error de digitación, condonación autorizada por gerencia…">
                                @error('moraMotivo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif
                    </div>

                    {{-- ── Registrar Pago ──
                         (El legacy tenía "Mora ¿?" para la mora extra al sobrepagar; aquí el
                         sobrepago está bloqueado —Monto ≤ Saldo— y la mora va en Total Mora.) --}}
                    <h6 class="mb-1 mt-3" style="color:red;">Registrar Pago</h6>
                    <div class="row g-2">
                        {{-- Los dos saldos NO son campos: son las cifras que el cajero
                             tiene que leer de un vistazo. Van como tarjetas KPI, el
                             mismo patrón de /reports/portfolio y el dashboard. Antes
                             eran inputs readonly con texto blanco sobre gris: bajo
                             contraste y parecían campos deshabilitados. --}}
                        <div class="col-12">
                            <div class="saldo-band">
                                <div class="saldo-kpi" style="--kpi: #0d6efd;">
                                    <div class="saldo-top"><i class="ti ti-wallet"></i> Saldo Pendiente</div>
                                    <div class="saldo-val">{{ number_format($c['saldo_restante'], 2) }}</div>
                                    @php
                                        // 08/09: este saldo es del CRONOGRAMA COMPLETO (trae el
                                        // interés futuro); cancelar hoy cuesta menos porque ese
                                        // interés se condona. Sin decirlo aquí, "Saldo Pendiente
                                        // 15,000" y "este pago cubre el total" con 10,875 parecían
                                        // contradecirse.
                                        $hayInteresFuturo = $cancelarHoy > 0.01 && ($c['saldo_pendiente'] - $cancelarHoy) > 0.01;
                                    @endphp
                                    {{-- Textos cortos: la banda lleva 4 tarjetas y a 1366px un pie
                                         largo se parte en dos líneas. Sin depender del switch
                                         Cancelado (enlace diferido: quedaría desfasado). --}}
                                    <div class="saldo-pie">
                                        @if($hayInteresFuturo && ! $cancelDisabled)
                                            alcanza para cancelar hoy
                                        @elseif($hayInteresFuturo)
                                            cancelar hoy: S/ {{ number_format($cancelarHoy, 2) }}
                                        @else
                                            capital + interés
                                        @endif
                                    </div>
                                </div>
                                <div class="saldo-kpi" style="--kpi: {{ $c['total_mora'] > 0 ? '#dc3545' : '#2eb85c' }};">
                                    <div class="saldo-top"><i class="ti ti-cash"></i> Saldo P. + Mora</div>
                                    <div class="saldo-val">{{ number_format($c['saldo_mora_restante'], 2) }}</div>
                                    <div class="saldo-pie">
                                        @if ($c['total_mora'] > 0)
                                            incluye mora {{ number_format($c['total_mora'], 2) }}
                                        @else
                                            sin mora
                                        @endif
                                    </div>
                                </div>
                                {{-- Monto a Pagar: la MISMA tarjeta, pero es el campo de
                                     acción de la pantalla — borde ámbar, fondo cálido y el
                                     número tan grande como los saldos. El input pierde su
                                     marco: la tarjeta es el marco. --}}
                                <div class="saldo-kpi saldo-editable" style="--kpi: #f0ad4e;">
                                    <div class="saldo-top"><i class="ti ti-coin"></i> Monto a Pagar</div>
                                    <input type="number" name="monto" id="monto-a-pagar" autocomplete="off" class="saldo-input"
                                           wire:model.live.debounce.400ms="monto"
                                           min="0.00" max="{{ $c['saldo_pendiente'] }}" step="0.01"
                                           placeholder="0.00">
                                    <div class="saldo-pie">
                                        @if($cancelHasta !== null && $cancel)
                                            <span class="text-danger fw-semibold">cancela · interés hasta la cuota {{ $cancelHasta }}</span>
                                        @elseif($cancelHasta !== null)
                                            calculado hasta la cuota {{ $cancelHasta }} · sin cancelar
                                        @else
                                            máx. {{ number_format($c['saldo_pendiente'], 2) }}
                                        @endif
                                    </div>
                                </div>
                                {{-- 23/10 (Antony): donde estaba el cuadro de Fecha de Pago (los cobros van
                                     siempre con la fecha del día) van los dos botones que arman el Monto a Pagar. --}}
                                <div class="saldo-acciones">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click.prevent="abrirMoraCuotas"
                                            title="Elegir qué cuotas pagar, cada una con su mora por días">
                                        <i class="ti ti-list-check"></i> Elegir cuotas
                                    </button>
                                    {{-- 30/10 (Antony): "Calcular cancelación" pasa a "Calcular pagos", con dos pestañas:
                                         la cancelación y lo que el cliente debe pagar a una fecha. --}}
                                    <span class="d-grid" title="Calcular el total para cancelar, o lo que el cliente debe pagar a la fecha que elijas">
                                        <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="$dispatch('calcular-pagos')">
                                            <i class="ti ti-calculator"></i> Calcular pagos
                                        </button>
                                    </span>
                                </div>
                            </div>
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
                                        (se habilita al cubrir capital + {{ $cancelUltimaCuota ? 'interés completo' : 'interés a la fecha' }}: {{ number_format($cancelarHoy, 2) }})
                                    </small>
                                @endif
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            {{-- Interés completo (30/09): al cancelar cobra el interés de TODO el
                                 cronograma y el mínimo pasa a ser el cronograma entero. Es el
                                 mismo flag que "Cancelar hasta la última cuota" de la tarjeta. --}}
                            <input class="form-check-input" type="checkbox" role="switch"
                                   wire:model.live="cancelUltimaCuota" id="interesCompleto">
                            <label class="form-check-label fw-semibold ms-1" for="interesCompleto" style="color:#b8860b;">
                                Interés completo
                                <small class="text-muted">(al cancelar cobra todo el cronograma: {{ number_format($cancelarTodo, 2) }})</small>
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
                            {{-- Estado de la ubicación del navegador (lo pinta el @script; wire:ignore
                                 para que el morph de Livewire no lo borre). --}}
                            <span id="ubicacion-estado" class="small align-self-center" wire:ignore></span>
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

        <style>
            /* Tarjetas de saldo: mismo lenguaje que los KPI de Cartera. */
            /* ── Datos del Crédito: el escalón MÁS BAJO de la jerarquía ── */
            .dato-band {
                display: grid; grid-template-columns: repeat(12, 1fr);
                gap: 6px 10px; margin-bottom: 4px;
            }
            .dato { min-width: 0; padding: 3px 8px; border-left: 2px solid #e9ecef; }
            .dato-top {
                font-size: 10px; color: #868e96; text-transform: uppercase;
                letter-spacing: .3px; white-space: nowrap;
                overflow: hidden; text-overflow: ellipsis;
            }
            .dato-val {
                font-size: 13px; font-weight: 600; color: #343a40; line-height: 1.35;
                font-variant-numeric: tabular-nums;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            @media (max-width: 991.98px) { .dato-band { grid-template-columns: repeat(6, 1fr); } }
            @media (max-width: 575.98px) { .dato-band { grid-template-columns: repeat(4, 1fr); } }

            /* ── Atraso: escalón INTERMEDIO — semáforo en tinte suave ── */
            .atraso-band {
                display: grid; grid-template-columns: repeat(4, 1fr);
                gap: 8px; margin-bottom: 4px;
            }
            .atraso-kpi { border-radius: 8px; padding: 5px 10px; border: 1px solid; }
            .atraso-top {
                font-size: 10px; font-weight: 600; text-transform: uppercase;
                letter-spacing: .3px; opacity: .85;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .atraso-val {
                font-size: 1.05rem; font-weight: 700; line-height: 1.25;
                font-variant-numeric: tabular-nums;
            }
            .atraso-pie { font-size: 10px; opacity: .75; }
            .atraso-ambar { background: #fff8e1; border-color: #f3d98b; color: #8a6100; }
            .atraso-rojo  { background: #fdecea; border-color: #f2b8b5; color: #b02a24; }
            .atraso-verde { background: #eaf6ec; border-color: #b7dfc0; color: #1e7a34; }
            .atraso-gris  { background: #f8f9fa; border-color: #e9ecef; color: #6c757d; }
            /* El Total Mora sigue siendo editable: input sin marco dentro del chip
               (el theme fuerza 10px !important en input, de ahí el override). */
            .atraso-kpi input.atraso-input {
                width: 100%; border: 0; padding: 0; background: transparent;
                font-size: 1.05rem !important; font-weight: 700; color: inherit;
                height: auto; box-shadow: none;
            }
            .atraso-kpi input.atraso-input:focus { outline: 0; box-shadow: none; }
            .atraso-kpi input.atraso-input::placeholder { color: currentColor; opacity: .45; }
            .atraso-kpi input.atraso-input:disabled { color: inherit; opacity: .75; }
            @media (max-width: 767.98px) { .atraso-band { grid-template-columns: 1fr 1fr; } }

            /* Las cuatro tarjetas ocupan la fila completa y reparten el ancho
               por igual; a 2 columnas en tablet y a 1 en móvil. */
            .saldo-band { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
            @media (max-width: 991.98px) { .saldo-band { grid-template-columns: 1fr 1fr; } }
            .saldo-kpi {
                border: 1px solid #e9ecef;
                border-left: 4px solid var(--kpi, #adb5bd);
                border-radius: 8px;
                padding: 6px 10px;
                background: #fff;
            }
            .saldo-top {
                font-size: 10.5px; font-weight: 600; color: #6c757d;
                text-transform: uppercase; letter-spacing: .3px;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .saldo-val {
                font-size: 1.35rem; font-weight: 700; line-height: 1.2;
                color: var(--kpi, #212529);
                font-variant-numeric: tabular-nums;
            }
            .saldo-pie { font-size: 10.5px; color: #868e96; }
            /* El input vive DENTRO de la tarjeta: sin marco propio, con la
               misma tipografía de las cifras para que la banda se lea pareja. */
            .saldo-editable { background: #fffdf5; }
            /* Al pulsar "Usar … en Monto a Pagar" la tarjeta late en ámbar un
               par de segundos: el botón queda lejos y el usuario nuevo no veía
               que el campo ya se llenó. */
            .saldo-resaltado { animation: saldo-latido 1.3s ease-in-out 2; }
            @keyframes saldo-latido {
                0%   { box-shadow: 0 0 0 0 rgba(240, 173, 78, .9); background: #fff3cd; }
                60%  { box-shadow: 0 0 0 12px rgba(240, 173, 78, 0); }
                100% { box-shadow: 0 0 0 0 rgba(240, 173, 78, 0); background: #fffdf5; }
            }
            /* !important a propósito: el theme trae
               `.btn, input, select { font-size: 10px !important }`, que si no
               deja la cifra editable en 10px al lado de saldos de 21.6px. */
            .saldo-kpi input.saldo-input {
                width: 100%; border: 0; padding: 0; background: transparent;
                font-size: 1.35rem !important; font-weight: 700; line-height: 1.2;
                color: var(--kpi, #212529);
                font-variant-numeric: tabular-nums;
                height: auto; box-shadow: none;
            }
            .saldo-kpi input.saldo-input:focus { outline: 0; box-shadow: none; background: transparent; }
            .saldo-kpi input.saldo-input::placeholder { color: #e0c48a; font-weight: 600; }
            /* 23/10: en el lugar de la fecha, los botones que arman el Monto a Pagar. */
            .saldo-acciones { display: flex; flex-direction: column; justify-content: center; gap: 6px; }
            .saldo-acciones .btn { width: 100%; }
            .cancelacion-cuenta td { padding: 4px 6px; }
            /* El tema fuerza 10px en .btn e input: la cuota elegida es la cifra principal del modal. */
            .cancelacion-paso .btn, .cancelacion-paso input { font-size: .95rem !important; }
            .cancelacion-cuenta .cancelacion-total td { font-weight: 700; font-size: 1.05rem; color: #dc3545; border-top: 2px solid #dc3545; border-bottom: 0; }
            /* En móvil una debajo de la otra, sin apretar las cifras. */
            @media (max-width: 575.98px) { .saldo-band { grid-template-columns: 1fr; } }
        </style>

        {{-- ═══ Modal: pagar por cuotas (selección FIFO + mora editable por fila) ═══ --}}
        <div class="modal fade" id="moraCuotasModal" tabindex="-1" aria-hidden="true" wire:ignore.self
             x-data="{ modal: null }"
             x-init="modal = bootstrap.Modal.getOrCreateInstance($el)"
             x-on:mora-cuotas-open.window="modal.show()"
             x-on:mora-cuotas-close.window="modal.hide()">
            <div class="modal-dialog modal-dialog-centered" style="max-width:660px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0">
                            <i class="ti ti-list-check f-s-14"></i>
                            Pagar por cuotas
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body py-2">
                        @if(count($moraCuotas) === 0)
                            <p class="text-muted mb-0 py-2 text-center">No hay cuotas pendientes.</p>
                        @else
                            <div style="max-height: 320px; overflow:auto;">
                                <table class="table table-sm table-striped mb-0 align-middle">
                                    <thead style="position: sticky; top: 0; background: var(--bs-body-bg); z-index: 1;">
                                        <tr>
                                            <th style="width:34px;"></th>
                                            <th class="text-center">Cuota</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-center">Días</th>
                                            <th class="text-end">Mora calc.</th>
                                            <th class="text-end" style="width:110px;">Mora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($moraCuotas as $i => $fila)
                                            <tr class="{{ $fila['sel'] ? '' : 'opacity-50' }}" style="cursor:pointer;"
                                                wire:key="mora-cuota-{{ $fila['num'] }}">
                                                <td class="text-center">
                                                    {{-- wire:model (no wire:click): el checkbox debe ser CONTROLADO
                                                         por Livewire — con wire:click el navegador lo marca por su
                                                         cuenta y queda desfasado del estado real cuando el servidor
                                                         corrige la selección (regla del prefijo). --}}
                                                    <input type="checkbox" class="form-check-input"
                                                           wire:model.live="moraCuotas.{{ $i }}.sel"
                                                           title="La selección no puede saltarse cuotas: el pago se imputa a la más antigua primero">
                                                </td>
                                                <td class="text-center" wire:click="toggleCuota({{ $i }})">{{ $fila['num'] }}</td>
                                                <td wire:click="toggleCuota({{ $i }})">{{ $fila['venc'] }}</td>
                                                <td class="text-end" wire:click="toggleCuota({{ $i }})">{{ number_format($fila['saldo'], 2) }}</td>
                                                <td class="text-center {{ $fila['dias'] > 0 ? 'text-danger fw-semibold' : '' }}" wire:click="toggleCuota({{ $i }})">
                                                    {{ $fila['dias'] > 0 ? $fila['dias'] : '—' }}
                                                </td>
                                                <td class="text-end" wire:click="toggleCuota({{ $i }})">{{ number_format($fila['calc'], 2) }}</td>
                                                <td>
                                                    @if($this->canEditMora())
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control form-control-sm text-end input-rojo"
                                                               wire:model.live.debounce.400ms="moraCuotas.{{ $i }}.valor"
                                                               @disabled(! $fila['sel'])>
                                                    @else
                                                        <span class="d-block text-end">{{ number_format(is_numeric($fila['valor']) ? (float) $fila['valor'] : 0, 2) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        @php
                                            $selRows = collect($moraCuotas)->where('sel', true);
                                            $selMonto = $selRows->sum('saldo');
                                            $selMora = $selRows->sum(fn ($f) => is_numeric($f['valor'] ?? null) ? max(0, (float) $f['valor']) : 0);
                                        @endphp
                                        <tr class="fw-semibold">
                                            <td colspan="3" class="text-end">Seleccionado ({{ $selRows->count() }})</td>
                                            <td class="text-end">{{ number_format($selMonto, 2) }}</td>
                                            <td colspan="2" class="text-end">Mora</td>
                                            <td class="text-end text-danger">{{ number_format($selMora, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p class="text-muted small mb-0 mt-2">
                                Marca las cuotas a pagar: la suma de sus saldos arma el Monto a Pagar.
                                El pago se imputa a la cuota más antigua primero, así que no se puede
                                saltar cuotas.
                                @if($this->canEditMora())
                                    La mora editada por fila suma al Total Mora (si difiere de la
                                    calculada, pedirá motivo).
                                @else
                                    La mora del cobro será la calculada por el sistema.
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="modal-footer justify-content-between py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        @if(count($moraCuotas) > 0)
                            <button type="button" class="btn btn-sm btn-primary" wire:click="aplicarCuotas">
                                <i class="ti ti-check f-s-14"></i>
                                Aplicar al cobro
                            </button>
                        @endif
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
        {{-- 10/10 (Antony): en escritorio es una VENTANA FLOTANTE (modalFlotante,
             assets/js/ventanas-flotantes.js): sin fondo, la pantalla de cobro sigue
             viva debajo y se arrastra desde la cabecera; recuerda dónde la dejaste. --}}
        <div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true" wire:ignore.self
             x-data="modalFlotante('cobro')"
             x-on:ticket-confirm.window="modal.show()"
             x-on:ticket-close.window="modal.hide()">
            {{-- 22/10: wire:ignore.self en el diálogo: Livewire actualiza lo de adentro pero no
                 toca sus atributos. Sin esto, al responder Sí/No el morph reponía el style del
                 servidor y borraba el left/top de la ventana: saltaba a la esquina superior
                 izquierda (pasaba en prod desde el 10/10). --}}
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px;" x-ref="dialogo" wire:ignore.self>
                <div class="modal-content">
                    <div class="modal-header py-2"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
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
                                    <div class="tp-row tp-abajo"><span>Cliente:</span><span>{{ $preview['cliente'] }}</span></div>
                                @endif
                                @if($preview['documento'])
                                    <div class="tp-row"><span>Doc:</span><span>{{ $preview['documento'] }}</span></div>
                                @endif
                                <div class="tp-row"><span>Credito:</span><span>#{{ $preview['credit_id'] }}</span></div>

                                <div class="tp-sep"></div>

                                @if($preview['cuotas'])
                                    <div class="tp-row tp-wrap"><span>Cuotas:</span><span>{{ \App\Support\RangoCuotas::texto($preview['cuotas']) }}</span></div>
                                @endif
                                {{-- 16/10 (Antony): mismo desglose por cuota que el ticket: (amortizada) solo si no queda
                                     completa y debajo "Cuota n (Saldo)" con lo que le falta. --}}
                                @if($preview['detalle_cuotas_visible'] ?? false)
                                    @foreach($preview['detalle_cuotas'] as $dc)
                                        <div class="tp-row"><span>&nbsp;{{ \App\Services\Printing\TicketPrinter::rotuloCuota($dc) }}:</span><span>{{ number_format($dc['monto'], 2) }}</span></div>
                                        @if($dc['parcial'])
                                            <div class="tp-row"><span>&nbsp;Cuota {{ $dc['num'] }} (Saldo):</span><span>{{ number_format($dc['saldo'], 2) }}</span></div>
                                        @endif
                                    @endforeach
                                    {{-- 23/10 (Antony): con "Calcular cancelación" solo van una por una las cuotas hasta la elegida. --}}
                                    @if(! empty($preview['resto_cuotas']))
                                        <div class="tp-row"><span>&nbsp;{{ $preview['resto_cuotas']['rotulo'] }}</span><span>{{ number_format($preview['resto_cuotas']['monto'], 2) }}</span></div>
                                    @endif
                                @endif
                                {{-- 23/10 (Antony): igual que en el recibo, hasta qué cuota se cobra el interés y lo exonerado. --}}
                                @if(! empty($preview['recibo_cancelacion']))
                                    <div class="tp-nota">{{ $preview['recibo_cancelacion']['titulo'] }}</div>
                                    @if($preview['recibo_cancelacion']['exonera'] !== null)
                                        <div class="tp-nota">{{ $preview['recibo_cancelacion']['exonera'] }}</div>
                                        <div class="tp-row"><span>Interés exonerado:</span><span>{{ number_format($preview['recibo_cancelacion']['exonerado'], 2) }}</span></div>
                                    @endif
                                @endif
                                <div class="tp-row"><span>Capital:</span><span>{{ number_format($preview['capital'], 2) }}</span></div>
                                <div class="tp-row"><span>Interes:</span><span>{{ number_format($preview['interes'], 2) }}</span></div>
                                {{-- 16/10 (Antony): sin línea "Excedente" en el voucher (va dentro del TOTAL). --}}
                                @if($preview['mora'] > 0.001)
                                    <div class="tp-row"><span>Mora{{ $rotuloMora !== '' ? ' '.$rotuloMora : '' }}:</span><span>{{ number_format($preview['mora'], 2) }}</span></div>
                                @endif

                                <div class="tp-sep"></div>
                                <div class="tp-row tp-total"><span>TOTAL</span><span>S/ {{ number_format($preview['total'], 2) }}</span></div>
                                <div class="tp-sep"></div>
                                {{-- Con el aviso de cancelación abierto, este saldo es el de
                                     "No, dejarlo vigente": si cancela, se condona. --}}
                                <div class="tp-row"><span>{{ ($preview['cubre_total'] ?? false) && ! $preview['cancela'] ? 'Saldo si no cancela:' : 'Saldo restante:' }}</span><span>S/ {{ number_format($preview['saldo'], 2) }}</span></div>
                            </div>

                            @if($preview['cancela'])
                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small text-center" style="color:#000;">
                                    <i class="ti ti-alert-triangle"></i> Este cobro <strong>cancela</strong> el crédito.
                                    @if(! empty($preview['cancelacion_hasta']))
                                        <div class="mt-1">Con interés hasta la cuota <strong>{{ $preview['cancelacion_hasta']['cuota'] }}</strong>.</div>
                                    @endif
                                    @if(($preview['monto_tecleado'] ?? null) !== null)
                                        {{-- Modo estricto (09/09): el excedente NO se cobra como interés.
                                             Con interés completo (30/09) el ajuste es hacia arriba: el
                                             mínimo es el cronograma entero. --}}
                                        <div class="mt-1">
                                            {{ ($preview['hasta_ultima_cuota'] ?? false) ? 'Con interés completo se cobra el cronograma entero:' : 'Solo se cobra lo necesario para cancelar hoy:' }}
                                            <strong>S/ {{ number_format($preview['monto'], 2) }}</strong>
                                            (tecleaste S/ {{ number_format($preview['monto_tecleado'], 2) }}).
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if(($preview['condonada_vigente'] ?? 0) > 0.001 || ($preview['condonada_acum'] ?? 0) > 0.001)
                        <div class="alert alert-info d-flex align-items-start gap-2 py-2 mx-3 mt-2 mb-0" style="font-size: 12px;">
                            <i class="ti ti-eraser f-s-16 mt-1"></i>
                            <div>
                                <b>Exoneración al cancelar:</b>
                                @if(($preview['condonada_vigente'] ?? 0) > 0.001)
                                    Mora S/ {{ number_format($preview['condonada_vigente'], 2) }}@if(($preview['condonada_acum'] ?? 0) > 0.001) ·@endif
                                @endif
                                @if(($preview['condonada_acum'] ?? 0) > 0.001)
                                    Mora acumulada S/ {{ number_format($preview['condonada_acum'], 2) }}
                                @endif
                                <br>Motivo: <em>{{ $preview['condonar_motivo'] }}</em>
                            </div>
                        </div>
                    @endif

                    @if($preview['reserva_mora'])
                                <div class="alert alert-info py-1 px-2 mt-2 mb-0 small text-center">
                                    Mora reservada: no se cobra ahora, queda acumulada.
                                </div>
                            @endif
                            @if($preview['mora_ajustada'] ?? false)
                                @php $pd = $preview['mora_ajuste_final'] - $preview['mora_calculada']; @endphp
                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small" style="color:#000;">
                                    <i class="ti ti-alert-triangle"></i>
                                    <strong>Mora ajustada a mano.</strong>
                                    Calculada S/ {{ number_format($preview['mora_calculada'], 2) }} →
                                    se cobra S/ {{ number_format($preview['mora_ajuste_final'], 2) }}
                                    ({{ $pd < 0 ? '−' : '+' }}S/ {{ number_format(abs($pd), 2) }}).<br>
                                    Motivo: <em>{{ $preview['mora_motivo'] }}</em>
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
                        <div class="mx-3 mt-3 mb-2 p-2 rounded" style="background:#fff4e5; border:1px solid #ffb74d;">
                            <div class="fw-bold mb-1" style="color:#b26a00;">
                                <i class="ti ti-alert-triangle"></i> Este monto alcanza para CANCELAR el crédito hoy
                            </div>
                            @php
                                // Veredicto por mora: se actualiza en vivo (el preview se
                                // reconstruye al elegir Sí/No) y dice el DESTINO real, no
                                // un "pendiente" ambiguo.
                                $vVig = (float) ($preview['mora_pendiente'] ?? 0);
                                $vCobra = (float) ($preview['mora_cobrar'] ?? 0);
                                $vCondVig = (float) ($preview['condonada_vigente'] ?? 0);
                                $vAcumCobra = (float) ($preview['mora_acum_cobrar'] ?? 0);
                                $vCondAcum = (float) ($preview['condonada_acum'] ?? 0);
                            @endphp
                            @php
                                $sSaldo = (float) ($preview['saldo_pendiente'] ?? 0);
                                $sMontoSi = (float) ($preview['monto_si_cancela'] ?? ($preview['cancelar_cap_int'] ?? 0));
                                $sMontoNo = (float) ($preview['monto_si_vigente'] ?? ($preview['monto'] ?? 0));
                                $sCondonaSi = (float) ($preview['condona_si_cancela'] ?? 0);
                                $sQuedaNo = (float) ($preview['queda_si_vigente'] ?? 0);
                                // ¿El modo estricto recortaría (o ya recortó) el monto?
                                $sRecorta = ($sMontoNo - $sMontoSi) > 0.01;
                                $sUltima = (bool) ($preview['hasta_ultima_cuota'] ?? false);
                                $sIntCancelar = $sUltima
                                    ? round($sMontoSi - (float) ($preview['cap_pendiente_total'] ?? 0) - (float) ($preview['exc_venc'] ?? 0), 2)
                                    : (float) ($preview['int_cancelar'] ?? 0);
                                $sMorasCobra = (float) ($preview['mora_cobrar'] ?? 0) + (float) ($preview['mora_acum_cobrar'] ?? 0);
                                $sMorasExon = (float) ($preview['condonada_vigente'] ?? 0) + (float) ($preview['condonada_acum'] ?? 0);
                            @endphp
                            {{-- Resumen corto + "Ver detalles" (09/09): el bloque completo era
                                 un muro de texto justo donde el cajero tiene que decidir. El
                                 detalle se despliega aquí mismo (Alpine) y no en otro modal:
                                 anidar modales de Bootstrap deja backdrops huérfanos. --}}
                            <div x-data="{ det: false }">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="small" style="color:#000;">
                                        <div><b>Si cancela:</b> S/ {{ number_format($sMontoSi, 2) }}{{ $sRecorta ? ' (de S/ '.number_format($sMontoNo, 2).' tecleados)' : '' }}</div>
                                        <div><b>Si lo deja vigente:</b> S/ {{ number_format($sMontoNo, 2) }}{{ $sQuedaNo > 0.001 ? ' · quedan S/ '.number_format($sQuedaNo, 2) : ' · queda pagado' }}</div>
                                        @if($sMorasCobra > 0.001)
                                            <div style="color:#c0392b;">+ moras S/ {{ number_format($sMorasCobra, 2) }} (ya en el TOTAL)</div>
                                        @elseif($sMorasExon > 0.001)
                                            <div style="color:#6b7280;">Las moras se exoneran</div>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link p-0 text-nowrap" style="font-size:.72rem;"
                                            x-on:click="det = ! det">
                                        <i class="ti ti-info-circle"></i>
                                        <span x-text="det ? 'Ocultar' : 'Ver detalles'">Ver detalles</span>
                                    </button>
                                </div>

                                <div x-show="det" style="display:none;" class="mt-2 pt-2">
                                    @if(! empty($preview['cancelacion_hasta']))
                                        @php $sCuenta = $preview['cancelacion_hasta']; @endphp
                                        <div class="small mb-1" style="color:#000; border-top:1px dashed #ffb74d; padding-top:6px;">
                                            Interés hasta la cuota {{ $sCuenta['cuota'] }} (S/ {{ number_format($sCuenta['interes_cuota'], 2) }} × {{ $sCuenta['cuota'] }})
                                            <b>S/ {{ number_format($sCuenta['interes'], 2) }}</b>
                                            + capital del crédito <b>S/ {{ number_format($sCuenta['capital'], 2) }}</b>
                                            − pagado <b>S/ {{ number_format($sCuenta['pagado'], 2) }}</b>
                                            = <b>S/ {{ number_format($sCuenta['total'], 2) }}</b>
                                        </div>
                                    @else
                                        <div class="small mb-1" style="color:#000; border-top:1px dashed #ffb74d; padding-top:6px;">
                                            Capital pendiente <b>S/ {{ number_format($preview['cap_pendiente_total'] ?? 0, 2) }}</b>
                                            + interés {{ $sUltima ? 'hasta la última cuota' : 'al '.$preview['fecha'] }}
                                            <b>S/ {{ number_format($sIntCancelar, 2) }}</b>
                                            @if(($preview['exc_venc'] ?? 0) > 0.001)
                                                + {{ $sUltima ? 'excedente de redondeo' : 'excedente vencido' }} <b>S/ {{ number_format($preview['exc_venc'], 2) }}</b>
                                            @endif
                                            = <b>S/ {{ number_format($sMontoSi, 2) }}</b>
                                        </div>
                                    @endif
                                    @if($sCondonaSi > 0.001 || $sQuedaNo > 0.001)
                                        <div class="small mb-1" style="color:#6b7280;">
                                            El saldo del cronograma es <b>S/ {{ number_format($sSaldo, 2) }}</b> porque incluye interés futuro.
                                            {{-- Texto condicional con ternarios, no con directivas: Blade
                                                 NO compila una directiva pegada al final de una palabra
                                                 (ej. "...pendientes" seguido de else) y la vista revienta. --}}
                                            <div class="mt-1">
                                                <b>Si cancela:</b> se cobran solo <b>S/ {{ number_format($sMontoSi, 2) }}</b>{{ $sRecorta ? ' (de los S/ '.number_format($sMontoNo, 2).' tecleados)' : '' }}{{ $sCondonaSi > 0.001 ? ' y se condonan S/ '.number_format($sCondonaSi, 2).(empty($preview['cancelacion_hasta']) ? ' de interés futuro' : ' de interés') : '' }}.
                                            </div>
                                            <div>
                                                <b>Si lo deja vigente:</b> se aplican <b>S/ {{ number_format($sMontoNo, 2) }}</b> a las cuotas{{ $sQuedaNo > 0.001 ? ' y quedan S/ '.number_format($sQuedaNo, 2).' pendientes' : ' y el cronograma queda pagado' }}.
                                            </div>
                                        </div>
                                    @else
                                        <div class="small mb-1" style="color:#6b7280;">Con este monto se paga el cronograma completo.</div>
                                    @endif
                                    @if($vCobra > 0.001 || $vVig > 0.001 || $vCondVig > 0.001)
                                        <div class="small mb-1">
                                            @if($vCobra > 0.001)
                                                <span style="color:#c0392b;">Mora: se cobra <b>S/ {{ number_format($vCobra, 2) }}</b>{{ ($preview['mora_ajustada'] ?? false) ? ' (ajustada a mano)' : '' }}</span>
                                            @elseif($preview['reserva_mora'] ?? false)
                                                <span style="color:#b8860b;">Mora: reservada — queda como deuda <b>S/ {{ number_format($vVig, 2) }}</b></span>
                                            @else
                                                <span style="color:#6b7280;">Mora: se exonera al cancelar <b style="text-decoration: line-through;">S/ {{ number_format($vCondVig > 0.001 ? $vCondVig : $vVig, 2) }}</b></span>
                                            @endif
                                        </div>
                                    @endif
                                    @if($vAcumCobra > 0.001 || $vCondAcum > 0.001)
                                        <div class="small mb-2">
                                            @if($vCondAcum > 0.001)
                                                <span style="color:#6b7280;">Mora acumulada: se exonera al cancelar <b style="text-decoration: line-through;">S/ {{ number_format($vCondAcum, 2) }}</b></span>
                                            @else
                                                <span style="color:#c0392b;">Mora acumulada: se cobra <b>S/ {{ number_format($vAcumCobra, 2) }}</b></span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
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
                            @php $sMontoCompleto = (float) ($preview['monto_interes_completo'] ?? 0); @endphp
                            @if(($preview['cubre_total'] ?? false) && $decisionTotal !== 'no' && ($sUltima || $sCondonaSi > 0.001))
                                {{-- Interés completo (30/09): antes del primer vencimiento el interés a
                                     la fecha es 0 y "solo lo necesario" condona el período entero; aquí
                                     el cajero puede cobrar el cronograma completo sin salir del modal.
                                     Marcarla antes de responder la pregunta vale como "Sí, cancelar". --}}
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="decInteresCompleto"
                                           wire:model.live="cancelUltimaCuota">
                                    <label class="form-check-label small fw-semibold" for="decInteresCompleto" style="color:#b8860b;">
                                        Cobrar el interés completo del cronograma
                                        {{ $sUltima
                                            ? '(total S/ '.number_format($sMontoSi, 2).')'
                                            : '(+ S/ '.number_format(max(0, $sMontoCompleto - $sMontoSi), 2).' → total S/ '.number_format($sMontoCompleto, 2).')' }}
                                    </label>
                                </div>
                            @endif
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
                            {{-- 16/10 (Antony): Cobrar del mismo negro que el botón Pagar. --}}
                            <button type="button" class="btn btn-sm btn-dark"
                                    x-on:click="$captura('pagar', false)"
                                    @disabled($faltaDecidir)
                                    wire:loading.attr="disabled" wire:target="pagar">
                                Cobrar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary"
                                    x-on:click="$captura('pagar', true)"
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
            /* La lista de cuotas SÍ se parte: con tramos sueltos (19-25,27,30-40)
               una sola línea se saldría del modal. */
            .ticket-preview .tp-wrap > span:last-child { white-space: normal; word-break: break-word; }
            /* 09/10 (Antony): el nombre del cliente arranca en la misma línea que el rótulo y,
               si no cabe, sigue debajo (alineado a la derecha), sin salirse del ticket. */
            .ticket-preview .tp-abajo > span:last-child { flex: 1 1 auto; min-width: 0; white-space: normal; overflow-wrap: anywhere; }
            .ticket-preview .tp-total { font-size: 13px; font-weight: bold; }
            /* 23/10: textos sueltos del recibo (cancelación): se parten dentro del ancho, nunca se salen. */
            .ticket-preview .tp-nota { white-space: normal; overflow-wrap: anywhere; }
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

            // 22/10 (Antony): cada pago guarda desde dónde se cobra. Se pide la ubicación
            // al abrir (para que el navegador pregunte de una vez) y OTRA VEZ al pulsar
            // "Pagar", fresca (timeout 8 s, como el legacy); con ella se llama a
            // confirmarPago; sin ella, se avisa y no se cobra. El servidor la exige igual
            // (validarCobro, config prestamos.ubicacion_obligatoria). Antes se pedía una
            // sola vez al cargar y el error se ignoraba en silencio: desde septiembre
            // ningún cobro traía coordenadas.
            const ubicacionObligatoria = @json((bool) config('prestamos.ubicacion_obligatoria'));
            const estadoUbicacion = document.getElementById('ubicacion-estado');
            const pintarUbicacion = (clase, html) => {
                if (! estadoUbicacion) return;
                estadoUbicacion.className = 'small align-self-center ' + clase;
                estadoUbicacion.innerHTML = html;
            };
            const mensajeUbicacion = (err) => {
                if (! err) return 'Este navegador no permite obtener la ubicación.';
                if (err.code === 1) return 'Permiso de ubicación denegado: permite la ubicación para este sitio (candado del navegador) y vuelve a intentar.';
                return 'No se pudo obtener la ubicación (señal o tiempo agotado): revisa que la ubicación del equipo esté activa y vuelve a intentar.';
            };
            window.pedirUbicacion = function (cb) {
                if (! navigator.geolocation) {
                    pintarUbicacion('text-danger', '<i class="ti ti-map-pin-off"></i> ' + mensajeUbicacion(null));
                    cb(false, null);
                    return;
                }
                pintarUbicacion('text-muted', '<i class="ti ti-loader"></i> Obteniendo ubicación…');
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        const lat = pos.coords.latitude.toFixed(7), lng = pos.coords.longitude.toFixed(7);
                        $wire.set('latitud', lat, false);
                        $wire.set('longitud', lng, false);
                        pintarUbicacion('text-success', '<i class="ti ti-map-pin"></i> Ubicación activa (' + lat + ', ' + lng + ')');
                        cb(true, null);
                    },
                    (err) => {
                        $wire.set('latitud', null, false);
                        $wire.set('longitud', null, false);
                        pintarUbicacion('text-danger', '<i class="ti ti-map-pin-off"></i> ' + mensajeUbicacion(err)
                            + ' <a href="#" onclick="pedirUbicacion(() => {}); return false;">Reintentar</a>');
                        cb(false, err);
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
                );
            };
            window.pagarConUbicacion = function () {
                if (! ubicacionObligatoria) {
                    // Opcional: se guarda si el navegador la da, pero no se espera.
                    pedirUbicacion(() => {});
                    $wire.confirmarPago();
                    return;
                }
                pedirUbicacion((ok, err) => {
                    if (ok) {
                        $wire.confirmarPago();
                    } else {
                        window.dispatchEvent(new CustomEvent('errorAlert', { detail: [{ message: mensajeUbicacion(err) }] }));
                    }
                });
            };
            pedirUbicacion(() => {});
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
                                <span>{{ $etqMoraSim }}</span>
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
                                <span>{{ $etqMoraSim }}</span>
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
                                $morasCancelar = round(($quitarMora ? 0 : $sim['mora']) + ($quitarMoraAcum ? 0 : $moraAcumTotal), 2);
                                // Excedente (cuota uniforme): solo el de cuotas vencidas;
                                // el de cuotas futuras se condona al cancelar. Con
                                // "Interés completo" se cobra el de todas (28/10, 29427).
                                $excCancelar = $cancelUltimaCuota ? $sim['exc_pendiente'] : $sim['exc_hoy'];
                                $capIntCancelar = round($sim['cap_pendiente_total'] + $intCancelar + $excCancelar, 2);
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
                            @if($excCancelar > 0)
                                <div class="d-flex justify-content-between small"><span>{{ $cancelUltimaCuota ? 'Excedente de redondeo (todas las cuotas)' : 'Excedente vencido' }}</span><span>{{ number_format($excCancelar, 2) }}</span></div>
                            @endif
                            <div class="d-flex justify-content-between small">
                                <span>{{ $etqMoraSim }}</span>
                                <span style="{{ $quitarMora ? $tachado : 'color:red;' }}">{{ number_format($sim['mora'], 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span>Mora acumulada de cuotas ya pagadas @if($moraAcumDias > 0)({{ $moraAcumDias }} {{ $moraAcumDias === 1 ? 'día' : 'días' }})@endif</span>
                                <span style="{{ $quitarMoraAcum ? $tachado : 'color:#b8860b;' }}">{{ number_format($moraAcumTotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>Total</span><span>{{ number_format($totalCancelarCard, 2) }}</span></div>

                            {{-- Disclaimer: adelanto vs la última cuota y descuento de interés --}}
                            @php
                                $descuentoInt = round(($sim['saldo_credito'] - $sim['cap_pendiente_total']) - $sim['int_cancelar'], 2);
                            @endphp
                            @if(!$cancelUltimaCuota && $sim['dias_adelanto'] > 0 && $descuentoInt > 0)
                                <div class="rounded p-2 mt-2 small" style="background:#e7f4ea; color:#1e7b34;">
                                    <i class="ti ti-discount-2 f-s-12"></i>
                                    @if($sim['cuotas_vencidas'] === 0 && $sim['int_cancelar'] <= 0.001)
                                        {{-- Antes del primer vencimiento no hay interés devengado (30/09):
                                             el "descuento" es el período entero, no los días de adelanto. --}}
                                        Aún no vence ninguna cuota: el sistema no cuenta interés devengado,
                                        así que cancelar hoy cuesta solo el capital y se condona
                                        <b>{{ number_format($descuentoInt, 2) }}</b> de interés.
                                        Para cobrarlo, marca <b>Interés completo</b>.
                                    @else
                                        Te adelantas <b>{{ $sim['dias_adelanto'] }} {{ $sim['dias_adelanto'] === 1 ? 'día' : 'días' }}</b>
                                        a la última cuota ({{ \Carbon\Carbon::parse($sim['ultima_venc'])->format('d/m/Y') }}):
                                        descuento de interés <b>{{ number_format($descuentoInt, 2) }}</b>
                                    @endif
                                </div>
                            @endif

                            {{-- Opciones de la cotización --}}
                            <div class="rounded p-2 mt-2 mb-2" style="background:#f2f4f7;">
                                {{-- Exoneración desglosada (26/08): cada mora con su switch (default ON),
                                     con el monto en la etiqueta y motivo obligatorio. --}}
                                @if($sim['mora'] > 0.005)
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="quitarMora" wire:model.live="quitarMora">
                                        <label class="form-check-label small fw-semibold" for="quitarMora">
                                            Exonerar mora (S/ {{ number_format($sim['mora'], 2) }})
                                        </label>
                                    </div>
                                @endif
                                @if($moraAcumTotal > 0.005)
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="quitarMoraAcum" wire:model.live="quitarMoraAcum">
                                        <label class="form-check-label small fw-semibold" for="quitarMoraAcum">
                                            Exonerar mora acumulada (S/ {{ number_format($moraAcumTotal, 2) }})
                                        </label>
                                    </div>
                                @endif
                                @if($quitarMora || $quitarMoraAcum)
                                    <input type="text" autocomplete="off" maxlength="255"
                                           class="form-control form-control-sm mb-1"
                                           wire:model.live.debounce.500ms="condonarMotivo"
                                           placeholder="Motivo de la exoneración (obligatorio)">
                                @endif
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="cancelUltimaCuota" wire:model.live="cancelUltimaCuota">
                                    <label class="form-check-label small fw-semibold" for="cancelUltimaCuota">
                                        Interés completo (cancelar hasta la última cuota)
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

        {{-- 23/10 (Antony): clic en "N abonos" de una cuota → este modal con el número de crédito, el cliente
             y la misma lista del title. Lo llena Alpine con el evento ver-abonos (sin ir al servidor);
             wire:ignore para que el morph de Livewire no lo toque. --}}
        {{-- Ventana movible (23/10, Antony): la misma de "Confirmar cobro" (modalFlotante), se arrastra por la cabecera. --}}
        <div class="modal fade" id="abonosModal" tabindex="-1" aria-hidden="true" wire:ignore
             x-data="Object.assign(modalFlotante('abonos'), abonosModal())" x-on:ver-abonos.window="abrir($event.detail)">
            <div class="modal-dialog modal-dialog-centered" style="max-width:360px;" x-ref="dialogo">
                <div class="modal-content">
                    <div class="modal-header py-2"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                        <h6 class="modal-title mb-0" x-text="'Abonos de la cuota ' + d.cuota"></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="fw-bold" x-text="'Crédito #' + d.credito"></div>
                        <div class="mb-2" x-text="d.cliente"></div>
                        <div class="fw-semibold">Amortizó:</div>
                        <template x-for="(linea, i) in d.abonos" :key="i">
                            <div x-text="linea"></div>
                        </template>
                    </div>
                    <div class="modal-footer py-2 justify-content-between">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-sm btn-primary" x-on:click="copiar()" title="Copiar para pegarlo en WhatsApp">
                            <i class="ti" :class="copiado ? 'ti-check' : 'ti-copy'"></i> <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
                        </button>
                    </div>
                </div>
            </div>
            <script>
                function abonosModal() {
                    return {
                        d: { credito: '', cliente: '', cuota: '', abonos: [] },
                        copiado: false,
                        abrir(detalle) {
                            this.d = detalle;
                            this.copiado = false;
                            this.modal.show(); // la instancia la crea modalFlotante (sin fondo oscuro en escritorio)
                        },
                        // Lo que se copia es lo mismo que muestra el cuerpo del modal, una línea cada cosa y sin líneas en blanco.
                        texto() {
                            return ['Crédito #' + this.d.credito, this.d.cliente, 'Amortizó:'].concat(this.d.abonos).join('\n');
                        },
                        copiar() {
                            const texto = this.texto();
                            const listo = () => { this.copiado = true; setTimeout(() => { this.copiado = false; }, 1800); };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(texto).then(listo, () => this.copiarViejo(texto, listo));
                            } else {
                                this.copiarViejo(texto, listo);
                            }
                        },
                        copiarViejo(texto, listo) {
                            const t = document.createElement('textarea');
                            t.value = texto; t.style.position = 'fixed'; t.style.opacity = '0';
                            document.body.appendChild(t); t.select();
                            try { document.execCommand('copy'); listo(); } catch (e) {}
                            document.body.removeChild(t);
                        },
                    };
                }
            </script>
        </div>

        {{-- ═══ Modal: Calcular pagos (23/10, Antony; antes "Calcular cancelación") ═══
             Pestaña "Cancelación": el cajero elige hasta qué cuota se cobra el interés y el total
             (interés por cuota × N + capital del crédito − monto pagado) va al Monto a Pagar con
             Cancelado encendido; luego sigue el cobro normal. La cuenta se rehace en el navegador
             mientras se elige la cuota; el monto final lo calcula el servidor en aplicarCancelacion().
             Pestaña "Pagos a una fecha" (30/10, Antony, crédito 29393): en un calendario con los
             vencimientos pintados se elige cualquier fecha y sale lo que el cliente debe pagar ese día
             (PagosAUnaFecha), con el texto para mandarle y la opción de llevarlo al Monto a Pagar.
             Ventana movible como las demás. --}}
        <div class="modal fade" id="cancelacionModal" tabindex="-1" aria-hidden="true" wire:ignore
             x-data="Object.assign(modalFlotante('cancelacion'), calcularCancelacion())"
             x-on:calcular-pagos.window="abrir()">
            <div class="modal-dialog modal-dialog-centered" style="max-width:720px;" x-ref="dialogo">
                <div class="modal-content">
                    <div class="modal-header py-2"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                        <h6 class="modal-title mb-0"><i class="ti ti-calculator f-s-14"></i> Calcular pagos</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <ul class="nav nav-tabs px-3 pt-2 small">
                        <li class="nav-item">
                            <a href="#" class="nav-link py-1" :class="{ 'active': tab === 'cancelacion' }" x-on:click.prevent="tab = 'cancelacion'">Cancelación</a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link py-1" :class="{ 'active': tab === 'fecha' }" x-on:click.prevent="verFecha()">Pagos a una fecha</a>
                        </li>
                    </ul>
                    <div class="modal-body" x-show="tab === 'cancelacion' && ! hayCancelacion">
                        <div class="alert alert-light border small mb-0" x-text="sinCancelacion"></div>
                    </div>
                    <div class="modal-body" x-show="tab === 'cancelacion' && hayCancelacion">
                      <div class="mx-auto" style="max-width:420px;">
                        <div class="small text-muted mb-2" x-text="'Crédito #' + p.credito + ' · ' + p.total_cuotas + ' cuotas · ' + p.pagadas + ' pagadas'"></div>

                        <label class="form-label fw-semibold mb-1" for="cancelacion-hasta">¿Hasta qué cuota paga los intereses?</label>
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm cancelacion-paso" style="width: 150px;">
                                <button type="button" class="btn btn-outline-secondary" x-on:click="mover(-1)" :disabled="n <= p.minimo" aria-label="Una cuota menos">−</button>
                                <input type="number" id="cancelacion-hasta" class="form-control text-center fw-bold"
                                       x-model.number="n" :min="p.minimo" :max="p.maximo" step="1"
                                       x-on:blur="n = limitar(n)" x-on:keydown.enter.prevent="usar()">
                                <button type="button" class="btn btn-outline-secondary" x-on:click="mover(1)" :disabled="n >= p.maximo" aria-label="Una cuota más">+</button>
                            </div>
                            <span class="small text-muted" x-text="valido() && vence() ? 'vence el ' + vence() : ''"></span>
                        </div>
                        <input type="range" class="form-range mt-2" :min="p.minimo" :max="p.maximo" step="1"
                               x-model.number="n" aria-label="Cuota hasta la que se cobra el interés">
                        <div class="d-flex justify-content-between small text-muted" style="margin-top:-4px;">
                            <span x-text="'desde la ' + p.minimo"></span>
                            <span x-text="'hasta la ' + p.maximo"></span>
                        </div>
                        <div class="d-flex gap-1 flex-wrap mt-1">
                            <button type="button" class="btn btn-sm btn-light border" x-show="vencidasEnRango()"
                                    x-on:click="n = limitar(p.vencidas)" x-text="'Hasta la última vencida (' + limitar(p.vencidas) + ')'"></button>
                            <button type="button" class="btn btn-sm btn-light border"
                                    x-on:click="n = p.maximo" x-text="'Todas (' + p.maximo + ')'"></button>
                        </div>

                        <table class="table table-sm mb-0 mt-3 cancelacion-cuenta">
                            <tbody>
                                <tr>
                                    <td class="text-start">Interés <span class="text-muted" x-text="soles(p.interes_cuota) + ' × ' + (valido() ? n : '…') + ' cuotas'"></span></td>
                                    <td class="text-end" x-text="valido() ? soles(interes()) : '—'"></td>
                                </tr>
                                <tr><td class="text-start">+ Capital del crédito</td><td class="text-end" x-text="soles(p.capital)"></td></tr>
                                <tr><td class="text-start">− Monto pagado</td><td class="text-end" x-text="soles(p.pagado)"></td></tr>
                                <tr class="cancelacion-total">
                                    <td class="text-start">Total para cancelar</td>
                                    <td class="text-end" x-text="valido() ? soles(total()) : '—'"></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="small text-muted mt-2">
                            <span x-show="valido() && n < p.maximo" x-text="'No se cobra el interés de las cuotas ' + (n + 1) + ' a ' + p.maximo + ': se descuentan ' + soles(descuento()) + ' del saldo.'"></span>
                            <span x-show="valido() && n >= p.maximo">Se cobra el interés de todas las cuotas.</span>
                        </div>

                        {{-- 28/10 (Antony): checks para agregar la mora y la mora acumulada, desmarcados por defecto.
                             Sin marcar se exoneran al cancelar (switches de la tarjeta "Cancelar crédito"). --}}
                        <div class="mt-2" x-show="p.mora > 0.005 || p.mora_acumulada > 0.005">
                            <div class="form-check mb-1" x-show="p.mora > 0.005">
                                <input class="form-check-input" type="checkbox" id="cancelacion-con-mora" x-model="conMora">
                                <label class="form-check-label small" for="cancelacion-con-mora">
                                    Agregar mora<span x-show="p.mora_rotulo" x-text="' (' + p.mora_rotulo + ')'"></span> <b x-text="soles(p.mora)"></b>
                                </label>
                            </div>
                            <div class="form-check mb-1" x-show="p.mora_acumulada > 0.005">
                                <input class="form-check-input" type="checkbox" id="cancelacion-con-mora-acumulada" x-model="conMoraAcumulada">
                                <label class="form-check-label small" for="cancelacion-con-mora-acumulada">
                                    Agregar mora acumulada (cuotas ya pagadas) <b x-text="soles(p.mora_acumulada)"></b>
                                </label>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1">
                                <span>Total a cobrar</span>
                                <span x-text="valido() ? soles(totalACobrar()) : '—'"></span>
                            </div>
                            {{-- 28/10 (Antony): aviso visible de que lo no marcado se exonera. --}}
                            <div class="rounded p-2 mt-2 small" style="background:#fff3cd; color:#664d03;" x-show="sinMarcar()">
                                <i class="ti ti-alert-triangle f-s-12"></i>
                                <span x-text="avisoExoneracion()"></span>
                            </div>
                            <div class="small text-muted mt-1" x-show="! sinMarcar()">La mora marcada se cobra aparte, como pago de mora.</div>
                        </div>
                        <div class="small text-danger mt-1" x-show="! valido()" x-text="'Elige una cuota entre la ' + p.minimo + ' y la ' + p.maximo + '.'"></div>
                      </div>
                    </div>

                    {{-- Pestaña "Pagos a una fecha" (30/10, Antony): cualquier fecha; el calendario pinta los
                         vencimientos para ubicarlos (vencidas, por vencer y pagadas). --}}
                    <div class="modal-body" x-show="tab === 'fecha'">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="calendario-pagos" x-ref="calendario"></div>
                                <div class="d-flex flex-wrap gap-2 small mt-2 calendario-pagos-leyenda">
                                    <span><i class="dp-muestra dp-vencida"></i> vencida</span>
                                    <span><i class="dp-muestra dp-por-vencer"></i> por vencer</span>
                                    <span><i class="dp-muestra dp-pagada"></i> pagada</span>
                                </div>
                                <div class="small text-muted mt-1">Puedes elegir cualquier fecha; los colores solo marcan los vencimientos.</div>
                                <div class="d-flex gap-1 flex-wrap mt-2">
                                    <button type="button" class="btn btn-sm btn-light border" x-on:click="elegirFecha(hoy)">Hoy</button>
                                    <button type="button" class="btn btn-sm btn-light border" x-show="proximoVencimiento()"
                                            x-on:click="elegirFecha(proximoVencimiento().fecha)"
                                            x-text="proximoVencimiento() ? 'Próximo vencimiento (' + fechaCorta(proximoVencimiento().fecha) + ')' : ''"></button>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="fw-semibold mb-1">
                                    Si paga el <span class="text-danger" x-text="r ? r.fecha : fechaCorta(fecha)"></span>
                                    <span class="spinner-border spinner-border-sm text-secondary ms-1" x-show="calculando"></span>
                                </div>
                                <template x-if="r && r.filas.length === 0">
                                    <div class="alert alert-light border small mb-0">No tiene cuotas por pagar hasta esa fecha.</div>
                                </template>
                                <template x-if="r && r.filas.length > 0">
                                    <div>
                                        <table class="table table-sm table-bordered mb-1 small pagos-fecha-tabla">
                                            <thead>
                                                <tr><th>Cuota</th><th>Vence</th><th class="text-end">Cuota</th><th class="text-end">Abonado</th><th>Estado</th><th class="text-end">Mora</th></tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="f in r.filas" :key="f.num">
                                                    <tr>
                                                        <td x-text="f.orden + '-' + f.num"></td>
                                                        <td x-text="fechaCorta(f.venc)"></td>
                                                        <td class="text-end" x-text="numero(f.cuota)"></td>
                                                        <td class="text-end" x-text="numero(f.pagado)"></td>
                                                        <td><span class="badge" :class="f.dias > 0 ? 'bg-danger' : 'bg-warning text-dark'" x-text="f.estado"></span></td>
                                                        <td class="text-end" x-text="f.dias > 0 ? numero(f.mora) + ' (' + f.dias + ' d)' : '—'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <div class="d-flex justify-content-between small"><span>Cuotas</span><span x-text="soles(r.saldo)"></span></div>
                                        <div class="d-flex justify-content-between small"><span>Mora</span><span x-text="soles(r.mora)"></span></div>
                                        <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1"><span>Total a pagar</span><span x-text="soles(r.total)"></span></div>
                                        <label class="form-label small text-muted mt-2 mb-1">Para enviar al cliente</label>
                                        <textarea class="form-control form-control-sm pagos-fecha-texto" rows="5" readonly x-ref="textoPagos" :value="r.texto"></textarea>
                                        <div class="small text-muted mt-1" x-show="fecha !== hoy">El cobro se registra con la fecha de hoy; la mora va calculada al <span x-text="r.fecha"></span>.</div>
                                        <div class="small text-muted mt-1" x-show="! puedeMora">Al llevarlo al cobro, el Total Mora lo pone el sistema (no tienes permiso para cambiarlo).</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer py-2 justify-content-between">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-sm btn-danger" x-show="tab === 'cancelacion' && hayCancelacion"
                                x-on:click="usar()" :disabled="! valido() || enviando">
                            <i class="ti ti-check"></i> Usar en Monto a Pagar
                        </button>
                        {{-- x-show en un div sin d-flex: el display:flex !important de Bootstrap le ganaba y los
                             botones de esta pestaña salían también en la de cancelación. --}}
                        <div x-show="tab === 'fecha'">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="copiarPagos()" :disabled="! r || r.filas.length === 0">
                                    <i class="ti ti-copy"></i> <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" x-on:click="usarFecha()" :disabled="! r || r.filas.length === 0 || enviando">
                                    <i class="ti ti-check"></i> Usar en Monto a Pagar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <style>
                .calendario-pagos .ui-datepicker { width: 100%; font-size: 12px; }
                .calendario-pagos td.dp-vencida a { background: #f8d7da !important; color: #842029 !important; border-color: #f1aeb5 !important; font-weight: 600; }
                .calendario-pagos td.dp-por-vencer a { background: #cfe2ff !important; color: #084298 !important; border-color: #9ec5fe !important; font-weight: 600; }
                .calendario-pagos td.dp-pagada a { background: #d1e7dd !important; color: #0f5132 !important; border-color: #a3cfbb !important; }
                .calendario-pagos td .ui-state-active { box-shadow: inset 0 0 0 2px #212529; }
                .calendario-pagos-leyenda .dp-muestra { display: inline-block; width: 11px; height: 11px; border-radius: 2px; vertical-align: -1px; border: 1px solid; }
                .calendario-pagos-leyenda .dp-vencida { background: #f8d7da; border-color: #f1aeb5; }
                .calendario-pagos-leyenda .dp-por-vencer { background: #cfe2ff; border-color: #9ec5fe; }
                .calendario-pagos-leyenda .dp-pagada { background: #d1e7dd; border-color: #a3cfbb; }
                /* El tema fuerza white-space: normal en las tablas: sin !important las celdas se partían ("500.0 / 0"). */
                .pagos-fecha-tabla th, .pagos-fecha-tabla td { padding: 2px 6px !important; white-space: nowrap !important; }
                .pagos-fecha-texto { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; white-space: pre; }
            </style>
            <script>
                function calcularCancelacion() {
                    return {
                        p: { credito: '', interes_cuota: 0, capital: 0, pagado: 0, total_cuotas: 0, pagadas: 0, minimo: 1, maximo: 1, vencidas: 0, saldo: 0, saldo_al_cancelar: 0, vencimientos: {}, mora: 0, mora_rotulo: '', mora_acumulada: 0 },
                        n: 1,
                        conMora: false,
                        conMoraAcumulada: false,
                        cargando: false,
                        enviando: false,
                        // 30/10: pestañas y "Pagos a una fecha".
                        tab: 'cancelacion',
                        hayCancelacion: true,
                        sinCancelacion: '',
                        vencs: [],
                        vencPorFecha: {},
                        hoy: '',
                        puedeMora: false,
                        fecha: '',
                        r: null,
                        calculando: false,
                        copiado: false,
                        abrir() {
                            if (this.cargando) return;
                            this.cargando = true;
                            this.$wire.abrirCalcularPagos().then((d) => {
                                this.cargando = false;
                                if (! d) return;
                                this.hayCancelacion = !! d.cancelacion;
                                this.sinCancelacion = d.sin_cancelacion || '';
                                if (d.cancelacion) {
                                    this.p = d.cancelacion;
                                    this.n = d.cancelacion.sugerida;
                                    // Desmarcados por defecto; si ya hay un cálculo aplicado, vuelven como quedaron.
                                    this.conMora = !! d.cancelacion.con_mora;
                                    this.conMoraAcumulada = !! d.cancelacion.con_mora_acumulada;
                                }
                                this.vencs = d.vencimientos;
                                this.vencPorFecha = {};
                                d.vencimientos.forEach((v) => {
                                    const x = this.vencPorFecha[v.fecha] || (this.vencPorFecha[v.fecha] = { nums: [], pagada: true });
                                    x.nums.push(v.num);
                                    x.pagada = x.pagada && v.pagada;
                                });
                                this.hoy = d.hoy;
                                this.puedeMora = !! d.puede_mora;
                                this.fecha = d.hoy;
                                this.r = null;
                                if (! this.hayCancelacion) this.tab = 'fecha';
                                this.modal.show(); // la instancia la crea modalFlotante (sin fondo oscuro en escritorio)
                                setTimeout(() => {
                                    if (this.tab === 'fecha') {
                                        this.verFecha();
                                    } else {
                                        // El número queda seleccionado: se escribe la cuota y Enter la usa.
                                        document.getElementById('cancelacion-hasta')?.select();
                                    }
                                }, 60);
                            }, () => { this.cargando = false; });
                        },
                        verFecha() {
                            this.tab = 'fecha';
                            this.prepararCalendario();
                            if (! this.r && this.fecha) this.elegirFecha(this.fecha);
                        },
                        prepararCalendario() {
                            const $cal = window.jQuery && jQuery(this.$refs.calendario);
                            if (! $cal || ! $cal.datepicker) return;
                            if (! $cal.hasClass('hasDatepicker')) {
                                $cal.datepicker({
                                    dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true, firstDay: 1,
                                    beforeShowDay: (d) => this.diaCalendario(d),
                                    onSelect: (texto) => this.elegirFecha(texto),
                                });
                            }
                            $cal.datepicker('setDate', this.fecha);
                            $cal.datepicker('refresh');
                        },
                        diaCalendario(d) {
                            const ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                            const v = this.vencPorFecha[ymd];
                            if (! v) return [true, '', ''];
                            const cuotas = (v.nums.length > 1 ? 'Cuotas ' : 'Cuota ') + v.nums.join(', ');
                            if (v.pagada) return [true, 'dp-pagada', cuotas + ' (pagada)'];
                            return ymd < this.hoy ? [true, 'dp-vencida', cuotas + ' vencida'] : [true, 'dp-por-vencer', cuotas + ' por vencer'];
                        },
                        elegirFecha(f) {
                            this.fecha = f;
                            this.copiado = false;
                            const $cal = window.jQuery && jQuery(this.$refs.calendario);
                            if ($cal && $cal.hasClass('hasDatepicker')) $cal.datepicker('setDate', f);
                            this.calculando = true;
                            this.$wire.calcularPagosAl(f).then((r) => {
                                // Solo la última fecha pedida manda (clics seguidos en el calendario).
                                if (f !== this.fecha) return;
                                this.calculando = false;
                                this.r = r;
                            }, () => { this.calculando = false; });
                        },
                        proximoVencimiento() { return this.vencs.find((v) => ! v.pagada && v.fecha >= this.hoy) || null; },
                        fechaCorta(ymd) { return ymd ? ymd.split('-').reverse().join('/') : ''; },
                        numero(v) { return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                        copiarPagos() {
                            if (! this.r) return;
                            navigator.clipboard.writeText(this.r.texto).then(() => {
                                this.copiado = true;
                                setTimeout(() => this.copiado = false, 1800);
                            });
                        },
                        usarFecha() {
                            if (! this.r || this.enviando) return;
                            this.enviando = true;
                            this.$wire.usarPagosAl(this.fecha).then((ok) => {
                                this.enviando = false;
                                if (ok) this.modal.hide();
                            }, () => { this.enviando = false; });
                        },
                        limitar(v) {
                            v = Math.round(Number(v));
                            if (! Number.isFinite(v)) v = this.p.minimo;
                            return Math.min(this.p.maximo, Math.max(this.p.minimo, v));
                        },
                        mover(paso) { this.n = this.limitar(this.n + paso); },
                        valido() { return Number.isInteger(this.n) && this.n >= this.p.minimo && this.n <= this.p.maximo; },
                        vencidasEnRango() { return this.p.vencidas >= this.p.minimo && this.p.vencidas < this.p.maximo; },
                        redondear(v) { return Math.round(v * 100) / 100; },
                        interes() { return this.redondear(this.p.interes_cuota * this.n); },
                        // Igual que CancelacionHastaCuota::monto(): nunca más que el saldo que queda al cancelar.
                        total() { return this.redondear(Math.min(this.interes() + this.p.capital - this.p.pagado, this.p.saldo_al_cancelar)); },
                        descuento() { return Math.max(0, this.redondear(this.p.saldo - this.total())); },
                        totalACobrar() {
                            return this.redondear(this.total() + (this.conMora ? this.p.mora : 0) + (this.conMoraAcumulada ? this.p.mora_acumulada : 0));
                        },
                        sinMarcar() {
                            return (this.p.mora > 0.005 && ! this.conMora) || (this.p.mora_acumulada > 0.005 && ! this.conMoraAcumulada);
                        },
                        avisoExoneracion() {
                            const partes = [];
                            if (this.p.mora > 0.005 && ! this.conMora) {
                                partes.push('la mora (' + this.soles(this.p.mora) + ')');
                            }
                            if (this.p.mora_acumulada > 0.005 && ! this.conMoraAcumulada) {
                                partes.push('la mora acumulada (' + this.soles(this.p.mora_acumulada) + ')');
                            }
                            const varias = partes.length > 1;
                            return 'Ojo: al cancelar se ' + (varias ? 'exoneran ' : 'exonera ') + partes.join(' y ')
                                + '. No se ' + (varias ? 'cobran' : 'cobra') + ' y queda registrado con el motivo «Mora exonerada». Para '
                                + (varias ? 'cobrarlas, márcalas' : 'cobrarla, márcala') + ' arriba.';
                        },
                        vence() { return this.p.vencimientos[this.n] || ''; },
                        soles(v) { return 'S/ ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                        // Ojo: no llamarlo aplicar(); ese nombre es de modalFlotante (coloca la ventana al abrir).
                        usar() {
                            if (! this.valido() || this.enviando) return;
                            this.enviando = true;
                            this.$wire.aplicarCancelacion(this.n, this.conMora, this.conMoraAcumulada).then((ok) => {
                                this.enviando = false;
                                if (ok) this.modal.hide();
                            }, () => { this.enviando = false; });
                        },
                    };
                }
            </script>
        </div>

        {{-- Cronograma de cuotas (homologado con /credits/{id}) --}}
        <div class="card shadow-sm mt-2">
            {{-- 17/10: los abonos de las cuotas YA PAGADAS llegan plegados (son historia; el cronograma
                 del crédito los tiene todos) y los de la cuota en juego, abiertos. Un solo interruptor
                 Alpine (sin round trip) los muestra; vive en el card-body, que Livewire no reemplaza al
                 morfear la tabla tras cobrar, así que queda como lo dejó el cajero. --}}
            <div class="card-body pb-2" x-data="{ verTodo: false }">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="mb-0">CRONOGRAMA DE CUOTAS</h6>
                    <div class="d-flex align-items-center gap-3">
                        @if($abonos !== [])
                            <div class="form-check form-switch mb-0 small" title="Las cuotas ya pagadas llegan con sus abonos plegados; la cuota que se está cobrando siempre los muestra">
                                <input class="form-check-input" type="checkbox" id="ver-abonos-pagadas" x-model="verTodo">
                                <label class="form-check-label" for="ver-abonos-pagadas">Ver abonos de cuotas pagadas</label>
                            </div>
                        @endif
                        <x-scroll-bottom-btn scrollable="#tabla-cronograma" />
                    </div>
                </div>
                @php $tieneExc = $credit->installments->sum('importe_excedente') > 0; @endphp
                <style>
                    /* 17/10: la cebra va por clase (fila-par) y no por table-striped, porque las filas de
                       abono intercaladas rompían el nth-of-type; mismo color que el tema (_tables.scss). */
                    #tabla-cronograma tr.fila-par > * { background-color: #d0cdcd; color: rgba(var(--dark), 1); }
                    /* Abonos de cuotas ya pagadas: plegados hasta que se activa el interruptor. */
                    #tabla-cronograma.historial-plegado tr.abono-pagada { display: none; }
                </style>
                <div id="tabla-cronograma" class="table-responsive tableFixHead historial-plegado"
                     :class="{ 'historial-plegado': ! verTodo }" style="max-height: 500px; overflow:auto;">
                    <table class="table table-bordered table-hover">
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
                            <th>Mora</th>
                            <th>Estado</th>
                            <th>Fecha Pago</th>
                            <th class="text-center">Rec.</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $nVencida = 0; $nPendiente = 0; @endphp
                        @foreach($credit->installments as $inst)
                            @php
                                $saldo = $inst->saldoPendiente();
                                $vencida = !$inst->pagado && $inst->fecha_vencimiento?->isPast();
                                $pendiente = !$inst->pagado && !$vencida;
                                if ($vencida) $nVencida++;
                                if ($pendiente) $nPendiente++;
                            @endphp
                            <tr class="{{ $vencida ? 'table-danger' : ($loop->even ? 'fila-par' : '') }}" wire:key="cuota-{{ $inst->id }}">
                                {{-- Numeración del legacy: vencidas y pendientes llevan su
                                     propio contador delante del número de cuota (1-16, 2-17...)
                                     así se ve de un vistazo cuántas hay de cada una --}}
                                <td>@if($vencida){{ $nVencida }}-@elseif($pendiente){{ $nPendiente }}-@endif{{ $inst->num_cuota }}</td>
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
                                {{-- Mora unificada: pagada (negro) y exonerada (rojo).
                                     17/09: la celda muestra la parte de ESTA cuota en el
                                     reparto del cobro (MoraPagada::mostradaPorCuota). Antes
                                     pintaba importe_mora, que carga toda la mora del cobro en
                                     la primera cuota y dejaba en blanco a las demás que la
                                     generaron; el reparto solo se veía en el tooltip. El total
                                     del crédito no cambia (fila de Totales). --}}
                                @php
                                    $me = $moraExon[$inst->num_cuota] ?? null;
                                    $mc = $moraCelda[$inst->id] ?? null;
                                    $mPag = $mc['monto'] ?? 0;
                                @endphp
                                <td class="text-end" style="white-space:nowrap;">
                                    @if($mPag > 0)
                                        <span data-bs-toggle="tooltip" data-bs-html="true" title="{{ $mc['detalle'] }}" style="cursor:help;">{{ number_format($mPag, 2) }}</span>
                                    @endif
                                    @if($me)
                                        @if($mPag > 0)<br>@endif
                                        <span class="text-danger" data-bs-toggle="tooltip" title="Mora exonerada" style="cursor:help;">{{ number_format($me['monto'], 2) }} - D. {{ $me['dias'] }}</span>
                                    @endif
                                    @if($mPag <= 0 && !$me)
                                        0.00
                                    @endif
                                </td>
                                <td>
                                    @if($inst->pagado)
                                        <span class="badge bg-success">Pagado</span>
                                    @elseif($vencida)
                                        <span class="badge bg-danger">Vencida</span>
                                    @else
                                        <span class="badge bg-warning">Pendiente</span>
                                    @endif
                                    {{-- 16/10 (Antony): la cuota con pago parcial se marca Amortizada además de su estado. --}}
                                    @if(! $inst->pagado && ($inst->importe_aplicado + $inst->interes_aplicado + $inst->excedente_aplicado) > 0.001)
                                        <span class="badge bg-info text-dark" title="Cuota con pago parcial: aún le falta">Amortizada</span>
                                    @endif
                                    {{-- 17/10: aviso de que la cuota tiene desglose (los de cuotas pagadas llegan plegados);
                                         el title lista cada abono. --}}
                                    @if(isset($abonos[$inst->id]))
                                        @php
                                            $nAb = count($abonos[$inst->id]);
                                            $lineasAb = array_map(fn ($a) => ($a['fecha'] !== '' ? \Carbon\Carbon::parse($a['fecha'])->format('d/m/Y') : '—').' · S/ '.number_format($a['monto'], 2), $abonos[$inst->id]);
                                            $listaAb = implode("\n", $lineasAb);
                                        @endphp
                                        {{-- 23/10 (Antony): el clic abre el modal #abonosModal con el crédito, el cliente y la misma lista --}}
                                        <span class="badge bg-light text-dark border ms-1" role="button" style="cursor:pointer;" title="{{ $listaAb }}"
                                              x-on:click="$dispatch('ver-abonos', @js(['credito' => $credit->id, 'cliente' => $credit->client?->fullName() ?? '', 'cuota' => $inst->num_cuota, 'abonos' => $lineasAb]))">{{ $nAb }} {{ $nAb === 1 ? 'abono' : 'abonos' }}</span>
                                    @endif
                                </td>
                                <td>{{ $inst->pagado ? $inst->fecha_pago?->format('d/m/Y') : '' }}</td>
                                <td class="text-center" style="white-space:nowrap;">
                                    {{-- 17/10 (Antony): con varios abonos el recibo va en cada fila del desglose;
                                         con un solo pago, aquí, en la fila de la cuota. --}}
                                    @php $rec = isset($abonos[$inst->id]) ? null : ($recibos[$inst->id] ?? null); @endphp
                                    @if($rec)
                                        {{-- Único botón (el de WhatsApp se quitó 14/08): abre el
                                             recibo en un modal sobre esta misma ventana, donde
                                             está el "Copiar imagen" --}}
                                        <button type="button" class="btn btn-sm btn-secondary py-0 px-1"
                                                title="Ver recibo" onclick="abrirRecibo(@js($rec['ver']), @js('Recibo · cuota '.$inst->num_cuota))">
                                            <i class="ti ti-eye"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            {{-- 17/10 (Antony): cuota pagada o amortizada en varios abonos → un renglón por
                                 abono, igual que en el cronograma del crédito (partial compartido y
                                 App\Support\AbonosPorCuota): numeración n.k, nota del cobro partido, capital,
                                 interés, monto, el saldo que iba quedando, fecha y hora, y su recibo. --}}
                            @isset($abonos[$inst->id])
                                @include('livewire.payments.partials._filas-abonos', [
                                    'abonosCuota' => $abonos[$inst->id],
                                    'n' => $inst->num_cuota,
                                    'insId' => $inst->id,
                                    'tieneExc' => $tieneExc,
                                    'formatoFecha' => 'd/m/Y',
                                    'cuotaPagada' => (bool) $inst->pagado,
                                ])
                            @endisset
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
                            $tMoraPag = $credit->installments->sum(fn ($i) => $i->importe_mora + $i->mora_interes);
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
                                    // Desglose por cuota de toda la mora pagada del crédito
                                    $itemsMoraTotal = $credit->installments
                                        ->filter(fn ($i) => $i->importe_mora + $i->mora_interes > 0)
                                        ->flatMap(fn ($i) => $moraPagadaCuotas[$i->id]
                                            ?? [['num' => $i->num_cuota, 'monto' => $i->importe_mora + $i->mora_interes, 'dias' => null]])
                                        ->groupBy('num')
                                        ->map(fn ($g, $num) => ['num' => (int) $num, 'monto' => $g->sum('monto'), 'dias' => $g->sum('dias') ?: null])
                                        ->sortBy('num')->values();
                                    $diasMoraTotal = $itemsMoraTotal->sum('dias');
                                    $tipMoraPagTotal = 'Mora pagada de '.$itemsMoraTotal->count().' cuota(s):<br>'
                                        .$itemsMoraTotal->take(15)->map(fn ($it) =>
                                            'Cuota '.$it['num'].': '.number_format($it['monto'], 2)
                                            .($it['dias'] ? ' - D. '.$it['dias'] : ''))->implode('<br>')
                                        .($itemsMoraTotal->count() > 15 ? '<br>…' : '')
                                        .'<br>Total: '.number_format($tMoraPag, 2)
                                        .($diasMoraTotal ? ' - D. '.$diasMoraTotal : '');
                                @endphp
                                {{-- Total Mora: pagada y exonerada juntas (la exonerada es
                                     informativa, no suma a los demás totales) --}}
                                <td class="text-end" style="white-space:nowrap;">
                                    @if($tMoraPag > 0)
                                        <span data-bs-toggle="tooltip" data-bs-html="true" title="{{ $tipMoraPagTotal }}" style="cursor:help;">{{ number_format($tMoraPag, 2) }}</span>
                                    @endif
                                    @if($tExon > 0)
                                        @if($tMoraPag > 0)<br>@endif
                                        <span class="text-danger" data-bs-toggle="tooltip" title="Mora exonerada" style="cursor:help;">{{ number_format($tExon, 2) }}@if($tExonDias > 0) - D. {{ $tExonDias }}@endif</span>
                                    @endif
                                    @if($tMoraPag <= 0 && $tExon <= 0)
                                        0.00
                                    @endif
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Retraso del legacy (saldoPendi): lo impago SOLO de las
                                 cuotas vencidas, alineado bajo la columna Saldo --}}
                            @php
                                $vencidasRetraso = $credit->installments
                                    ->filter(fn ($i) => !$i->pagado && $i->fecha_vencimiento?->isPast());
                                $tRetraso = $vencidasRetraso->sum(fn ($i) => $i->saldoPendiente());
                                $nVencRetraso = $vencidasRetraso->count();
                                $montoCuotaVenc = ($vencidasRetraso->first()?->importe_cuota ?? 0)
                                    + ($vencidasRetraso->first()?->importe_interes ?? 0);
                                // Si hay pagos parciales el producto no cierra: el tooltip lo dice tal cual.
                                $tipRetraso = abs($montoCuotaVenc * $nVencRetraso - $tRetraso) < 0.01
                                    ? 'Cuota S/ '.number_format($montoCuotaVenc, 2).' × '.$nVencRetraso.' cuota(s) vencida(s) = S/ '.number_format($tRetraso, 2)
                                    : $nVencRetraso.' cuota(s) vencida(s) con saldo pendiente = S/ '.number_format($tRetraso, 2);
                            @endphp
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end text-danger"
                                    data-bs-toggle="tooltip" title="{{ $tipRetraso }}" style="cursor:help;">Retraso</td>
                                <td class="text-end text-danger" data-bs-toggle="tooltip" title="{{ $tipRetraso }}" style="cursor:help;">{{ number_format($tRetraso, 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Filas homologadas con /credits/{id}/schedule: Total pagado
                                 + mora (solo si hay mora), Saldo y Capital pendiente total --}}
                            @php
                                $moraPagadaCaja = (float) \App\Models\Payment::where('credit_id', $credit->id)
                                    ->where('tipo', 'MORA')->sum('monto');
                                $tPagadoTotal = $tPagCap + $tPagInt + $tPagExc;
                                $capPendiente = round($tCap - $tPagCap, 2);
                            @endphp
                            @if($moraPagadaCaja > 0)
                                <tr class="fw-bold" style="background:#f0f0f0;">
                                    <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end">Total pagado + mora</td>
                                    <td class="text-end">{{ number_format($tPagadoTotal + $moraPagadaCaja, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endif
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end text-danger">Saldo</td>
                                <td class="text-end text-danger">{{ number_format($tSaldo, 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end text-danger">Capital pendiente total</td>
                                <td class="text-end text-danger">{{ number_format($capPendiente, 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Interés pendiente del cronograma: interés total − pagado --}}
                            @php
                                $tIntPend = round($tInt - $tPagInt, 2);
                                $tipIntPend = 'Interés '.number_format($tInt, 2).' − pagado '.number_format($tPagInt, 2).' = '.number_format($tIntPend, 2);
                            @endphp
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end text-danger"
                                    data-bs-toggle="tooltip" title="{{ $tipIntPend }}" style="cursor:help;">Interés pendiente</td>
                                <td class="text-end text-danger" data-bs-toggle="tooltip" title="{{ $tipIntPend }}" style="cursor:help;">{{ number_format($tIntPend, 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- La mora pagada vive en la columna Mora Pag. (por cuota).
                                 Si algún pago MORA quedó sin cuota asignada (caja > suma
                                 de cuotas), el residuo sale aquí para cuadrar con Caja 1. --}}
                            @php $moraSinCuota = round($moraPagadaCaja - $tMoraPag, 2); @endphp
                            @if($moraSinCuota > 0.01)
                                <tr class="fw-bold" style="background:#f0f0f0;">
                                    <td colspan="{{ $tieneExc ? 9 : 8 }}" class="text-end">Mora sin cuota asignada</td>
                                    <td class="text-end">{{ number_format($moraSinCuota, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
    {{-- Modal del recibo (ventana flotante, compartido con la ficha y el cronograma). --}}
    @include('livewire.payments.partials._modal-recibo')
<span id="final"></span>
</div>
