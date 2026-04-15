<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE DE PAGOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Pagos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 170px;">
                                    <label class="form-label mb-0 small">Desde</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="fecha_desde">
                                </div>
                                <div class="flex-shrink-0" style="width: 170px;">
                                    <label class="form-label mb-0 small">Hasta</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="fecha_hasta">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Summary cards --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card border-start border-primary border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Capital</small>
                                    <h5 class="mb-0">S/ {{ number_format($totals->CAPITAL, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-start border-success border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Interes</small>
                                    <h5 class="mb-0">S/ {{ number_format($totals->INTERES, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-start border-danger border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Mora</small>
                                    <h5 class="mb-0">S/ {{ number_format($totals->MORA, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-start border-dark border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Total</small>
                                    <h5 class="mb-0">S/ {{ number_format($totals->total, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Credito #</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($payments as $index => $payment)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $payment->fecha?->format('d/m/Y') }}</td>
                                    <td>{{ $payment->credit?->client?->fullName() }}</td>
                                    <td>{{ $payment->credit_id }}</td>
                                    <td>
                                        @php
                                            $bc = match($payment->tipo) {
                                                'CAPITAL' => 'bg-primary', 'INTERES' => 'bg-success',
                                                'MORA' => 'bg-danger', default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $bc }}">{{ $payment->tipo }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($payment->monto, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-muted text-center">No se encontraron pagos en el rango seleccionado</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start fw-bold">TOTAL</td>
                                <td colspan="3"></td>
                                <td class="text-end fw-bold">{{ number_format($totals->total, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
