<div class="container-fluid">
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
        @php $c = $calcs; @endphp

        {{-- Acciones --}}
        <div class="row my-2">
            <div class="col-12">
                <div class="d-flex gap-2 py-1">
                    <a href="{{ route('clients.show', $credit->client_id) }}" class="btn btn-sm btn-secondary ms-auto">Regresar</a>
                </div>
            </div>
        </div>

        {{-- Formulario --}}
        <form wire:submit.prevent="pagar">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Cliente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->id }}-{{ $credit->client?->fullName() }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">DNI</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->client?->documento }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Moneda</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $credit->moneda ?: 'Soles' }}" readonly>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label mb-0 small fw-semibold">Capital / % / Interés / Total</label>
                            <div class="d-flex gap-1">
                                <input type="text" class="form-control form-control-sm bg-light" style="width:100px;"
                                       value="{{ number_format($c['importe'], 2) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:60px;"
                                       value="{{ number_format($c['interes_pct'], 0) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:100px;"
                                       value="{{ number_format($c['interes_total'], 2) }}" readonly>
                                <input type="text" class="form-control form-control-sm bg-light" style="width:120px;"
                                       value="{{ number_format($c['total_credito'], 2) }}" readonly>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Pago x día atrasado</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:600;" value="{{ number_format($c['mora_rate'], 2) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Ejecutivo</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['asesor_nombre'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Saldo Pendiente</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:bold;"
                                   value="{{ number_format($c['saldo_pendiente'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Monto a Pagar</label>
                            <input type="number" class="form-control form-control-sm" wire:model="monto"
                                   min="0.01" max="{{ $c['saldo_pendiente'] }}" step="0.01" style="background:yellow;">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label mb-0 small fw-semibold">Mora</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="0.00" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Pago</label>
                            <input type="date" class="form-control form-control-sm bg-light"
                                   wire:model="fecpag" readonly>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Fecha de Vencimiento</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ $c['fecha_venc'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Días Transcurridos</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:red; color:white;"
                                   value="{{ $c['dias_atraso'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Descontar Días</label>
                            <input type="number" class="form-control form-control-sm" wire:model="diasf"
                                   min="0" style="background:yellow;">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Días Final</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:red; color:white;"
                                   value="{{ $c['dias_final'] }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Total Mora</label>
                            <input type="text" class="form-control form-control-sm"
                                   style="background:red; color:white;"
                                   value="{{ number_format($c['total_mora'], 2) }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Saldo P. + Mora</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   style="color:red; font-weight:bold;"
                                   value="{{ number_format($c['saldo_mora'], 2) }}" readonly>
                        </div>
                    </div>

                    {{-- Inputs avanzados de mora manual --}}
                    <div class="row g-2 mt-2 pt-2 border-top">
                        <div class="col-12">
                            <small class="text-muted fw-semibold">PAGOS DE MORA MANUALES (avanzado)</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Cuota destino (idpre)</label>
                            <select class="form-select form-select-sm" wire:model="idpre">
                                <option value="">—</option>
                                @foreach($credit->installments as $ins)
                                    <option value="{{ $ins->id }}">
                                        Cuota {{ $ins->num_cuota }} ({{ $ins->fecha_pago?->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Mora Interés</label>
                            <input type="number" class="form-control form-control-sm" wire:model="impointe2"
                                   min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Mora Acumulada</label>
                            <input type="number" class="form-control form-control-sm" wire:model="saldomora"
                                   min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Mora Capital</label>
                            <input type="number" class="form-control form-control-sm" wire:model="impomora"
                                   min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Observación</label>
                            <input type="text" class="form-control form-control-sm" wire:model="obs"
                                   placeholder="Observación de la cuota">
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-3 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="ckmora" id="ckmora">
                            <label class="form-check-label fw-semibold ms-2" for="ckmora" style="color:red;">
                                Reserva Mora
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="cancel" id="cancel">
                            <label class="form-check-label fw-semibold ms-2" for="cancel" style="color:red;">
                                Cancelado
                            </label>
                        </div>

                        <button type="submit" class="btn btn-sm btn-dark ms-auto" wire:loading.attr="disabled">
                            <i class="ti ti-thumb-up"></i> Pagar
                        </button>
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

        {{-- Tabla cronograma --}}
        <div class="card shadow-sm mt-2">
            <div class="card-body pb-2">
                <div class="table-responsive" style="max-height: 500px; overflow:auto;">
                    <table class="table table-bordered table-hover" style="font-size: 11px;">
                        <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th class="text-center" style="width:80px;">N° Cuota</th>
                                <th class="text-center" style="width:110px;">Periodo</th>
                                <th class="text-center" style="width:100px;">Capital</th>
                                <th class="text-center" style="width:100px;">Interés</th>
                                <th class="text-center" style="width:110px;">Total</th>
                                <th class="text-center" style="width:90px;">Mora</th>
                                <th class="text-center" style="width:110px;">Pagado</th>
                                <th class="text-center" style="width:120px;">Fecha Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $sumCap = 0; $sumInt = 0; $sumPag = 0; $sumMora = 0; $todayStr = now()->format('Y-m-d'); @endphp
                            @foreach($credit->installments as $ins)
                                @php
                                    $fechaPago = $ins->fecha_pago ? $ins->fecha_pago->format('Y-m-d') : '';
                                    $dow = $fechaPago ? \Carbon\Carbon::parse($fechaPago)->dayOfWeek : null;
                                    $color = '';
                                    if ($dow === \Carbon\Carbon::SUNDAY) $color = 'red';
                                    elseif ($dow === \Carbon\Carbon::SATURDAY) $color = 'green';

                                    $cap = round((float) $ins->importe_cuota, 2);
                                    $int = round((float) $ins->importe_interes, 2);
                                    $apli = round((float) $ins->importe_aplicado, 2);
                                    $iapli = round((float) $ins->interes_aplicado, 2);
                                    $mora = round((float) $ins->importe_mora, 2);
                                    $pagado = round($apli + $iapli + $mora, 2);
                                    $totalCuota = round($cap + $int, 2);

                                    $rowStyle = $color ? 'color:'.$color.';' : '';

                                    $sumCap += $cap; $sumInt += $int; $sumPag += $pagado; $sumMora += $mora;
                                @endphp
                                <tr style="{{ $rowStyle }}">
                                    <td class="text-center">{{ $ins->num_cuota }}</td>
                                    <td class="text-center">{{ $fechaPago }}</td>
                                    <td class="text-end">{{ number_format($cap, 2) }}</td>
                                    <td class="text-end">{{ number_format($int, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalCuota, 2) }}</td>
                                    <td class="text-end">{{ number_format($mora, 2) }}</td>
                                    <td class="text-end">{{ number_format($pagado, 2) }}</td>
                                    <td class="text-center">{{ $ins->pagado ? $fechaPago : '' }}</td>
                                </tr>
                            @endforeach
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center">Total</td>
                                <td class="text-end">{{ number_format($sumCap, 2) }}</td>
                                <td class="text-end">{{ number_format($sumInt, 2) }}</td>
                                <td class="text-end">{{ number_format($sumCap + $sumInt, 2) }}</td>
                                <td class="text-end">{{ number_format($sumMora, 2) }}</td>
                                <td class="text-end">{{ number_format($sumPag, 2) }}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
