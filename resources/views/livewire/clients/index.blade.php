<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE CLIENTES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Clientes</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Listado</a></li>
            </ul>
        </div>
    </div>

    @if(session('client_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('client_success') }}
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
                                    <select class="form-select form-select-sm" wire:model="filterBy">
                                        <option value="nombre">Nombre</option>
                                        <option value="documento">Documento</option>
                                        <option value="expediente">Expediente</option>
                                    </select>
                                </div>

                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar..." wire:model="search">
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('clients.create') }}" target="_blank">
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
                                <th>Expediente</th>
                                <th>Documento</th>
                                <th>Nombre Completo</th>
                                <th>Celular</th>
                                <th>Dirección</th>
                                <th>Asesor</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($clients as $client)
                                <tr>
                                    <td>{{ $loop->iteration + ($clients->currentPage() - 1) * $clients->perPage() }}</td>
                                    <td>{{ $client->expediente }}</td>
                                    <td>{{ $client->documento }}</td>
                                    <td>{{ $client->fullName() }}</td>
                                    <td>{{ $client->celular1 ?: '—' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($client->direccion, 30) ?: '—' }}</td>
                                    <td>{{ $client->asesor?->name ?: '—' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('clients.show', $client->id) }}" title="Ver">
                                            <i class="ti ti-eye f-s-18 text-info" style="cursor:pointer"></i>
                                        </a>
                                        <a href="{{ route('clients.edit', $client->id) }}" title="Editar">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                        <a href="{{ route('credits.create', $client->id) }}" title="Nuevo Crédito">
                                            <i class="ti ti-credit-card f-s-18 text-primary" style="cursor:pointer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="5"></td>
                                <td class="num">{{ $clients->total() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $clients->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
