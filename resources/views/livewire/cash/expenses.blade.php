<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESOS</h4>
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
                    <a href="#" class="f-s-14">Egresos</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('cash_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('cash_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('cash_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('cash_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 160px;">
                                    <label class="form-label mb-0">Fecha</label>
                                    <input type="date" class="form-control form-control-sm" wire:model.live="fecha">
                                </div>

                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar por motivo, detalle o responsable..." wire:model.live="search">
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                @hasanyrole('superusuario|administrador|director|asesor')
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('cash.expenses.create') }}" target="_blank">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>
                                @endhasanyrole
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Detalle</th>
                                <th>Tipo Doc.</th>
                                <th>Responsable</th>
                                <th>Total</th>
                                <th>Usuario</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($expenses as $expense)
                                <tr>
                                    <td>{{ $loop->iteration + ($expenses->currentPage() - 1) * $expenses->perPage() }}</td>
                                    <td>{{ $expense->date->format('d/m/Y') }}</td>
                                    <td>{{ $expense->reason }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($expense->detail, 30) ?: '-' }}</td>
                                    <td>{{ $expense->document_type ?: '-' }}</td>
                                    <td>{{ $expense->in_charge ?: '-' }}</td>
                                    <td class="num">{{ number_format($expense->total, 2) }}</td>
                                    <td>{{ $expense->user?->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('cash.expenses.edit', $expense->id) }}" title="Editar">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="4"></td>
                                <td class="num">{{ number_format($expenses->sum('total'), 2) }}</td>
                                <td></td>
                                <td class="num">{{ $expenses->total() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $expenses->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
