<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PANEL DE CONTROL</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <a href="#" class="f-s-14">Inicio</a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Dashboard</a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Metric Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-green">
                <div class="metric-icon"><i class="ti ti-credit-card"></i></div>
                <div>
                    <div class="metric-value">{{ $creditosActivos }}</div>
                    <div class="metric-label">Créditos Activos</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-blue">
                <div class="metric-icon"><i class="ti ti-wallet"></i></div>
                <div>
                    <div class="metric-value">S/ {{ number_format($totalCartera, 2) }}</div>
                    <div class="metric-label">Total Cartera</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-amber">
                <div class="metric-icon"><i class="ti ti-cash"></i></div>
                <div>
                    <div class="metric-value">S/ {{ number_format($cobranzaHoy, 2) }}</div>
                    <div class="metric-label">Cobranza Hoy</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-red">
                <div class="metric-icon"><i class="ti ti-alert-triangle"></i></div>
                <div>
                    <div class="metric-value">{{ $morosidad }}</div>
                    <div class="metric-label">Morosidad</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-neutral">
                <div class="metric-icon"><i class="ti ti-arrow-down-circle"></i></div>
                <div>
                    <div class="metric-value">S/ {{ number_format($ingresosHoy, 2) }}</div>
                    <div class="metric-label">Ingresos Hoy</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card metric-neutral">
                <div class="metric-icon"><i class="ti ti-arrow-up-circle"></i></div>
                <div>
                    <div class="metric-value">S/ {{ number_format($egresosHoy, 2) }}</div>
                    <div class="metric-label">Egresos Hoy</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables --}}
    <div class="row table-section">
        {{-- Últimos Pagos --}}
        <div class="col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Últimos Pagos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ultimosPagos as $pago)
                                    <tr>
                                        <td>{{ $pago->fecha?->format('d/m/Y') }}</td>
                                        <td>{{ $pago->credit?->client?->fullName() ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-light-primary">{{ $pago->tipo }}</span>
                                        </td>
                                        <td class="text-end">S/ {{ number_format($pago->monto, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Sin pagos registrados</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Créditos Recientes --}}
        <div class="col-xl-5">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Créditos Recientes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Importe</th>
                                    <th>Situación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditosRecientes as $credito)
                                    <tr>
                                        <td>{{ $credito->fecha_prestamo?->format('d/m/Y') }}</td>
                                        <td>{{ $credito->client?->fullName() ?? '—' }}</td>
                                        <td class="text-end">S/ {{ number_format($credito->importe, 2) }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($credito->situacion) {
                                                    'Activo' => 'bg-light-success',
                                                    'Cancelado' => 'bg-light-secondary',
                                                    'Refinanciado' => 'bg-light-warning',
                                                    'Eliminado' => 'bg-light-danger',
                                                    default => 'bg-light-info',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $credito->situacion }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Sin créditos registrados</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
