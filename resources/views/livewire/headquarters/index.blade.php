<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO DE SUCURSALES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Sucursales</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('headquarter_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('headquarter_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('headquarter_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('headquarter_error') }}
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
                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search"
                                           class="form-control form-control-sm"
                                           placeholder="Buscar por nombre"
                                           wire:model="search">
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                @hasanyrole('superusuario|administrador')
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('settings.headquarters.create') }}" target="_blank">
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
                                <th>Orden</th>
                                <th>Nombre</th>
                                <th>Usuarios</th>
                                <th>Estado</th>
                                @hasanyrole('superusuario|administrador')
                                <th></th>
                                @endhasanyrole
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($headquarters as $hq)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $hq->sort_order }}</td>
                                    <td>{{ $hq->name }}</td>
                                    <td>{{ $hq->activeUsers->pluck('username')->implode(', ') ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $hq->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $hq->status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    @hasanyrole('superusuario|administrador')
                                    <td>
                                        <a href="{{ route('settings.headquarters.edit', $hq->id) }}">
                                            <i class="ti ti-edit f-s-18 text-success" style="cursor:pointer"></i>
                                        </a>
                                    </td>
                                    @endhasanyrole
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-muted">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td></td>
                                <td colspan="2" class="num">{{ $headquarters->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
