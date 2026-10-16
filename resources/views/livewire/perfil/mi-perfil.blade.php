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

    {{-- ═══ Mis datos (solo lectura: los cambia quien administra usuarios) ═══ --}}
    <div class="card">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <h6 class="mb-0"><i class="ti ti-id"></i> Mis datos</h6>
            <span class="badge bg-secondary" title="Para corregir algún dato, pídeselo a quien administra los usuarios"><i class="ti ti-lock"></i> Solo lectura</span>
        </div>
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                @foreach([
                    'Nombre' => $usuario->name,
                    'Usuario' => $usuario->username,
                    'Correo' => $usuario->email,
                    'Rol' => $rol ? ucfirst($rol) : null,
                    'Teléfono' => $usuario->phone,
                    'Sede' => $sede,
                    'Documento' => trim(($usuario->document_type ?? '').' '.($usuario->document_number ?? '')) ?: null,
                ] as $etiqueta => $valor)
                    <div class="perm-row">
                        <div class="perm-col-title">{{ $etiqueta }}</div>
                        <div class="perm-col-controls">
                            <input type="text" class="form-control form-control-sm" value="{{ $valor ?: '—' }}" readonly disabled>
                        </div>
                    </div>
                @endforeach
            </div>
            <small class="text-muted d-block mt-2"><i class="ti ti-info-circle"></i> Estos datos solo los modifica quien administra los usuarios.</small>
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
                <div class="d-flex flex-column flex-md-row gap-2 align-items-md-start" x-data x-on:perfil-sesiones-cerradas.window="$refs.clave.value = ''">
                    <div style="max-width:320px;">
                        <input type="password" class="form-control form-control-sm @error('passwordSesiones') is-invalid @enderror" x-ref="clave" name="password_sesiones"
                               wire:keydown.enter="cerrarOtrasSesiones($event.target.value)" autocomplete="current-password" placeholder="Tu contraseña, para confirmar">
                        @error('passwordSesiones') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="cerrarOtrasSesiones($refs.clave.value)" wire:loading.attr="disabled" wire:target="cerrarOtrasSesiones"
                            @if($sesiones->where('actual', false)->isEmpty()) disabled title="No hay otras sesiones abiertas" @endif>
                        <i class="ti ti-logout"></i> Cerrar las otras sesiones
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
