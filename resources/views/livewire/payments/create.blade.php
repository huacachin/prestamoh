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
            // Helper validosdoc() del legacy: formatea días como "N días" / "N meses M días" / etc.
            $validosdoc = function ($d) {
                $d = (int) $d;
                if ($d <= 0) return '';
                if ($d < 30) return $d . ' día' . ($d === 1 ? '' : 's');
                $meses = intdiv($d, 30); $resto = $d % 30;
                if ($d < 365) {
                    return $meses . ' mes' . ($meses === 1 ? '' : 'es')
                        . ($resto > 0 ? ' ' . $resto . ' día' . ($resto === 1 ? '' : 's') : '');
                }
                $anos = intdiv($d, 365); $restoA = $d % 365;
                $mesesA = intdiv($restoA, 30); $diasA = $restoA % 30;
                $out = $anos . ' año' . ($anos === 1 ? '' : 's');
                if ($mesesA > 0) $out .= ' ' . $mesesA . ' mes' . ($mesesA === 1 ? '' : 'es');
                if ($diasA > 0)  $out .= ' ' . $diasA . ' día' . ($diasA === 1 ? '' : 's');
                return $out;
            };
            // Para cancelar el crédito hay que cubrir el saldo. Si "Reserva Mora"
            // está marcada, alcanza con capital+interés (la mora se perdona al
            // cancelar). Sin reserva, hay que cubrir también la mora — pero esa
            // mora (calculada u override) SE COBRA junto con el pago, así que
            // cuenta como cobertura. La cobertura suma: monto + mora cobrada +
            // moras manuales (impomora/impointe2).
            $moraCobrada = $ckmora ? 0.0 : (float) $c['total_mora'];
            $cobertura = (float) $monto + $moraCobrada + (float) $impomora + (float) $impointe2;
            $cancelDisabled = $ckmora
                ? ($c['saldo_pendiente'] - $cobertura) > 0.01
                : ($c['saldo_mora']      - $cobertura) > 0.01;
        @endphp

        {{-- Formulario --}}
        <form wire:submit.prevent="pagar">
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- Fila 1: Cliente / DNI / Moneda / Capital(4) / Pago x día / Ejecutivo --}}
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Cliente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->id }}-{{ $credit->client?->fullName() }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">DNI</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->client?->documento }}" maxlength="8" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Moneda</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->moneda ?: 'Soles' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Capital / % / Interés / Total</label>
                            <div class="d-flex gap-1">
                                <input type="text" class="form-control form-control-sm bg-light" style="width:80px;"
                                       value="{{ number_format($c['importe'], 2) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:50px;"
                                       value="{{ number_format($c['interes_pct'], 0) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:80px;"
                                       value="{{ number_format($c['interes_total'], 2) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:90px;"
                                       value="{{ number_format($c['total_credito'], 2) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Pago x día atrasado</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:600;"
                                   value="{{ number_format($c['mora_rate'], 2) }}" readonly>
                        </div>
                    </div>

                    {{-- Fila 2: Ejecutivo / Saldo Pendiente / Monto a Pagar / Fecha de Pago.
                         (El legacy tenía "Mora ¿?" para la mora extra al sobrepagar; aquí el
                         sobrepago está bloqueado —Monto ≤ Saldo— y la mora va en la Fila 4.) --}}
                    <div class="row g-2 mt-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Ejecutivo</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['asesor_nombre'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Saldo Pendiente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:bold;"
                                   value="{{ number_format($c['saldo_restante'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Monto a Pagar</label>
                            <input type="number" name="monto" autocomplete="off" class="form-control form-control-sm"
                                   wire:model.live.debounce.400ms="monto"
                                   min="0.00" max="{{ $c['saldo_pendiente'] }}" step="0.01"
                                   style="background:yellow;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Pago</label>
                            <input type="text" name="fecpag" autocomplete="off" class="form-control form-control-sm bg-light dates3"
                                   wire:model="fecpag" readonly>
                        </div>
                    </div>

                    {{-- Fila 3: Fecha Vcto / Días Transc. / Descontar / Días Final / Tiempo --}}
                    <div class="row g-2 mt-2">
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Vencimiento</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['fecha_venc'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Días Transcurridos</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:#ff0000; color:#fff;"
                                   value="{{ $c['dias_atraso'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Descontar Días</label>
                            <input type="number" name="diasf" autocomplete="off" class="form-control form-control-sm"
                                   wire:model.live.debounce.400ms="diasf"
                                   min="0" style="background:yellow;">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Días Final</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:#ff0000; color:#fff;"
                                   value="{{ $c['dias_final'] }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Tiempo Transcurridos</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:#ff0000; color:#fff;"
                                   value="{{ $validosdoc($c['dias_atraso']) }}" readonly>
                        </div>
                    </div>

                    {{-- Fila 4: Total Mora / Saldo P. + Mora --}}
                    <div class="row g-2 mt-2">
                        <div class="col-md-2">
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
                                       class="form-control form-control-sm"
                                       style="background:#ff0000; color:#fff;"
                                       wire:model.live.debounce.400ms="moraManual"
                                       placeholder="{{ number_format($c['total_mora_calc'], 2) }}"
                                       @disabled(((float)$monto) <= 0)
                                       title="{{ ((float)$monto) > 0 ? 'Total Mora editable (reemplaza la calculada)' : 'Escribe primero el Monto a Pagar' }}">
                            @else
                                <input type="text" class="form-control form-control-sm"
                                       style="background:#ff0000; color:#fff;"
                                       value="{{ number_format($c['total_mora'], 2) }}" readonly>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Saldo P. + Mora</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:bold;"
                                   value="{{ number_format($c['saldo_mora_restante'], 2) }}" readonly>
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
                            <input class="form-check-input" type="checkbox" role="switch"
                                   wire:model="cancel" id="cancel"
                                   {{ $cancelDisabled ? 'disabled' : '' }}
                                   onclick="if(this.checked){if(!confirm('Si marcas Cancelado, este crédito YA NO podrá ser refinanciado. ¿Confirmar?')) this.checked=false;}">
                            <label class="form-check-label fw-semibold ms-1" for="cancel"
                                   style="color:{{ $cancelDisabled ? '#999' : 'red' }};">
                                Cancelado
                                @if($cancelDisabled)
                                    <small class="text-muted">
                                        (se habilita al cubrir {{ $ckmora ? 'capital + interés' : 'capital + interés + mora' }})
                                    </small>
                                @endif
                            </label>
                        </div>
                    </div>

                    {{-- Botones --}}
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <button type="submit" class="btn btn-sm btn-dark"
                                wire:loading.attr="disabled" wire:target="pagar">
                            <i class="ti ti-thumb-up"></i>
                            <span wire:loading.remove wire:target="pagar">Pagar</span>
                            <span wire:loading wire:target="pagar">Procesando…</span>
                        </button>
                        @if($credit->situacion !== 'Cancelado')
                            <a href="{{ route('payments.refinance', $credit->id) }}" class="btn btn-sm btn-warning">
                                <i class="ti ti-refresh"></i> Refinanciar
                            </a>
                        @endif
                        <a href="#" class="btn btn-sm btn-success disabled" title="Próximamente">
                            <i class="ti ti-file-spreadsheet"></i> Excel
                        </a>
                        <a href="{{ route('clients.show', $credit->client_id) }}"
                           class="btn btn-sm btn-danger ms-auto">
                            <i class="ti ti-arrow-back"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
        </form>

        @script
        <script>
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

        {{-- Cronograma de cuotas (homologado con /credits/{id}) --}}
        <div class="card shadow-sm mt-2">
            <div class="card-body pb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="mb-0">CRONOGRAMA DE CUOTAS</h6>
                    <x-scroll-bottom-btn scrollable="#tabla-cronograma" />
                </div>
                <div id="tabla-cronograma" class="table-responsive tableFixHead" style="max-height: 500px; overflow:auto;">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="bg-primary">
                        <tr>
                            <th>Cuota</th>
                            <th>Fecha Venc.</th>
                            <th>Capital</th>
                            <th>Interés</th>
                            <th>Pagado Cap.</th>
                            <th>Pagado Int.</th>
                            <th>Saldo</th>
                            <th>Estado</th>
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
                                <td class="text-end">{{ number_format($inst->importe_aplicado, 2) }}</td>
                                <td class="text-end">{{ number_format($inst->interes_aplicado, 2) }}</td>
                                <td class="text-end">{{ number_format($saldo, 2) }}</td>
                                <td>
                                    @if($inst->pagado)
                                        <span class="badge bg-success">Pagado</span>
                                    @elseif($vencida)
                                        <span class="badge bg-danger">Vencida</span>
                                    @else
                                        <span class="badge bg-warning">Pendiente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        @php
                            $tCap    = $credit->installments->sum('importe_cuota');
                            $tInt    = $credit->installments->sum('importe_interes');
                            $tPagCap = $credit->installments->sum('importe_aplicado');
                            $tPagInt = $credit->installments->sum('interes_aplicado');
                            $tSaldo  = $credit->installments->sum(fn ($i) => $i->saldoPendiente());
                        @endphp
                        <tfoot>
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="2" class="text-end">Totales</td>
                                <td class="text-end">{{ number_format($tCap, 2) }}</td>
                                <td class="text-end">{{ number_format($tInt, 2) }}</td>
                                <td class="text-end">{{ number_format($tPagCap, 2) }}</td>
                                <td class="text-end">{{ number_format($tPagInt, 2) }}</td>
                                <td class="text-end">{{ number_format($tSaldo, 2) }}</td>
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
