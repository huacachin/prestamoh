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
                                       wire:model="nexpediente" name="nexpediente" autocomplete="on" list="clients_expediente_hist" data-search-history="clients_expediente" placeholder="Numero Expediente">
                                <datalist id="clients_expediente_hist" wire:ignore></datalist>
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>DNI</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="documento" name="documento" autocomplete="on" list="clients_documento_hist" data-search-history="clients_documento" placeholder="DNI">
                                <datalist id="clients_documento_hist" wire:ignore></datalist>
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Nombre</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="nombre" name="nombre" autocomplete="on" list="clients_nombre_hist" data-search-history="clients_nombre" placeholder="Nombres">
                                <datalist id="clients_nombre_hist" wire:ignore></datalist>
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>T.Credito</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="ruta" name="ruta" autocomplete="on" list="clients_ruta_hist" data-search-history="clients_ruta" placeholder="Ruta">
                                <datalist id="clients_ruta_hist" wire:ignore></datalist>
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Giro</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="giro" name="giro" autocomplete="on" list="clients_giro_hist" data-search-history="clients_giro" placeholder="Giro">
                                <datalist id="clients_giro_hist" wire:ignore></datalist>
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
                            {{-- estado = chip de morosidad activo: sin él, el Excel salía sin
                                 el filtro de al día / 2 / 3 / 4+ / ejecución. --}}
                            <a href="{{ route('exports.clients') }}?nexpediente={{ $nexpediente }}&documento={{ $documento }}&nombre={{ $nombre }}&ruta={{ $ruta }}&giro={{ $giro }}&ejecutivo={{ $ejecutivo }}&estado={{ $morosidadFiltro }}"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                                <x-scroll-bottom-btn />
                            <a href="{{ route('clients.create') }}" class="btn btn-sm btn-danger">
                                <i class="ti ti-user-plus f-s-12"></i> Nuevo Cliente
                            </a>

                            {{-- Chips de morosidad: cada uno abre una ventana nueva ya filtrada,
                                 conservando los filtros de búsqueda que estén puestos. --}}
                            @php
                                $filtrosPuestos = array_filter([
                                    'expediente' => $nexpediente,
                                    'documento'  => $documento,
                                    'nombre'     => $nombre,
                                    'ruta'       => $ruta,
                                    'giro'       => $giro,
                                    'asesor'     => $ejecutivo,
                                ], fn ($v) => $v !== '' && $v !== null);

                                // Escala de mora: comparten la palabra "vencidas", que se pone una
                                // sola vez como rótulo del grupo para no repetirla en cada chip.
                                $chips = [
                                    ['aldia',   'verde',   'Al día', $countAldia,   'Sin cuotas vencidas relevantes (0 o 1)'],
                                    ['naranja', 'naranja', '2',      $countNaranja, 'Algún crédito activo con exactamente 2 cuotas vencidas'],
                                    ['rojo',    'rojo',    '3',      $countRojo,    'Algún crédito activo con exactamente 3 cuotas vencidas'],
                                    ['critico', 'critico', '4-8',    $countCritico, 'Algún crédito activo con entre 4 y 8 cuotas vencidas'],
                                    ['masde8',  'rojo',    '+8',     $countMasde8,  'Algún crédito activo con más de 8 cuotas vencidas'],
                                ];
                            @endphp
                            <div class="moros-chips ms-md-2" role="group" aria-label="Filtro de morosidad">
                                @foreach($chips as [$estado, $color, $etiqueta, $conteo, $ayuda])
                                    <a href="{{ route('clients.index', $filtrosPuestos + ['estado' => $estado]) }}"
                                       target="_blank" rel="noopener"
                                       class="moros-chip moros-chip--{{ $color }} {{ $morosidadFiltro === $estado ? 'is-active' : '' }}"
                                       title="{{ $ayuda }} — se abre en una ventana nueva">
                                        <span class="dot"></span> {{ $etiqueta }} <span class="n">{{ $conteo }}</span>
                                    </a>
                                @endforeach
                                <span class="moros-rotulo">vencidas</span>

                                {{-- Ejecución es otro eje, no un grado más de mora: va tras un
                                     separador y sin punto de color, para que no se lea como el
                                     siguiente escalón de la escala. --}}
                                <span class="moros-sep" aria-hidden="true"></span>
                                <a href="{{ route('clients.index', $filtrosPuestos + ['estado' => 'ejecucion']) }}"
                                   target="_blank" rel="noopener"
                                   class="moros-chip moros-chip--ejecucion {{ $morosidadFiltro === 'ejecucion' ? 'is-active' : '' }}"
                                   title="Expedientes en ejecución (manos legales). No cuentan en los niveles de mora — se abre en una ventana nueva">
                                    <i class="ti ti-gavel f-s-12"></i> Ejecución <span class="n">{{ $countEjecucion }}</span>
                                </a>

                                @if($morosidadFiltro !== '')
                                    <a href="{{ route('clients.index', $filtrosPuestos) }}"
                                       class="moros-chip moros-chip--limpiar"
                                       title="Quitar el filtro de morosidad en esta ventana">
                                        <i class="ti ti-x f-s-12"></i> Quitar filtro
                                    </a>
                                @endif
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
                                    // Rojo solo si TUVO créditos y ya no le queda ninguno vigente.
                                    // Quien nunca tuvo crédito no se marca: es un caso distinto.
                                    $todoCancelado = ($estadoCreditos[$client->id] ?? 'sin') === 'cancelado';
                                    // Morosidad del peor crédito activo: 2 cuotas vencidas → naranja, 3+ → rojo
                                    $venc = $morosidad[$client->id] ?? 0;
                                    // Ejecución PREVALECE sobre la morosidad: el expediente ya está en
                                    // manos legales, así que se distingue en verde por mucha mora que
                                    // acumule. Se anota a mano en "zona" (ej. "SIGM.S-Ejecucion 08/04"),
                                    // por eso la búsqueda es laxa: sin distinguir mayúsculas ni tilde.
                                    // "ejecuc" es prefijo común de Ejecucion y Ejecución, con o sin tilde.
                                    $enEjecucion = mb_stripos((string) $client->zona, 'ejecuc') !== false;
                                    $rowBg = $enEjecucion
                                        ? '#8ce99a'
                                        : ($venc >= 3 ? '#ff6b6b' : ($venc === 2 ? '#ffc078' : ''));
                                @endphp
                                {{-- El color va por clase, no en el style de la fila: Bootstrap 5 pinta
                                     cada celda con .table > :not(caption) > * > *, y eso tapaba
                                     cualquier color puesto en el <tr>. --}}
                                <tr class="{{ $todoCancelado ? 'cliente-cancelado' : '' }}"
                                    style="{{ $rowBg !== '' ? "background-color: {$rowBg};" : '' }}"
                                    data-bg="{{ $rowBg }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=this.getAttribute('data-bg')">
                                    <td class="text-center">{{ $clients->firstItem() + $loop->index }}</td>
                                    <td class="text-center">{{ $client->fecha_registro?->format('Y-m-d') }}</td>
                                    <td class="text-center">{{ $client->usuario }}</td>
                                    <td class="text-center">{{ $client->expediente }}</td>
                                    <td class="col-wrap">
                                        {{-- color: inherit, no black: si no, el nombre se queda negro
                                             y tapa el rojo de la fila, que es justo donde se mira. --}}
                                        <a href="{{ route('clients.edit', $client->id) }}" style="color: inherit; text-decoration: none;">
                                            {{ $client->apellido_pat }} {{ $client->apellido_mat }} {{ $client->nombre }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('credits.create', $client->id) }}" style="color: inherit; text-decoration: none;">
                                            {{ $client->documento }}
                                        </a>
                                    </td>
                                    <td>{{ $client->celular1 }}</td>
                                    <td class="text-center">{{ $client->zona }}</td>
                                    <td class="text-center">{{ $client->giro }}</td>
                                    <td class="text-center">{{ $client->asesor?->username ?? $client->asesor?->name }}</td>
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
                                            <a href="#" wire:click.prevent="$dispatch('abrir-notifs', { clientId: {{ $client->id }} })"
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
                                    <td class="text-center fw-bold">{{ $totalFiltrados }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($clients as $client)
                            @php
                                $todoCancelado = ($estadoCreditos[$client->id] ?? 'sin') === 'cancelado';
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $todoCancelado ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ $todoCancelado ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('clients.edit', $client->id) }}" style="{{ $todoCancelado ? 'color: red;' : 'color: black;' }}">
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
                                            <button type="button" wire:click="$dispatch('abrir-notifs', { clientId: {{ $client->id }} })"
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
                            <span class="badge bg-primary">Total: {{ $totalFiltrados }}</span>
                        </div>
                    </div>

                    {{-- Paginación (LIMIT en SQL: solo viaja la página visible) --}}
                    <div class="mt-3">
                        {{ $clients->links() }}
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

    {{-- Modal de notificaciones (componente hijo: sus clicks no re-renderizan la lista) --}}
    <livewire:clients.notifications-modal />

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
    /* El color de fila (morosidad/sin credito) se hereda en todas las celdas
       sin repetir style=color:inherit en cada td (aligera ~40KB por pagina). */
    .clients-legacy tbody td { color: inherit; }
    /* Apellidos y Nombres: puede ser largo → envuelve con tope, como el legacy. */
    .clients-legacy th.col-wrap, .clients-legacy td.col-wrap {
        white-space: normal; min-width: 180px; max-width: 300px;
    }
    /* Filas por estado (2 vencidas → naranja, 3+ → rojo, Ejecución → verde) y hover:
       el fondo inline del <tr> debe ganarle al zebra, que Bootstrap pinta con
       box-shadow inset EN CADA CELDA (se superpondría al color de la fila). */
    .clients-legacy tbody tr[style*="background-color"] > * {
        box-shadow: none !important;
        background-color: inherit !important;
    }

    /* Cliente que ya cancelo todos sus creditos: el rojo tiene que ir sobre las
       CELDAS. Bootstrap 5 les asigna color con `.table > :not(caption) > * > *`,
       asi que un color puesto en el <tr> nunca se ve. Esta regla tiene mas
       especificidad que la suya, por eso no hace falta !important.

       Se excluyen los .btn: llevan color propio sobre fondo de color, y
       pintarlos de rojo dejaba el boton "Aval" en rojo sobre rojo. */
    .table > tbody > tr.cliente-cancelado > td,
    .table > tbody > tr.cliente-cancelado > td a:not(.btn) {
        color: #dc3545;
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
    /* Son <a>, no <button>: hay que anular el subrayado y heredar el color. */
    .moros-chip, .moros-chip:hover, .moros-chip:focus { text-decoration: none; }
    .moros-chip--verde .dot { background: #2eb85c; }
    .moros-chip--naranja .dot { background: #fd7e14; }
    .moros-chip--rojo .dot { background: #dc3545; }
    .moros-chip--critico .dot { background: #7b1e2b; }
    .moros-chip--verde.is-active { background: #2eb85c; border-color: #2eb85c; color: #fff; }
    .moros-chip--naranja.is-active { background: #fd7e14; border-color: #fd7e14; color: #fff; }
    .moros-chip--rojo.is-active { background: #dc3545; border-color: #dc3545; color: #fff; }
    .moros-chip--critico.is-active { background: #7b1e2b; border-color: #7b1e2b; color: #fff; }
    /* Ejecucion: gris pizarra, fuera de la escala rojo/naranja/verde a proposito. */
    .moros-chip--ejecucion { color: #3b4a5a; border-color: #b9c2cc; }
    .moros-chip--ejecucion.is-active { background: #3b4a5a; border-color: #3b4a5a; color: #fff; }
    /* Rotulo del grupo: dice "vencidas" una vez en vez de repetirlo en 2, 3 y 4+. */
    .moros-rotulo {
        font-size: 11px; font-weight: 600; color: #868e96;
        margin-left: 1px; letter-spacing: .2px;
    }
    /* Separador entre los dos ejes (grado de mora | estado legal). */
    .moros-sep {
        width: 1px; height: 18px; background: #dee2e6;
        margin: 0 4px; display: inline-block;
    }
    .moros-chip--limpiar { color: #6c757d; border-style: dashed; }
    .moros-chip.is-active .dot { background: #fff; }
    .moros-chip.is-active .n { background: rgba(255, 255, 255, .25); }
</style>
</div>
