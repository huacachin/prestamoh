<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PERMISOS</h4>
            @if($permsUserName)
                <small class="text-muted d-block">Usuario: {{ $permsUserName }}</small>
            @endif
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a></li>
                <li class="d-flex active"><a class="f-s-14">Permisos</a></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            @if(!$canEdit)
                <div class="alert alert-secondary py-2 mb-3">
                    <i class="ti ti-info-circle"></i>
                    El SuperUsuario tiene todos los permisos por defecto.
                </div>
            @endif

            <div class="perm-grid" @if(!$canEdit) style="opacity:.6; pointer-events:none" @endif>
                @foreach($aclGroups as $groupKey => $group)
                    @php
                        $count   = count($group['items'] ?? []);
                        $compact = ($group['type'] === 'single') || ($count === 1);
                        $only    = $group['items'][0] ?? null;
                    @endphp

                    @if($compact && $only)
                        <div class="perm-row">
                            <div class="perm-col-title">{{ $group['title'] }}</div>
                            <div class="perm-col-controls">
                                <label class="chip-check" title="{{ $only['key'] }}">
                                    <input class="form-check-input" type="checkbox"
                                           value="{{ $only['key'] }}"
                                           wire:model="selectedPermissionNames"
                                           @if(!$canEdit) disabled @endif>
                                    <span>{{ $group['title'] }}</span>
                                </label>
                            </div>
                        </div>
                    @else
                        <div class="perm-row">
                            <div class="perm-col-title d-flex align-items-center gap-2">
                                <span>{{ $group['title'] }}</span>
                                @if($canEdit)
                                <a href="javascript:void(0)" class="action-icon" wire:click="selectGroup('{{ $groupKey }}')" title="Marcar todo">
                                    <i class="ti ti-square-check"></i>
                                </a>
                                <a href="javascript:void(0)" class="action-icon" wire:click="deselectGroup('{{ $groupKey }}')" title="Desmarcar">
                                    <i class="ti ti-square-x"></i>
                                </a>
                                @endif
                            </div>
                            <div class="perm-col-controls">
                                <div class="perm-chips">
                                    @foreach($group['items'] as $it)
                                        <label class="chip-check" title="{{ $it['key'] }}">
                                            <input class="form-check-input" type="checkbox"
                                                   value="{{ $it['key'] }}"
                                                   wire:model="selectedPermissionNames"
                                                   @if(!$canEdit) disabled @endif>
                                            <span>{{ $it['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-2 d-flex gap-2">
                @if($canEdit)
                <button class="btn btn-sm btn-primary" wire:click="savePerms">Guardar</button>
                @endif
                <a class="btn btn-sm btn-secondary" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
