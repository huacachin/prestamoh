<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REGISTRAR PAGO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Nuevo</span></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Revisa los siguientes errores:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3">
                {{-- Buscar credito --}}
                <div class="col-12"><div class="app-divider-v">CREDITO</div></div>

                @if($creditInfo)
                    <div class="col-12">
                        <span class="badge bg-dark p-2">{{ $creditInfo }}</span>
                        <button class="btn btn-sm btn-outline-danger ms-2" wire:click="clearCredit">
                            <i class="ti ti-x"></i> Cambiar
                        </button>
                    </div>
                @else
                    <div class="col-12 col-md-6 position-relative">
                        <label class="form-label">Buscar Credito (*)</label>
                        <input type="search" class="form-control form-control-sm @error('credit_id') is-invalid @enderror"
                               placeholder="Nombre o DNI del cliente..." wire:model.live.debounce.300ms="searchCredit">
                        @error('credit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        @if(count($credits) > 0)
                            <div class="list-group position-absolute w-100 shadow" style="z-index:100; max-height:200px; overflow-y:auto;">
                                @foreach($credits as $c)
                                    <button type="button" class="list-group-item list-group-item-action py-1"
                                            wire:click="selectCredit({{ $c->id }})">
                                        {{ $c->client?->fullName() }} - {{ $c->client?->documento }}
                                        | Credito #{{ $c->id }} | S/. {{ number_format($c->importe, 2) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Info del credito seleccionado --}}
                @if($selectedCredit)
                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-auto">
                                <small class="text-muted">Capital:</small>
                                <strong>S/. {{ number_format($selectedCredit->importe, 2) }}</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Cuotas:</small>
                                <strong>{{ $selectedCredit->cuotas }}</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Tipo:</small>
                                <strong>{{ $selectedCredit->tipoPlanillaLabel() }}</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Interes:</small>
                                <strong>{{ $selectedCredit->interes }}%</strong>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Situacion:</small>
                                <span class="badge bg-success">{{ $selectedCredit->situacion }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Datos del pago --}}
                @if($credit_id)
                    <div class="col-12"><div class="app-divider-v">DATOS DEL PAGO</div></div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Cuota (*)</label>
                        <select class="form-select form-select-sm @error('installment_id') is-invalid @enderror"
                                wire:model="installment_id">
                            <option value="">-- Seleccionar cuota --</option>
                            @foreach($installments as $inst)
                                <option value="{{ $inst->id }}">
                                    Cuota {{ $inst->num_cuota }}
                                    - Venc: {{ $inst->fecha_vencimiento?->format('d/m/Y') }}
                                    - Cap: {{ number_format($inst->importe_cuota, 2) }}
                                    - Int: {{ number_format($inst->importe_interes, 2) }}
                                    @if($inst->pagado) [PAGADO] @else [Pend: {{ number_format($inst->saldoPendiente(), 2) }}] @endif
                                </option>
                            @endforeach
                        </select>
                        @error('installment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-auto">
                        <label class="form-label">Tipo Pago (*)</label>
                        <select class="form-select form-select-sm @error('tipo') is-invalid @enderror"
                                wire:model="tipo">
                            <option value="CAPITAL">CAPITAL</option>
                            <option value="INTERES">INTERES</option>
                            <option value="MORA">MORA</option>
                        </select>
                        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-auto">
                        <label class="form-label">Monto (*)</label>
                        <input type="number" step="0.01" class="form-control form-control-sm @error('monto') is-invalid @enderror"
                               placeholder="0.00" wire:model="monto">
                        @error('monto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-auto">
                        <label class="form-label">Fecha (*)</label>
                        <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                               wire:model="fecha">
                        @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-auto">
                        <label class="form-label">Nro. Recibo</label>
                        <input type="text" class="form-control form-control-sm" placeholder="Recibo"
                               wire:model="nro_recibo">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Detalle</label>
                        <input type="text" class="form-control form-control-sm" placeholder="Observacion"
                               wire:model="detalle">
                    </div>
                @endif
            </div>

            <div class="mt-3 d-flex gap-2">
                @if($credit_id)
                    <button class="btn btn-sm btn-primary" wire:click="save">
                        <i class="ti ti-device-floppy f-s-12"></i> Registrar Pago
                    </button>
                @endif
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>

            {{-- Tabla de cuotas del credito seleccionado --}}
            @if($credit_id && count($installments) > 0)
                <div class="mt-3">
                    <h6>CRONOGRAMA DE CUOTAS</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="bg-primary">
                            <tr>
                                <th>Cuota</th>
                                <th>Fecha Venc.</th>
                                <th>Capital</th>
                                <th>Interes</th>
                                <th>Pagado Cap.</th>
                                <th>Pagado Int.</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($installments as $inst)
                                <tr class="{{ $inst->pagado ? 'table-success' : '' }}">
                                    <td>{{ $inst->num_cuota }}</td>
                                    <td>{{ $inst->fecha_vencimiento?->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ number_format($inst->importe_cuota, 2) }}</td>
                                    <td class="text-end">{{ number_format($inst->importe_interes, 2) }}</td>
                                    <td class="text-end">{{ number_format($inst->importe_aplicado, 2) }}</td>
                                    <td class="text-end">{{ number_format($inst->interes_aplicado, 2) }}</td>
                                    <td class="text-end">{{ number_format($inst->saldoPendiente(), 2) }}</td>
                                    <td>
                                        @if($inst->pagado)
                                            <span class="badge bg-success">Pagado</span>
                                        @else
                                            <span class="badge bg-warning">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
