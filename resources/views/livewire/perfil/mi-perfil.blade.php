<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title title-modules">MI PERFIL</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-user-circle f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Cuenta</span></a>
                </li>
                <li class="d-flex active"><a class="f-s-14">Mi perfil</a></li>
            </ul>
        </div>
    </div>

    {{-- ═══ Mis datos ═══ --}}
    <div class="card">
        <div class="card-header py-2"><h6 class="mb-0"><i class="ti ti-id"></i> Mis datos</h6></div>
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                <div class="perm-row">
                    <div class="perm-col-title">Nombre</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" autocomplete="name" maxlength="255">
                        @error('name') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Usuario</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" value="{{ $usuario->username }}" disabled title="El usuario lo cambia quien administra usuarios">
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Correo</div>
                    <div class="perm-col-controls">
                        <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" wire:model="email" autocomplete="email" maxlength="255">
                        @error('email') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Rol</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" value="{{ $rol ? ucfirst($rol) : '—' }}" disabled>
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Teléfono</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" wire:model="phone" autocomplete="tel" maxlength="15">
                        @error('phone') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Sede</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" value="{{ $sede ?: '—' }}" disabled>
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-primary btn-sm" wire:click="guardarDatos" wire:loading.attr="disabled" wire:target="guardarDatos">
                    <i class="ti ti-device-floppy"></i> Guardar mis datos
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ Contraseña ═══ --}}
    <div class="card" x-data="{ ver: false }">
        <div class="card-header py-2"><h6 class="mb-0"><i class="ti ti-key"></i> Cambiar contraseña</h6></div>
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                <div class="perm-row">
                    <div class="perm-col-title">Contraseña actual</div>
                    <div class="perm-col-controls">
                        <input :type="ver ? 'text' : 'password'" class="form-control form-control-sm @error('passwordActual') is-invalid @enderror" wire:model="passwordActual" autocomplete="current-password">
                        @error('passwordActual') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">&nbsp;</div>
                    <div class="perm-col-controls">
                        <button type="button" class="btn btn-outline-secondary btn-sm" x-on:click="ver = !ver"><i class="ti" :class="ver ? 'ti-eye-off' : 'ti-eye'"></i> <span x-text="ver ? 'Ocultar' : 'Mostrar'">Mostrar</span></button>
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Contraseña nueva</div>
                    <div class="perm-col-controls">
                        <input :type="ver ? 'text' : 'password'" class="form-control form-control-sm @error('passwordNueva') is-invalid @enderror" wire:model="passwordNueva" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                        @error('passwordNueva') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="perm-row">
                    <div class="perm-col-title">Repetir la nueva</div>
                    <div class="perm-col-controls">
                        <input :type="ver ? 'text' : 'password'" class="form-control form-control-sm" wire:model="passwordNueva_confirmation" wire:keydown.enter="cambiarPassword" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-dark btn-sm" wire:click="cambiarPassword" wire:loading.attr="disabled" wire:target="cambiarPassword">
                    <i class="ti ti-key"></i> Cambiar contraseña
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ Sesiones activas ═══ --}}
    <div class="card">
        <div class="card-header py-2"><h6 class="mb-0"><i class="ti ti-devices"></i> Sesiones activas</h6></div>
        <div class="card-body">
            @if(! $sesionesDisponibles)
                <div class="alert alert-warning py-2 mb-0 small">
                    <i class="ti ti-alert-triangle"></i> Este servidor guarda las sesiones en archivos: la lista de sesiones
                    necesita que se guarden en la base de datos (<code>SESSION_DRIVER=database</code>).
                </div>
            @else
                <p class="small text-muted mb-2">Equipos y navegadores donde tu cuenta está abierta ahora. Si ves alguno que no reconoces, cierra las otras sesiones y cambia tu contraseña.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-2 tabla-sesiones" style="font-size:12px;">
                        <thead class="table-secondary">
                            <tr>
                                <th>Dispositivo</th>
                                <th>Navegador</th>
                                <th>Dirección IP</th>
                                <th>Última actividad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($sesiones as $s)
                            <tr wire:key="sesion-{{ md5($s['id']) }}" @class(['table-success' => $s['actual']])>
                                <td>
                                    <i class="ti {{ $s['agente']['dispositivo'] === 'Celular' ? 'ti-device-mobile' : ($s['agente']['dispositivo'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }} f-s-16"></i>
                                    {{ $s['agente']['dispositivo'] }} · {{ $s['agente']['plataforma'] }}
                                </td>
                                <td>{{ $s['agente']['navegador'] }}</td>
                                <td class="font-monospace">{{ $s['ip'] ?: '—' }}</td>
                                <td style="white-space:nowrap;" title="{{ $s['ultima']->format('d/m/Y H:i:s') }}">{{ $s['ultima']->diffForHumans() }}</td>
                                <td>@if($s['actual'])<span class="badge bg-success">Esta sesión</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No hay sesiones registradas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-column flex-md-row gap-2 align-items-md-start">
                    <div style="max-width:320px;">
                        <input type="password" class="form-control form-control-sm @error('passwordSesiones') is-invalid @enderror" wire:model="passwordSesiones"
                               wire:keydown.enter="cerrarOtrasSesiones" autocomplete="current-password" placeholder="Tu contraseña, para confirmar">
                        @error('passwordSesiones') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="cerrarOtrasSesiones" wire:loading.attr="disabled" wire:target="cerrarOtrasSesiones"
                            @if($sesiones->where('actual', false)->isEmpty()) disabled title="No hay otras sesiones abiertas" @endif>
                        <i class="ti ti-logout"></i> Cerrar las otras sesiones
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
