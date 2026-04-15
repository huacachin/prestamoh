<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">BALANCE DE CAJA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Balance</a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Filtro de fecha --}}
    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div class="flex-shrink-0" style="width: 180px;">
                            <label class="form-label mb-0">Fecha</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="fecha">
                        </div>
                        <button class="btn btn-sm btn-dark" wire:click="$refresh">
                            <i class="ti ti-search f-s-12"></i> Consultar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Resumen --}}
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">Saldo Inicial</h6>
                    <h4 class="mb-0">S/ {{ number_format($saldo_inicial, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">Total Ingresos</h6>
                    <h4 class="mb-0 text-success">S/ {{ number_format($total_ingresos, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-danger">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">Total Egresos</h6>
                    <h4 class="mb-0 text-danger">S/ {{ number_format($total_egresos, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1">Saldo Final</h6>
                    <h4 class="mb-0 {{ $saldo_final >= 0 ? 'text-primary' : 'text-danger' }}">S/ {{ number_format($saldo_final, 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de Ingresos --}}
    <div class="row table-section mt-3">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <h6 class="mb-3 text-success"><i class="ti ti-arrow-up-circle f-s-16"></i> Ingresos del d&iacute;a</h6>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Motivo</th>
                                <th>Detalle</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($incomes as $income)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $income->reason }}</td>
                                    <td>{{ $income->detail ?: '-' }}</td>
                                    <td class="num">{{ number_format($income->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-3 text-muted">Sin ingresos para esta fecha</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL INGRESOS</td>
                                <td></td>
                                <td class="num">{{ number_format($total_ingresos, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de Egresos --}}
    <div class="row table-section mt-3">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <h6 class="mb-3 text-danger"><i class="ti ti-arrow-down-circle f-s-16"></i> Egresos del d&iacute;a</h6>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Motivo</th>
                                <th>Detalle</th>
                                <th>Responsable</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($expenses as $expense)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $expense->reason }}</td>
                                    <td>{{ $expense->detail ?: '-' }}</td>
                                    <td>{{ $expense->in_charge ?: '-' }}</td>
                                    <td class="num">{{ number_format($expense->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-3 text-muted">Sin egresos para esta fecha</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL EGRESOS</td>
                                <td colspan="2"></td>
                                <td class="num">{{ number_format($total_egresos, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
