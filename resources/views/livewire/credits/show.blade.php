<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DETALLE DE CRÉDITO #{{ $credit->id }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Detalle</span></li>
            </ul>
        </div>
    </div>

    @if(session('credit_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('credit_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info crédito --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h6>CLIENTE</h6>
                    <p class="mb-1"><strong>{{ $credit->client?->fullName() }}</strong></p>
                    <small class="text-muted">{{ $credit->client?->tipo_documento }} {{ $credit->client?->documento }}</small>
                </div>
                <div class="col-12 col-md-6">
                    <div class="row g-2">
                        <div class="col-auto">
                            <small class="text-muted">Importe:</small><br>
                            <strong>{{ $credit->moneda === 'USD' ? '$' : 'S/.' }} {{ number_format($credit->importe, 2) }}</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Cuotas:</small><br>
                            <strong>{{ $credit->cuotas }} ({{ $credit->tipoPlanillaLabel() }})</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Interés:</small><br>
                            <strong>{{ $credit->interes }}%</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Situación:</small><br>
                            @php
                                $bc = match($credit->situacion) {
                                    'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                    'Refinanciado' => 'bg-warning', 'Eliminado' => 'bg-danger', default => 'bg-dark',
                                };
                            @endphp
                            <span class="badge {{ $bc }}">{{ $credit->situacion }}</span>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Fecha:</small><br>
                            <strong>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Vencimiento:</small><br>
                            <strong>{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resumen financiero --}}
            <div class="row g-3 mt-2">
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#e8f5e9;">
                        <small class="text-muted">Total Deuda</small><br>
                        <strong>{{ number_format($totalDeuda, 2) }}</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#e3f2fd;">
                        <small class="text-muted">Total Pagado</small><br>
                        <strong>{{ number_format($totalPagado, 2) }}</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#fff3e0;">
                        <small class="text-muted">Saldo Pendiente</small><br>
                        <strong>{{ number_format($saldoPendiente, 2) }}</strong>
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('credits.schedule', $credit->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-calendar"></i> Cronograma
                </a>
                <a href="{{ route('payments.create', $credit->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-currency-dollar"></i> Registrar Pago
                </a>
                <a href="{{ route('credits.edit', $credit->id) }}" class="btn btn-sm btn-outline-warning">
                    <i class="ti ti-edit"></i> Editar
                </a>
                <a href="{{ route('credits.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    </div>

    {{-- Cronograma --}}
    <div class="card shadow-sm mt-3">
        <div class="card-body pb-2">
            <h6>CRONOGRAMA DE CUOTAS</h6>
            <div class="table-responsive tableFixHead">
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
                </table>
            </div>
        </div>
    </div>

    {{-- Pagos realizados --}}
    <div class="card shadow-sm mt-3">
        <div class="card-body pb-2">
            <h6>PAGOS REALIZADOS</h6>
            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Recibo</th>
                        <th>Usuario</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($credit->payments as $pay)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $pay->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $pay->tipo }}</td>
                            <td class="text-end">{{ number_format($pay->monto, 2) }}</td>
                            <td>{{ $pay->nro_recibo ?: '—' }}</td>
                            <td>{{ $pay->user?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-muted">Sin pagos registrados</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
