<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CLIENTES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Cliente</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Expediente</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="nexpediente" name="nexpediente" autocomplete="off" placeholder="Numero Expediente">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>DNI</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="documento" name="documento" autocomplete="off" placeholder="DNI">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Nombre</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="nombre" name="nombre" autocomplete="off" placeholder="Nombres">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>T.Credito</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="ruta" name="ruta" autocomplete="off" placeholder="Ruta">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Giro</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="giro" name="giro" autocomplete="off" placeholder="Giro">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Asesor</b></label>
                                <select class="form-select form-select-sm" wire:model="ejecutivo">
                                    <option value="">Todos</option>
                                    <option value="Ninguno">Sin Asesor</option>
                                    @foreach($asesores as $asesor)
                                        <option value="{{ $asesor->id }}">{{ $asesor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2 align-items-center flex-wrap">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            <a href="{{ route('exports.clients') }}?nexpediente={{ $nexpediente }}&documento={{ $documento }}&nombre={{ $nombre }}&ruta={{ $ruta }}&giro={{ $giro }}&ejecutivo={{ $ejecutivo }}"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                                <x-scroll-bottom-btn />
                            <a href="{{ route('clients.create') }}" class="btn btn-sm btn-danger">
                                <i class="ti ti-user-plus f-s-12"></i> Nuevo Cliente
                            </a>

                            {{-- Chips de morosidad: click filtra, click de nuevo lo quita --}}
                            <div class="moros-chips ms-md-2" role="group" aria-label="Filtro de morosidad">
                                <button type="button"
                                        class="moros-chip moros-chip--verde {{ $morosidadFiltro === 'aldia' ? 'is-active' : '' }}"
                                        wire:click="filtrarMorosidad('aldia')"
                                        title="Clientes sin morosidad relevante">
                                    <span class="dot"></span> Al día <span class="n">{{ $countAldia }}</span>
                                </button>
                                <button type="button"
                                        class="moros-chip moros-chip--naranja {{ $morosidadFiltro === 'naranja' ? 'is-active' : '' }}"
                                        wire:click="filtrarMorosidad('naranja')"
                                        title="Algún crédito activo con 2 cuotas vencidas">
                                    <span class="dot"></span> 2 vencidas <span class="n">{{ $countNaranja }}</span>
                                </button>
                                <button type="button"
                                        class="moros-chip moros-chip--rojo {{ $morosidadFiltro === 'rojo' ? 'is-active' : '' }}"
                                        wire:click="filtrarMorosidad('rojo')"
                                        title="Algún crédito activo con 3 o más cuotas vencidas">
                                    <span class="dot"></span> 3+ vencidas <span class="n">{{ $countRojo }}</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Tabla Desktop / Cards Mobile --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover table-autofit clients-legacy">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center">N&deg;</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center">Exp.</th>
                                    <th class="text-center col-wrap">Apellidos y Nombres</th>
                                    <th class="text-center">DNI</th>
                                    <th class="text-center">Movil</th>
                                    <th class="text-center">T.Credito</th>
                                    <th class="text-center">Giro</th>
                                    <th class="text-center">Asesor</th>
                                    <th class="text-center" colspan="3">Opciones</th>
                                    <th class="text-center">C.</th>
                                    <th class="text-center">N.</th>
                                    <th class="text-center" title="Recordatorio WhatsApp (morosos)"><i class="ti ti-brand-whatsapp"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($clients as $client)
                                @php
                                    $hasCredit = isset($clientsWithCredit[$client->id]);
                                    $textColor = $hasCredit ? 'inherit' : '#dc3545';
                                    // Morosidad del peor crédito activo: 2 cuotas vencidas → naranja, 3+ → rojo
                                    $venc = $morosidad[$client->id] ?? 0;
                                    $rowBg = $venc >= 3 ? '#ff6b6b' : ($venc === 2 ? '#ffc078' : '');
                                @endphp
                                <tr style="color: {{ $textColor }};{{ $rowBg !== '' ? " background-color: {$rowBg};" : '' }}"
                                    data-bg="{{ $rowBg }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=this.getAttribute('data-bg')">
                                    <td class="text-center" style="color: inherit;">{{ $loop->iteration }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->fecha_registro?->format('Y-m-d') }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->usuario }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->expediente }}</td>
                                    <td class="col-wrap" style="color: inherit;">
                                        <a href="{{ route('clients.edit', $client->id) }}" style="color: black; text-decoration: none;">
                                            {{ $client->apellido_pat }} {{ $client->apellido_mat }} {{ $client->nombre }}
                                        </a>
                                    </td>
                                    <td style="color: inherit;">
                                        <a href="{{ route('credits.create', $client->id) }}" style="color: inherit; text-decoration: none;">
                                            {{ $client->documento }}
                                        </a>
                                    </td>
                                    <td style="color: inherit;">{{ $client->celular1 }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->zona }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->giro }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->asesor?->username ?? $client->asesor?->name }}</td>
                                    <td class="text-center text-nowrap">
                                        <a href="{{ route('clients.show', $client->id) }}"
                                           class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">
                                            Prestamo
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('clients.aval', $client->id) }}"
                                           class="btn btn-xs {{ ($client->avales_count ?? 0) > 0 ? 'btn-primary' : 'btn-danger' }}" style="padding: 2px 8px; font-size: 10px;">
                                            Aval
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('clients.gallery', $client->id) }}"
                                           class="btn btn-xs {{ ($client->attachments_count ?? 0) > 0 ? 'btn-info' : 'btn-danger' }}" style="padding: 2px 8px; font-size: 10px;">
                                            Adjuntos
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        @if($client->latitud && $client->longitud)
                                            <a href="https://maps.google.com/?q={{ $client->latitud }},{{ $client->longitud }}" target="_blank">
                                                <i class="ti ti-map-pin f-s-18 text-success"></i>
                                            </a>
                                        @elseif($puedeCoords)
                                            <i class="ti ti-map-pin-off f-s-18 text-danger" style="cursor:pointer;"
                                               title="Agregar coordenadas de Casa"
                                               wire:click="openCoord({{ $client->id }}, 'casa')"></i>
                                        @else
                                            <i class="ti ti-map-pin-off f-s-18 text-danger"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($client->latitud2 && $client->longitud2)
                                            <a href="https://maps.google.com/?q={{ $client->latitud2 }},{{ $client->longitud2 }}" target="_blank">
                                                <i class="ti ti-map-pin f-s-18 text-success"></i>
                                            </a>
                                        @elseif($puedeCoords)
                                            <i class="ti ti-map-pin-off f-s-18 text-danger" style="cursor:pointer;"
                                               title="Agregar coordenadas de Negocio"
                                               wire:click="openCoord({{ $client->id }}, 'negocio')"></i>
                                        @else
                                            <i class="ti ti-map-pin-off f-s-18 text-danger"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php $waTel = preg_replace('/\D/', '', (string) $client->celular1); @endphp
                                        @if($venc >= 2 && $waTel !== '')
                                            <a href="#" wire:click.prevent="abrirNotifs({{ $client->id }})"
                                               title="Notificaciones WhatsApp ({{ $venc }} cuotas vencidas)">
                                                <i class="ti ti-brand-whatsapp f-s-16 text-success"></i>
                                            </a>
                                            @if(isset($waEnviadosHoy[$client->id]))
                                                <i class="ti ti-checks f-s-14" style="color:#2eb85c;" title="Notificación ya enviada hoy"></i>
                                            @endif
                                        @else
                                            <i class="ti ti-brand-whatsapp f-s-16" style="color:#c9ced6;"
                                               title="{{ $venc < 2 ? 'Cliente al día — notificaciones deshabilitadas' : 'Sin número de celular' }}"></i>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="2">TOTAL</td>
                                    <td colspan="12"></td>
                                    <td class="text-center fw-bold">{{ $clients->count() }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($clients as $client)
                            @php
                                $hasCredit = isset($clientsWithCredit[$client->id]);
                            @endphp
                            <div class="card mb-2 shadow-sm {{ !$hasCredit ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ !$hasCredit ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('clients.edit', $client->id) }}" style="{{ !$hasCredit ? 'color: red;' : 'color: black;' }}">
                                                {{ $client->apellido_pat }} {{ $client->apellido_mat }} {{ $client->nombre }}
                                            </a>
                                        </h6>
                                        <span class="badge bg-secondary">#{{ $loop->iteration }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>DNI:</b>
                                            <a href="{{ route('credits.create', $client->id) }}">{{ $client->documento }}</a>
                                        </div>
                                        <div class="col-6"><b>Exp.:</b> {{ $client->expediente }}</div>
                                        <div class="col-6"><b>Movil:</b> {{ $client->celular1 }}</div>
                                        <div class="col-6"><b>T.Credito:</b> {{ $client->zona }}</div>
                                        <div class="col-6"><b>Giro:</b> {{ $client->giro }}</div>
                                        <div class="col-6"><b>Asesor:</b> {{ $client->asesor?->username ?? $client->asesor?->name }}</div>
                                        <div class="col-6"><b>Fecha:</b> {{ $client->fecha_registro?->format('Y-m-d') }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $client->usuario }}</div>
                                        <div class="col-6">
                                            @if($client->latitud && $client->longitud)
                                                <a href="https://maps.google.com/?q={{ $client->latitud }},{{ $client->longitud }}" target="_blank">
                                                    <i class="ti ti-map-pin f-s-14 text-success"></i> Casa
                                                </a>
                                            @elseif($puedeCoords)
                                                <span style="cursor:pointer;" wire:click="openCoord({{ $client->id }}, 'casa')">
                                                    <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Casa
                                                </span>
                                            @else
                                                <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Casa
                                            @endif
                                        </div>
                                        <div class="col-6">
                                            @if($client->latitud2 && $client->longitud2)
                                                <a href="https://maps.google.com/?q={{ $client->latitud2 }},{{ $client->longitud2 }}" target="_blank">
                                                    <i class="ti ti-map-pin f-s-14 text-success"></i> Trabajo
                                                </a>
                                            @elseif($puedeCoords)
                                                <span style="cursor:pointer;" wire:click="openCoord({{ $client->id }}, 'negocio')">
                                                    <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Trabajo
                                                </span>
                                            @else
                                                <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Trabajo
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 mt-2">
                                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">Prestamo</a>
                                        <a href="{{ route('clients.aval', $client->id) }}" class="btn btn-xs {{ ($client->avales_count ?? 0) > 0 ? 'btn-primary' : 'btn-danger' }}" style="padding: 2px 8px; font-size: 10px;">Aval</a>
                                        <a href="{{ route('clients.gallery', $client->id) }}" class="btn btn-xs {{ ($client->attachments_count ?? 0) > 0 ? 'btn-info' : 'btn-danger' }}" style="padding: 2px 8px; font-size: 10px;">Adjuntos</a>
                                        @php
                                            $vencM = $morosidad[$client->id] ?? 0;
                                            $waTelM = preg_replace('/\D/', '', (string) $client->celular1);
                                        @endphp
                                        @if($vencM >= 2 && $waTelM !== '')
                                            <button type="button" wire:click="abrirNotifs({{ $client->id }})"
                                                    class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;">
                                                <i class="ti ti-brand-whatsapp"></i> WA
                                                @if(isset($waEnviadosHoy[$client->id]))<i class="ti ti-checks"></i>@endif
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-primary">Total: {{ $clients->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal: pegar/registrar coordenadas (Casa / Negocio) --}}
    <div class="modal fade" id="coordModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);
                 $el.addEventListener('shown.bs.modal', () => $refs.paste && $refs.paste.focus());"
         x-on:coord-open.window="modal.show()"
         x-on:coord-close.window="modal.hide()">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-map-pin text-success"></i>
                        Coordenadas {{ $coordTipo === 'negocio' ? 'Negocio' : 'Casa' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($coordClientName)
                        <p class="small text-muted mb-2"><b>Cliente:</b> {{ $coordClientName }}</p>
                    @endif
                    <label class="form-label small fw-semibold mb-1">Pega aquí las coordenadas</label>
                    <textarea class="form-control form-control-sm" id="coordPaste" rows="2"
                              x-ref="paste" wire:model="coordPaste"
                              placeholder="-12.014431, -76.824936"></textarea>
                    <div class="form-text">
                        Formato: <code>latitud, longitud</code>. También admite pegar un enlace de Google Maps.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-dark" wire:click="saveCoord" wire:loading.attr="disabled" wire:target="saveCoord">
                        <span wire:loading.remove wire:target="saveCoord">Guardar</span>
                        <span wire:loading wire:target="saveCoord">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal de notificaciones WhatsApp (cobranza) ═══ --}}
    <div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:notif-open.window="modal.show()"
         x-on:notif-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-brand-whatsapp text-success"></i>
                        Notificaciones — {{ $notifClientName }}
                        @if($notifVencidas > 0)
                            <span class="badge {{ $notifVencidas >= 3 ? 'bg-danger' : 'bg-warning text-dark' }}" style="font-size:10px;">
                                {{ $notifVencidas }} cuotas vencidas
                            </span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    {{-- Editor de nueva notificación --}}
                    @if($notifEditor)
                        <div class="border rounded p-2 mb-3" style="background:#f6fbf7;">
                            <label class="form-label small fw-semibold mb-1">Mensaje a enviar por WhatsApp</label>
                            <textarea class="form-control form-control-sm @error('notifTexto') is-invalid @enderror"
                                      rows="4" wire:model="notifTexto"></textarea>
                            @error('notifTexto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-success"
                                        wire:click="enviarNotif" wire:loading.attr="disabled" wire:target="enviarNotif">
                                    <i class="ti ti-brand-whatsapp"></i>
                                    <span wire:loading.remove wire:target="enviarNotif">Enviar por WhatsApp</span>
                                    <span wire:loading wire:target="enviarNotif">Guardando…</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('notifEditor', false)">Cancelar</button>
                            </div>
                            <div class="form-text">Al enviar se guarda en el historial y se abre WhatsApp con el texto listo.</div>
                        </div>
                    @else
                        <button type="button" class="btn btn-sm btn-success mb-3" wire:click="nuevaNotif">
                            <i class="ti ti-plus"></i> Nueva notificación
                        </button>
                    @endif

                    {{-- Historial --}}
                    @if($notifs->isEmpty())
                        <p class="text-muted small mb-0">Aún no se han enviado notificaciones a este cliente.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle" style="font-size:12px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th class="text-center" style="width:34px;">#</th>
                                        <th class="text-center" style="width:110px;">Enviada</th>
                                        <th>Mensaje</th>
                                        <th class="text-center" style="width:90px;">Usuario</th>
                                        <th style="width:220px;">Compromiso de pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($notifs as $n)
                                    <tr>
                                        <td class="text-center fw-bold">{{ $n->numero }}</td>
                                        <td class="text-center">
                                            {{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}
                                            @if($n->cuotas_vencidas !== null)
                                                <span class="badge {{ $n->cuotas_vencidas >= 3 ? 'bg-danger' : 'bg-warning text-dark' }} d-block mx-auto mt-1"
                                                      style="font-size: 9px; width: fit-content;"
                                                      title="Cuotas vencidas al momento del envío">
                                                    {{ $n->cuotas_vencidas }} venc.
                                                </span>
                                            @endif
                                        </td>
                                        <td style="max-width: 320px;">
                                            @if(mb_strlen($n->mensaje) > 50)
                                                <span data-bs-toggle="tooltip" data-bs-placement="top"
                                                      data-bs-custom-class="notif-tooltip"
                                                      data-bs-title="{{ $n->mensaje }}" style="cursor: help;">
                                                    {{ mb_substr($n->mensaje, 0, 50) }}…
                                                </span>
                                            @else
                                                {{ $n->mensaje }}
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $n->usuario ?? $n->usuario_name ?? '—' }}</td>
                                        <td>
                                            @if($compNotifId === $n->id)
                                                {{-- Mini-form de compromiso --}}
                                                <div class="d-flex flex-column gap-1">
                                                    <input type="date" class="form-control form-control-sm @error('compFecha') is-invalid @enderror"
                                                           wire:model="compFecha">
                                                    @error('compFecha') <div class="text-danger" style="font-size:10px;">{{ $message }}</div> @enderror
                                                    <input type="text" class="form-control form-control-sm" placeholder="Detalle (opcional)"
                                                           wire:model="compDetalle" maxlength="5000">
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-xs btn-dark" style="padding:2px 8px; font-size:10px;"
                                                                wire:click="guardarCompromiso">Guardar</button>
                                                        <button type="button" class="btn btn-xs btn-secondary" style="padding:2px 8px; font-size:10px;"
                                                                wire:click="$set('compNotifId', null)">Cancelar</button>
                                                    </div>
                                                </div>
                                            @elseif($n->compromiso_fecha)
                                                @php
                                                    $cf = \Carbon\Carbon::parse($n->compromiso_fecha);
                                                    $dias = now()->startOfDay()->diffInDays($cf->copy()->startOfDay(), false);
                                                    $cfColor = $dias <= 0 ? '#dc3545' : ($dias <= 2 ? '#fd7e14' : '#198754');
                                                @endphp
                                                <div>
                                                    @if($n->compromiso_registrado_at)
                                                        <div class="text-muted" style="font-size:10px;">
                                                            <i class="ti ti-pencil"></i> Registrado el {{ \Carbon\Carbon::parse($n->compromiso_registrado_at)->format('d/m/Y H:i') }}
                                                        </div>
                                                    @endif
                                                    <b style="color: {{ $cfColor }};"><i class="ti ti-calendar-event"></i> {{ $cf->format('d/m/Y') }}</b>
                                                    @if($n->compromiso_cumplido_at)
                                                        <span class="badge bg-success" style="font-size:9px;">cumplido</span>
                                                    @endif
                                                    @if($n->compromiso_detalle)
                                                        <div class="text-muted" style="font-size:11px;">{{ $n->compromiso_detalle }}</div>
                                                    @endif
                                                    <a href="#" wire:click.prevent="abrirCompromiso({{ $n->id }})" style="font-size:10px;">editar</a>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-dark" style="padding:2px 8px; font-size:10px;"
                                                        wire:click="abrirCompromiso({{ $n->id }})">
                                                    <i class="ti ti-calendar-plus"></i> Compromiso
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
            </div>
        </div>
    </div>

<span id="final"></span>

<script>
    document.addEventListener('livewire:init', () => {
        // Al guardar la notificación, el componente pide abrir WhatsApp con el texto.
        Livewire.on('notif-wa', (e) => {
            const url = e?.url ?? e?.[0]?.url;
            if (url) window.open(url, '_blank');
        });

        // Tooltips Bootstrap del modal (mensaje truncado a 50 chars):
        // se re-inicializan tras cada update de Livewire (el morph crea nodos nuevos).
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(() => {
                if (typeof bootstrap === 'undefined') return;
                document.querySelectorAll('#notifModal [data-bs-toggle="tooltip"]')
                    .forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
            }, 60));
        });
    });
</script>

<style>
    /* Homologado al legacy (cliente.php + ideasweb.css .tableM): fuente Tahoma
       compacta, cabecera en mayúsculas, columnas ajustadas al contenido en una
       sola línea y scroll horizontal (.table-responsive) si excede el ancho.
       Se conserva el zebra (table-striped) que también usa el legacy. */
    .clients-legacy { font-family: Tahoma, Verdana, Geneva, sans-serif; }
    .clients-legacy th, .clients-legacy td {
        padding: 3px 6px; vertical-align: middle; white-space: nowrap;
    }
    /* Apellidos y Nombres: puede ser largo → envuelve con tope, como el legacy. */
    .clients-legacy th.col-wrap, .clients-legacy td.col-wrap {
        white-space: normal; min-width: 180px; max-width: 300px;
    }
    /* Filas de morosidad (2 cuotas vencidas → naranja, 3+ → rojo) y hover:
       el fondo inline del <tr> debe ganarle al zebra, que Bootstrap pinta con
       box-shadow inset EN CADA CELDA (se superpondría al color de la fila). */
    .clients-legacy tbody tr[style*="background-color"] > * {
        box-shadow: none !important;
        background-color: inherit !important;
    }

    /* Tooltip del mensaje de notificación: conserva saltos de línea */
    .notif-tooltip .tooltip-inner {
        white-space: pre-line;
        text-align: left;
        max-width: 340px;
    }

    /* ── Chips del filtro de morosidad ── */
    .moros-chips { display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .moros-chip {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid #dee2e6; background: #fff; color: #495057;
        border-radius: 20px; padding: 3px 12px;
        font-size: 12px; font-weight: 600; line-height: 1.4;
        cursor: pointer; transition: all .15s ease;
    }
    .moros-chip:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, .14); }
    .moros-chip .dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
    .moros-chip .n {
        background: rgba(0, 0, 0, .08); border-radius: 10px;
        padding: 0 7px; font-size: 11px; font-weight: 700;
    }
    .moros-chip--verde .dot { background: #2eb85c; }
    .moros-chip--naranja .dot { background: #fd7e14; }
    .moros-chip--rojo .dot { background: #dc3545; }
    .moros-chip--verde.is-active { background: #2eb85c; border-color: #2eb85c; color: #fff; }
    .moros-chip--naranja.is-active { background: #fd7e14; border-color: #fd7e14; color: #fff; }
    .moros-chip--rojo.is-active { background: #dc3545; border-color: #dc3545; color: #fff; }
    .moros-chip.is-active .dot { background: #fff; }
    .moros-chip.is-active .n { background: rgba(255, 255, 255, .25); }
</style>
</div>
