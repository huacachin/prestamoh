<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE DE CAJA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Caja</a>
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
                        <div class="col-md-4">
                            <div class="card border-start border-success border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Total Ingresos</small>
                                    <h5 class="mb-0 text-success">S/ {{ number_format($summary->total_ingresos, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-start border-danger border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Total Egresos</small>
                                    <h5 class="mb-0 text-danger">S/ {{ number_format($summary->total_egresos, 2) }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-start border-primary border-4 shadow-sm">
                                <div class="card-body py-2">
                                    <small class="text-muted">Balance</small>
                                    <h5 class="mb-0 {{ $summary->balance >= 0 ? 'text-success' : 'text-danger' }}">
                                        S/ {{ number_format($summary->balance, 2) }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- Incomes table --}}
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success">Ingresos</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover">
                                    <thead class="bg-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Concepto</th>
                                        <th>Detalle</th>
                                        <th>Monto</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($incomes as $index => $income)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $income->date?->format('d/m/Y') }}</td>
                                            <td>{{ $income->reason }}</td>
                                            <td>{{ $income->detail }}</td>
                                            <td class="text-end">{{ number_format($income->total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-3 text-muted text-center">Sin ingresos</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                    <tfoot class="bg-primary">
                                    <tr>
                                        <td></td>
                                        <td class="fw-bold" colspan="3">TOTAL</td>
                                        <td class="text-end fw-bold">{{ number_format($summary->total_ingresos, 2) }}</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Expenses table --}}
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger">Egresos</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover">
                                    <thead class="bg-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Concepto</th>
                                        <th>Detalle</th>
                                        <th>Monto</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($expenses as $index => $expense)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $expense->date?->format('d/m/Y') }}</td>
                                            <td>{{ $expense->reason }}</td>
                                            <td>{{ $expense->detail }}</td>
                                            <td class="text-end">{{ number_format($expense->total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-3 text-muted text-center">Sin egresos</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                    <tfoot class="bg-primary">
                                    <tr>
                                        <td></td>
                                        <td class="fw-bold" colspan="3">TOTAL</td>
                                        <td class="text-end fw-bold">{{ number_format($summary->total_egresos, 2) }}</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
