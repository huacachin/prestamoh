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
                {{-- 16/10 (Antony): confirmación en dos pasos. El botón abre un cuadro con la contraseña (candado,
                     ojo para verla, Confirmar / Cancelar); la contraseña va como parámetro, nunca como estado.
                     ⚠️ Se llama con Alpine (x-on + $wire): en una expresión wire:click los nombres se resuelven
                     contra $wire ($wire.document, $refs del componente) y la llamada falla en silencio. --}}
                @php $otras = $sesiones->where('actual', false)->count(); @endphp
                <div x-data="{ confirmando: {{ $errors->has('passwordSesiones') ? 'true' : 'false' }}, ver: false }"
                     x-on:perfil-sesiones-cerradas.window="confirmando = false; ver = false; $refs.clave.value = ''">
                    <button type="button" class="btn btn-outline-danger btn-sm" x-show="!confirmando"
                            x-on:click="confirmando = true; $nextTick(() => $refs.clave.focus())"
                            @if($otras === 0) disabled title="No hay otras sesiones abiertas" @endif>
                        <i class="ti ti-logout"></i> Cerrar las otras sesiones
                    </button>
                    <div class="border rounded p-2 confirmar-cierre-sesiones" style="max-width:520px; background:#fcfcfa;" x-show="confirmando" x-cloak>
                        <label for="clave-cerrar-sesiones" class="form-label small fw-semibold mb-1">
                            <i class="ti ti-shield-lock"></i> Confirma con tu contraseña para cerrar {{ $otras === 1 ? 'la otra sesión' : "las otras {$otras} sesiones" }}
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                            <input :type="ver ? 'text' : 'password'" id="clave-cerrar-sesiones" class="form-control @error('passwordSesiones') is-invalid @enderror"
                                   x-ref="clave" name="password_sesiones" autocomplete="current-password" placeholder="Tu contraseña"
                                   x-on:keydown.enter="$wire.cerrarOtrasSesiones($event.target.value)">
                            <button type="button" class="btn btn-outline-secondary" x-on:click="ver = !ver" :title="ver ? 'Ocultar' : 'Mostrar'">
                                <i class="ti" :class="ver ? 'ti-eye-off' : 'ti-eye'"></i>
                            </button>
                        </div>
                        @error('passwordSesiones') <div class="text-danger small mt-1"><i class="ti ti-alert-circle"></i> {{ $message }}</div> @enderror
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-danger btn-sm" x-on:click="$wire.cerrarOtrasSesiones($refs.clave.value)"
                                    wire:loading.attr="disabled" wire:target="cerrarOtrasSesiones">
                                <i class="ti ti-logout"></i>
                                <span wire:loading.remove wire:target="cerrarOtrasSesiones">Cerrar las otras sesiones</span>
                                <span wire:loading wire:target="cerrarOtrasSesiones">Cerrando…</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" x-on:click="confirmando = false; ver = false; $refs.clave.value = ''">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">Esta sesión seguirá abierta; las demás tendrán que volver a iniciar sesión.</small>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
