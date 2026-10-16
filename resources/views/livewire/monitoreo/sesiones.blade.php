<div class="container-fluid monitoreo-sesiones">
    <style>
        .monitoreo-sesiones .tabla-mini { font-size: 12px; margin-bottom: 0; }
        .monitoreo-sesiones .punto { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
        .monitoreo-sesiones .punto-activa { background: #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,.2); }
        .monitoreo-sesiones .punto-inactiva { background: #adb5bd; }
        .monitoreo-sesiones .cabecera-usuario { background: #fcfcfa; }
        @media (max-width: 575.98px) {
            .monitoreo-sesiones .tabla-apilable thead { display: none; }
            .monitoreo-sesiones .tabla-apilable, .monitoreo-sesiones .tabla-apilable tbody, .monitoreo-sesiones .tabla-apilable tr, .monitoreo-sesiones .tabla-apilable td { display: block; width: 100%; }
            .monitoreo-sesiones .tabla-apilable tr { border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 8px; padding: 4px 8px; background: #fff; }
            .monitoreo-sesiones .tabla-apilable td { border: 0 !important; padding: 2px 0 !important; text-align: left !important; white-space: normal !important; }
            .monitoreo-sesiones .tabla-apilable td[data-label]::before { content: attr(data-label) ": "; font-weight: 600; color: #6c757d; }
        }
    </style>

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">MONITOREO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-devices f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Seguridad</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Sesiones de los usuarios</span></li>
            </ul>
        </div>
    </div>

    @if(! $disponibles)
        <div class="alert alert-warning py-2 small">
            <i class="ti ti-alert-triangle"></i> Este servidor guarda las sesiones en archivos: el monitoreo necesita que se guarden
            en la base de datos (<code>SESSION_DRIVER=database</code>).
        </div>
    @endif

    {{-- Resumen y filtros --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-success f-s-12"><i class="ti ti-users"></i> {{ $usuariosConSesion }} usuario(s) con sesión abierta</span>
                <span class="badge bg-dark f-s-12"><i class="ti ti-devices"></i> {{ $totalSesiones }} sesión(es) en total</span>
                <span class="text-muted small"><span class="punto punto-activa"></span> con actividad en los últimos {{ \App\Support\SesionesActivas::MINUTOS_ACTIVA }} min</span>
                <div class="ms-md-auto d-flex flex-wrap align-items-center gap-2">
                    <div class="form-check form-switch mb-0 small">
                        <input class="form-check-input" type="checkbox" id="soloConSesion" wire:model.live="soloConSesion">
                        <label class="form-check-label" for="soloConSesion">Solo con sesión abierta</label>
                    </div>
                    <input type="search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Buscar usuario…" wire:model.live.debounce.400ms="buscar">
                </div>
            </div>
        </div>
    </div>

    @forelse($bloques as $b)
        @php $u = $b['usuario']; $n = $b['sesiones']->count(); $ajenas = $b['sesiones']->where('actual', false)->count(); @endphp
        <div class="card shadow-sm mb-3" wire:key="usuario-{{ $u->id }}">
            {{-- Título: usuario --}}
            <div class="card-header py-2 cabecera-usuario">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <i class="ti ti-user-circle f-s-22 {{ $n ? 'text-success' : 'text-muted' }}"></i>
                    <div class="flex-grow-1">
                        <span class="fw-bold f-s-15">{{ $u->username }}</span>
                        <span class="text-muted">· {{ $u->name }}</span>
                        @if($b['rol'])<span class="badge bg-secondary ms-1">{{ ucfirst($b['rol']) }}</span>@endif
                        @if($u->status !== 'active')<span class="badge bg-warning text-dark ms-1">Cesado</span>@endif
                        @if($b['fallidos'] > 0)
                            <span class="badge bg-danger ms-1" title="Intentos de inicio de sesión fallidos en los últimos 7 días"><i class="ti ti-alert-triangle"></i> {{ $b['fallidos'] }} fallido(s)</span>
                        @endif
                    </div>
                    <span class="badge {{ $n ? 'bg-success' : 'bg-light text-dark border' }}">{{ $n }} sesión(es) abierta(s)</span>
                    @if($ajenas > 0)
                        <button type="button" class="btn btn-sm btn-outline-danger py-0" wire:click="cerrarTodas({{ $u->id }})"
                                data-confirmar="¿Cerrar {{ $ajenas === 1 ? 'la sesión' : "las {$ajenas} sesiones" }} de <b>{{ e($u->username) }}</b>?<br><small>Tendrá que volver a iniciar sesión.</small>"
                                data-confirmar-ok="Sí, cerrar" data-confirmar-titulo="Cerrar sesiones">
                            <i class="ti ti-logout"></i> Cerrar {{ $ajenas === 1 ? 'su sesión' : 'todas sus sesiones' }}
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-3">
                    {{-- Sesiones activas --}}
                    <div class="col-12 col-xl-7">
                        <div class="small fw-semibold mb-1"><i class="ti ti-devices"></i> Sesiones abiertas</div>
                        @if($n === 0)
                            <div class="text-muted small">Sin sesiones abiertas.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle tabla-mini tabla-apilable">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Dispositivo</th>
                                            <th>Navegador</th>
                                            <th>IP</th>
                                            <th>Última actividad</th>
                                            <th width="90"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($b['sesiones'] as $s)
                                        <tr wire:key="sesion-{{ md5($s['id']) }}" @class(['table-success' => $s['actual']])>
                                            <td data-label="Dispositivo">
                                                <span class="punto {{ $s['activa'] ? 'punto-activa' : 'punto-inactiva' }}" title="{{ $s['activa'] ? 'Con actividad ahora' : 'Sin actividad reciente' }}"></span>
                                                <i class="ti {{ $s['agente']['dispositivo'] === 'Celular' ? 'ti-device-mobile' : ($s['agente']['dispositivo'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }}"></i>
                                                {{ $s['agente']['dispositivo'] }} · {{ $s['agente']['plataforma'] }}
                                            </td>
                                            <td data-label="Navegador">{{ $s['agente']['navegador'] }}</td>
                                            <td data-label="IP" class="font-monospace">{{ $s['ip'] ?: '—' }}</td>
                                            <td data-label="Última actividad" style="white-space:nowrap;" title="{{ $s['ultima']->format('d/m/Y H:i:s') }}">{{ $s['ultima']->diffForHumans() }}</td>
                                            <td class="text-end">
                                                @if($s['actual'])
                                                    <span class="badge bg-success">Esta sesión</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0" style="font-size:11px;"
                                                            wire:click="cerrarSesion('{{ $s['id'] }}')"
                                                            data-confirmar="¿Cerrar esta sesión de <b>{{ e($u->username) }}</b>?<br><small>{{ e($s['agente']['texto']) }} · IP {{ e($s['ip'] ?? '—') }}</small>"
                                                            data-confirmar-ok="Sí, cerrar" data-confirmar-titulo="Cerrar sesión">
                                                        <i class="ti ti-logout"></i> Cerrar
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Últimos inicios de sesión (auditoría) --}}
                    <div class="col-12 col-xl-5">
                        <div class="small fw-semibold mb-1"><i class="ti ti-login"></i> Últimos inicios de sesión</div>
                        @if($b['inicios']->isEmpty())
                            <div class="text-muted small">Sin inicios de sesión registrados.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle tabla-mini tabla-apilable">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Dispositivo</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($b['inicios'] as $i)
                                        <tr>
                                            <td data-label="Fecha" style="white-space:nowrap;">{{ $i['fecha']->format('d/m/Y H:i') }}</td>
                                            <td data-label="Dispositivo">{{ $i['agente'] }}</td>
                                            <td data-label="IP" class="font-monospace">{{ $i['ip'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card shadow-sm"><div class="card-body text-muted small">No hay usuarios que coincidan.</div></div>
    @endforelse
</div>
