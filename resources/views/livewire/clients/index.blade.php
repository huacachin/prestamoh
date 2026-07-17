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
                        <div class="d-flex gap-2 mb-2">
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="2">TOTAL</td>
                                    <td colspan="12"></td>
                                    <td class="text-center fw-bold">{{ $clients->count() }}</td>
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

<span id="final"></span>

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
</style>
</div>
