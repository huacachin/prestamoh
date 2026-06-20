<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">LISTADO GENERAL DE USUARIOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Usuarios</a></li>
            </ul>
        </div>
    </div>

    @if(session('user_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('user_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-nowrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 260px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar..." wire:model="search" name="search" autocomplete="off">
                                </div>

                                <div class="flex-shrink-0" style="width: 140px;">
                                    <select class="form-select form-select-sm" wire:model.live="estado">
                                        <option value="active">Activos</option>
                                        <option value="inactive">Cesados</option>
                                        <option value="all">Todos</option>
                                    </select>
                                </div>

                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>

                                @can('configuracion.usuarios')
                                <a class="btn btn-sm btn-primary flex-shrink-0"
                                   href="{{ route('settings.users.create') }}" target="_blank">
                                    <i class="ti ti-square-plus f-s-12"></i> Nuevo
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover table-autofit" >
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Nombres</th>
                                <th>Usuario</th>
                                <th>Teléfono</th>
                                <th>Sede</th>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->username }}</td>
                                    <td>{{ $user->phone ?: '—' }}</td>
                                    <td>{{ $user->headquarter?->name ?: '—' }}</td>
                                    <td>{{ optional($user->roles->first())->name ?: '—' }}</td>
                                    <td>
                                        <span class="badge bg-dark">{{ $user->permissions->count() }} permisos</span>
                                    </td>
                                    <td>
                                        @if($user->status === 'active')
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Cesado</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @can('configuracion.usuarios')
                                        <a class="btn btn-sm btn-outline-success me-1" title="Editar datos"
                                           href="{{ route('settings.users.edit', $user->id) }}">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-dark" title="Permisos"
                                           href="{{ route('settings.users.perms', $user->id) }}" target="_blank">
                                            <i class="ti ti-shield-lock"></i>
                                        </a>
                                        @if($user->status === 'active')
                                            @if(!$user->hasRole('director'))
                                            <button class="btn btn-sm btn-outline-danger ms-1" title="Desactivar"
                                                    wire:click="questionDelete({{ $user->id }}, '{{ $user->name }}')">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                            @endif
                                        @else
                                            <button class="btn btn-sm btn-outline-success ms-1" title="Re-activar"
                                                    wire:click="reactivate({{ $user->id }})">
                                                <i class="ti ti-restore"></i>
                                            </button>
                                        @endif
                                        @endcan
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
                                <td>TOTAL USUARIOS</td>
                                <td colspan="5"></td>
                                <td>{{ $users->count() }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
