{{-- 16/10 (Antony): "otro look and feel más moderno": indicadores arriba, barra de búsqueda,
     una tarjeta por usuario con avatar y estado, sesiones como filas con icono y los inicios de
     sesión como línea de tiempo. Se refresca solo cada 30 s (wire:poll). --}}
<div class="container-fluid monitoreo-sesiones" wire:poll.30s>
    <style>
        .monitoreo-sesiones { --mon-radius: 14px; --mon-sombra: 0 6px 18px rgba(16, 24, 40, .06); --mon-borde: #eceff3; }
        .monitoreo-sesiones .mon-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .monitoreo-sesiones .mon-kpi { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid var(--mon-borde); border-radius: var(--mon-radius); box-shadow: var(--mon-sombra); padding: 14px 16px; }
        .monitoreo-sesiones .mon-kpi-ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 22px; flex-shrink: 0; }
        .monitoreo-sesiones .mon-kpi-num { font-size: 26px; font-weight: 700; line-height: 1; color: #1b2430; }
        .monitoreo-sesiones .mon-kpi-label { font-size: 12px; color: #6b7280; margin-top: 3px; }
        .monitoreo-sesiones .mon-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--mon-borde); border-radius: var(--mon-radius); box-shadow: var(--mon-sombra); padding: 10px 14px; margin-bottom: 16px; }
        .monitoreo-sesiones .mon-buscar { position: relative; flex: 1 1 220px; max-width: 360px; }
        .monitoreo-sesiones .mon-buscar i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #9aa3af; }
        .monitoreo-sesiones .mon-buscar input { padding-left: 32px; border-radius: 10px; }
        .monitoreo-sesiones .mon-usuario { background: #fff; border: 1px solid var(--mon-borde); border-radius: var(--mon-radius); box-shadow: var(--mon-sombra); margin-bottom: 16px; overflow: hidden; }
        .monitoreo-sesiones .mon-cab { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--mon-borde); }
        .monitoreo-sesiones .mon-avatar { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 15px; color: #fff; background: linear-gradient(135deg, #6b7280, #9aa3af); flex-shrink: 0; position: relative; }
        .monitoreo-sesiones .mon-avatar.en-linea { background: linear-gradient(135deg, #16a34a, #4ade80); }
        .monitoreo-sesiones .mon-avatar.conectado { background: linear-gradient(135deg, #d97706, #fbbf24); }
        .monitoreo-sesiones .mon-avatar .mon-punto { position: absolute; right: -1px; bottom: -1px; width: 13px; height: 13px; border-radius: 50%; border: 2px solid #fff; background: #adb5bd; }
        .monitoreo-sesiones .mon-avatar .mon-punto.activa { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .25); }
        .monitoreo-sesiones .mon-nombre { font-weight: 700; font-size: 15px; color: #1b2430; }
        .monitoreo-sesiones .mon-sub { font-size: 12px; color: #6b7280; }
        .monitoreo-sesiones .mon-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: #f1f3f6; color: #374151; }
        .monitoreo-sesiones .mon-chip.verde { background: #dcfce7; color: #166534; }
        .monitoreo-sesiones .mon-chip.ambar { background: #fef3c7; color: #92400e; }
        .monitoreo-sesiones .mon-chip.rojo { background: #fee2e2; color: #991b1b; }
        .monitoreo-sesiones .mon-chip.gris { background: #f1f3f6; color: #6b7280; }
        .monitoreo-sesiones .mon-cuerpo { display: grid; grid-template-columns: 1fr; gap: 0; }
        .monitoreo-sesiones .mon-col { padding: 12px 16px; min-width: 0; }
        .monitoreo-sesiones .mon-col + .mon-col { border-top: 1px solid var(--mon-borde); }
        .monitoreo-sesiones .mon-sesiones { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 8px; }
        .monitoreo-sesiones .mon-ip { display: inline-flex; align-items: center; gap: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--ip-bg); border: 1px solid var(--ip-bd); color: var(--ip-tx); white-space: nowrap; }
        .monitoreo-sesiones .mon-inicios-lista { max-height: 230px; overflow: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 4px 14px; padding: 8px 10px; border: 1px solid var(--mon-borde); border-radius: 10px; background: #fafbfc; }
        .monitoreo-sesiones .mon-inicio { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #374151; min-width: 0; padding: 2px 0; }
        .monitoreo-sesiones .mon-inicio .mon-fecha { font-weight: 600; color: #1b2430; white-space: nowrap; }
        .monitoreo-sesiones .mon-inicio .mon-dato { color: #6b7280; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .monitoreo-sesiones .mon-titulo { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; margin-bottom: 8px; }
        .monitoreo-sesiones .mon-sesion { display: flex; align-items: center; gap: 12px; padding: 9px 10px; border-radius: 10px; border: 1px solid var(--mon-borde); background: #fafbfc; min-width: 0; }
        .monitoreo-sesiones .mon-sesion.actual { background: #f0fdf4; border-color: #bbf7d0; }
        .monitoreo-sesiones .mon-dev { width: 38px; height: 38px; border-radius: 10px; background: #fff; border: 1px solid var(--mon-borde); display: grid; place-items: center; font-size: 19px; color: #374151; flex-shrink: 0; }
        .monitoreo-sesiones .mon-sesion-txt { flex: 1 1 auto; min-width: 0; }
        .monitoreo-sesiones .mon-sesion-l1 { font-size: 13px; font-weight: 600; color: #1b2430; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .monitoreo-sesiones .mon-sesion-l2 { font-size: 12px; color: #6b7280; display: flex; flex-wrap: wrap; gap: 4px 10px; }
        .monitoreo-sesiones .mon-vivo { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); display: inline-block; }
        .monitoreo-sesiones .mon-dormida { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; display: inline-block; }
        .monitoreo-sesiones .mon-cerrar { border-radius: 8px; font-size: 12px; white-space: nowrap; }
        .monitoreo-sesiones .mon-vacio { font-size: 12px; color: #9aa3af; padding: 10px; border: 1px dashed var(--mon-borde); border-radius: 10px; text-align: center; }
        @media (max-width: 575.98px) {
            .monitoreo-sesiones .mon-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .monitoreo-sesiones .mon-kpi-num { font-size: 22px; }
            .monitoreo-sesiones .mon-cerrar span { display: none; }
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

    {{-- Indicadores --}}
    <div class="mon-kpis">
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#dcfce7;color:#166534;"><i class="ti ti-users"></i></div>
            <div><div class="mon-kpi-num">{{ $usuariosConSesion }}</div><div class="mon-kpi-label">usuario(s) con sesión abierta</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#e0e7ff;color:#3730a3;"><i class="ti ti-devices"></i></div>
            <div><div class="mon-kpi-num">{{ $totalSesiones }}</div><div class="mon-kpi-label">sesión(es) en total</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#d1fae5;color:#065f46;"><i class="ti ti-activity"></i></div>
            <div><div class="mon-kpi-num">{{ $activasAhora }}</div><div class="mon-kpi-label">con actividad en {{ \App\Support\SesionesActivas::MINUTOS_ACTIVA }} min</div></div>
        </div>
        <div class="mon-kpi">
            <div class="mon-kpi-ico" style="background:#fee2e2;color:#991b1b;"><i class="ti ti-shield-x"></i></div>
            <div><div class="mon-kpi-num">{{ $fallidosSemana }}</div><div class="mon-kpi-label">intento(s) fallido(s) en 7 días</div></div>
        </div>
    </div>

    {{-- Barra --}}
    <div class="mon-toolbar">
        <div class="mon-buscar">
            <i class="ti ti-search"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Buscar usuario o nombre…" wire:model.live.debounce.400ms="buscar">
        </div>
        <div class="form-check form-switch mb-0 small">
            <input class="form-check-input" type="checkbox" id="soloConSesion" wire:model.live="soloConSesion">
            <label class="form-check-label" for="soloConSesion">Solo con sesión abierta</label>
        </div>
        <span class="ms-auto text-muted small"><i class="ti ti-refresh"></i> Se actualiza solo cada 30 s · <span class="mon-vivo"></span> actividad reciente</span>
    </div>

    @forelse($bloques as $b)
        @php
            $u = $b['usuario'];
            $n = $b['sesiones']->count();
            $ajenas = $b['sesiones']->where('actual', false)->count();
            $enLinea = $b['sesiones']->contains(fn ($s) => $s['activa']);
            $estado = $enLinea ? 'en-linea' : ($n ? 'conectado' : 'sin-sesion');
            $iniciales = mb_strtoupper(mb_substr((string) $u->username, 0, 2));
            $ultima = $b['sesiones']->first()['ultima'] ?? null;
        @endphp
        <div class="mon-usuario" wire:key="usuario-{{ $u->id }}">
            <div class="mon-cab">
                <div class="mon-avatar {{ $estado === 'sin-sesion' ? '' : $estado }}">
                    {{ $iniciales }}
                    <span class="mon-punto {{ $enLinea ? 'activa' : '' }}"></span>
                </div>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="mon-nombre">{{ $u->username }}</span>
                        @if($b['rol'])<span class="mon-chip">{{ ucfirst($b['rol']) }}</span>@endif
                        @if($u->status !== 'active')<span class="mon-chip ambar"><i class="ti ti-user-off"></i> Cesado</span>@endif
                        @if($b['fallidos'] > 0)
                            <span class="mon-chip rojo" title="Intentos de inicio de sesión fallidos en los últimos 7 días"><i class="ti ti-alert-triangle"></i> {{ $b['fallidos'] }} fallido(s)</span>
                        @endif
                    </div>
                    <div class="mon-sub">{{ $u->name }}@if($ultima) · última actividad {{ $ultima->diffForHumans() }}@endif</div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if($estado === 'en-linea')
                        <span class="mon-chip verde"><span class="mon-vivo"></span> En línea</span>
                    @elseif($estado === 'conectado')
                        <span class="mon-chip ambar"><i class="ti ti-clock"></i> Conectado, sin actividad</span>
                    @else
                        <span class="mon-chip gris"><i class="ti ti-plug-off"></i> Sin sesión</span>
                    @endif
                    <span class="mon-chip">{{ $n }} sesión(es) abierta(s)</span>
                    @if($ajenas > 0)
                        <button type="button" class="btn btn-sm btn-outline-danger mon-cerrar" wire:click="cerrarTodas({{ $u->id }})"
                                data-confirmar="¿Cerrar {{ $ajenas === 1 ? 'la sesión' : "las {$ajenas} sesiones" }} de <b>{{ e($u->username) }}</b>?<br><small>Tendrá que volver a iniciar sesión.</small>"
                                data-confirmar-ok="Sí, cerrar" data-confirmar-titulo="Cerrar sesiones">
                            <i class="ti ti-logout"></i> <span>Cerrar {{ $ajenas === 1 ? 'su sesión' : 'todas sus sesiones' }}</span>
                        </button>
                    @endif
                </div>
            </div>
            <div class="mon-cuerpo">
                {{-- Sesiones abiertas --}}
                <div class="mon-col">
                    <div class="mon-titulo"><i class="ti ti-devices"></i> Sesiones abiertas</div>
                    @if($n === 0)
                        <div class="mon-vacio">Sin sesiones abiertas.</div>
                    @else
                        <div class="mon-sesiones">
                        @foreach($b['sesiones'] as $s)
                            <div class="mon-sesion {{ $s['actual'] ? 'actual' : '' }}" wire:key="sesion-{{ md5($s['id']) }}">
                                <div class="mon-dev"><i class="ti {{ $s['agente']['dispositivo'] === 'Celular' ? 'ti-device-mobile' : ($s['agente']['dispositivo'] === 'Tablet' ? 'ti-device-tablet' : 'ti-device-desktop') }}"></i></div>
                                <div class="mon-sesion-txt">
                                    <div class="mon-sesion-l1">
                                        <span class="{{ $s['activa'] ? 'mon-vivo' : 'mon-dormida' }}" title="{{ $s['activa'] ? 'Con actividad ahora' : 'Sin actividad reciente' }}"></span>
                                        {{ $s['agente']['dispositivo'] }} · {{ $s['agente']['plataforma'] }}
                                        <span class="text-muted fw-normal">· {{ $s['agente']['navegador'] }}</span>
                                        @if($s['actual'])<span class="mon-chip verde">Esta sesión</span>@endif
                                    </div>
                                    <div class="mon-sesion-l2">
                                        <span class="mon-ip" style="{{ \App\Support\ColorDeIp::estilo($paleta[$s['ip']] ?? null) }}"><i class="ti ti-world"></i> {{ $s['ip'] ?: '—' }}</span>
                                        <span title="{{ $s['ultima']->format('d/m/Y H:i:s') }}"><i class="ti ti-clock"></i> {{ $s['ultima']->diffForHumans() }}</span>
                                    </div>
                                </div>
                                @unless($s['actual'])
                                    <button type="button" class="btn btn-sm btn-outline-danger mon-cerrar" wire:click="cerrarSesion('{{ $s['id'] }}')"
                                            data-confirmar="¿Cerrar esta sesión de <b>{{ e($u->username) }}</b>?<br><small>{{ e($s['agente']['texto']) }} · IP {{ e($s['ip'] ?? '—') }}</small>"
                                            data-confirmar-ok="Sí, cerrar" data-confirmar-titulo="Cerrar sesión" title="Cerrar esta sesión">
                                        <i class="ti ti-logout"></i> <span>Cerrar</span>
                                    </button>
                                @endunless
                            </div>
                        @endforeach
                        </div>
                    @endif
                </div>

                {{-- Inicios de sesión (auditoría): TODOS, en un recuadro con scroll, y cada IP distinta con su color.
                     16/10 (Antony): "hay que listar todos… y pintar las IPs de color distinto si son diferentes". --}}
                <div class="mon-col mon-inicios">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="mon-titulo mb-0"><i class="ti ti-login"></i> Inicios de sesión <span class="mon-chip">{{ $b['inicios']->count() }}</span></div>
                        @if($b['ips']->isNotEmpty())
                            <span class="mon-sub">· {{ $b['ips']->count() }} IP(s) distinta(s):</span>
                            @foreach($b['ips'] as $ip => $veces)
                                <span class="mon-ip" style="{{ \App\Support\ColorDeIp::estilo($paleta[$ip] ?? null) }}" title="{{ $veces }} inicio(s) desde esta IP">{{ $ip }} <b>×{{ $veces }}</b></span>
                            @endforeach
                        @endif
                    </div>
                    @if($b['inicios']->isEmpty())
                        <div class="mon-vacio">Sin inicios de sesión registrados.</div>
                    @else
                        <div class="mon-inicios-lista">
                            @foreach($b['inicios'] as $i)
                                <div class="mon-inicio">
                                    <span class="mon-fecha">{{ $i['fecha']->format('d/m/Y H:i') }}</span>
                                    <span class="mon-dato">{{ $i['agente'] }}</span>
                                    <span class="mon-ip" style="{{ \App\Support\ColorDeIp::estilo($i['ip'] ? ($paleta[$i['ip']] ?? null) : null) }}">{{ $i['ip'] ?: '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="mon-usuario"><div class="mon-col mon-vacio">No hay usuarios que coincidan.</div></div>
    @endforelse
</div>
