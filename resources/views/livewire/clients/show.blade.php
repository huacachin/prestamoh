<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">FICHA DE CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Detalle</span></li>
            </ul>
        </div>
    </div>

    {{-- Datos del cliente --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-auto">
                    @if($client->imagen)
                        <img src="{{ asset('storage/' . $client->imagen) }}" alt="Foto" style="max-height: 120px; border-radius: 8px;">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light" style="width:100px;height:100px;border-radius:8px;">
                            <i class="ti ti-user f-s-22 text-muted"></i>
                        </div>
                    @endif
                </div>
                <div class="col">
                    <h5>{{ $client->fullName() }}</h5>
                    <div class="row g-2">
                        <div class="col-auto"><small class="text-muted">Expediente:</small> <strong>{{ $client->expediente }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Documento:</small> <strong>{{ $client->tipo_documento }} {{ $client->documento }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Celular:</small> <strong>{{ $client->celular1 ?: '—' }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Email:</small> <strong>{{ $client->email ?: '—' }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Dirección:</small> <strong>{{ $client->direccion ?: '—' }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Asesor:</small> <strong>{{ $client->asesor?->name ?: '—' }}</strong></div>
                        <div class="col-auto"><small class="text-muted">Sucursal:</small> <strong>{{ $client->headquarter?->name ?: '—' }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-edit"></i> Editar
                </a>
                <a href="{{ route('credits.create', $client->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-credit-card"></i> Nuevo Crédito
                </a>
                <a href="{{ route('clients.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    </div>

    {{-- Créditos del cliente --}}
    <div class="card shadow-sm mt-3">
        <div class="card-body pb-2">
            <h6 class="mb-3">CRÉDITOS DEL CLIENTE</h6>

            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Importe</th>
                        <th>Cuotas</th>
                        <th>Tipo</th>
                        <th>Interés %</th>
                        <th>Situación</th>
                        <th>Vencimiento</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($client->credits as $credit)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                            <td>{{ $credit->cuotas }}</td>
                            <td>{{ $credit->tipoPlanillaLabel() }}</td>
                            <td>{{ $credit->interes }}%</td>
                            <td>
                                @php
                                    $badgeClass = match($credit->situacion) {
                                        'Activo' => 'bg-success',
                                        'Cancelado' => 'bg-secondary',
                                        'Refinanciado' => 'bg-warning',
                                        'Eliminado' => 'bg-danger',
                                        default => 'bg-dark',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $credit->situacion }}</span>
                            </td>
                            <td>{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('credits.show', $credit->id) }}" title="Ver">
                                    <i class="ti ti-eye f-s-18 text-info" style="cursor:pointer"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 text-muted">Sin créditos registrados</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
