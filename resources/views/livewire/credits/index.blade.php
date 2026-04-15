<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CRÉDITOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Listado</a></li>
            </ul>
        </div>
    </div>

    @if(session('credit_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('credit_success') }}
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
                                <div class="flex-shrink-0" style="width: 130px;">
                                    <select class="form-select form-select-sm" wire:model="filterSituacion">
                                        <option value="">Todas</option>
                                        <option value="Activo">Activo</option>
                                        <option value="Cancelado">Cancelado</option>
                                        <option value="Refinanciado">Refinanciado</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <select class="form-select form-select-sm" wire:model="filterTipo">
                                        <option value="">Tipo</option>
                                        <option value="1">Semanal</option>
                                        <option value="3">Mensual</option>
                                        <option value="4">Diario</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar cliente..." wire:model="search">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('credits.create') }}" target="_blank">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Fecha</th>
                                <th>Importe</th>
                                <th>Cuotas</th>
                                <th>Tipo</th>
                                <th>Interés</th>
                                <th>Situación</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($credits as $credit)
                                <tr>
                                    <td>{{ $loop->iteration + ($credits->currentPage() - 1) * $credits->perPage() }}</td>
                                    <td>{{ $credit->client?->fullName() }}</td>
                                    <td>{{ $credit->client?->documento }}</td>
                                    <td>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                                    <td>{{ $credit->cuotas }}</td>
                                    <td>{{ $credit->tipoPlanillaLabel() }}</td>
                                    <td>{{ $credit->interes }}%</td>
                                    <td>
                                        @php
                                            $bc = match($credit->situacion) {
                                                'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                                'Refinanciado' => 'bg-warning', 'Eliminado' => 'bg-danger', default => 'bg-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $bc }}">{{ $credit->situacion }}</span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('credits.show', $credit->id) }}" title="Ver">
                                            <i class="ti ti-eye f-s-18 text-info" style="cursor:pointer"></i>
                                        </a>
                                        <a href="{{ route('credits.schedule', $credit->id) }}" title="Cronograma">
                                            <i class="ti ti-calendar f-s-18 text-primary" style="cursor:pointer"></i>
                                        </a>
                                        <a href="{{ route('credits.edit', $credit->id) }}" title="Editar">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="7"></td>
                                <td class="num">{{ $credits->total() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-2">{{ $credits->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
