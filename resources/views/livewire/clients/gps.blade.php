<div>
    {{-- 13/10 (Antony): las direcciones van en una tabla como las ubicaciones de
         los vehículos: Op. (lápiz) · N° · Dirección · Coordenadas. El lápiz abre
         arriba el formulario de esa dirección (nombre y coordenadas; Casa y
         Negocio también se renombran) y Borrar vive dentro del formulario.
         En el celular las tablas se apilan (.tabla-apilable, data-label). --}}
    <style>
        .alta-direccion { border: 1px dashed #c9cdd2; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; background: #fcfcfa; }
        .tabla-direcciones-gps { font-size: 12px; margin-bottom: 0; }
        @media (max-width: 575.98px) {
            .tabla-apilable thead { display: none; }
            .tabla-apilable, .tabla-apilable tbody, .tabla-apilable tr, .tabla-apilable td { display: block; width: 100%; }
            .tabla-apilable tr { border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 8px; padding: 4px 8px; background: #fff; }
            .tabla-apilable td { border: 0 !important; padding: 2px 0 !important; text-align: left !important; white-space: normal !important; }
            .tabla-apilable td[data-label]::before { content: attr(data-label) ": "; font-weight: 600; color: #6c757d; }
            .tabla-apilable td.celda-acciones { text-align: right !important; }
        }
    </style>

    <h6 class="mb-2" style="color:red;">
        Ubicaciones GPS
        <span class="text-muted small fw-normal d-block d-sm-inline">
            (pega las coordenadas o el enlace de Google Maps)
        </span>
    </h6>

    @if($msg)
        @php
            $cls = match($msgType) { 'ok' => 'alert-success', 'warn' => 'alert-warning', default => 'alert-danger' };
            $ico = match($msgType) { 'ok' => 'ti-circle-check', 'warn' => 'ti-alert-triangle', default => 'ti-alert-circle' };
        @endphp
        <div class="alert {{ $cls }} py-2 mb-2 d-flex align-items-start gap-2">
            <i class="ti {{ $ico }} f-s-16"></i><span class="small">{{ $msg }}</span>
        </div>
    @endif

    <div class="border rounded px-2 py-2 mb-2 lista-direcciones-gps" style="background:#fcfcfa;"
         x-data="{ agregando: false }"
         x-on:gps-guardado.window="agregando = false">

        {{-- ═══ Formulario de la dirección que se edita (nombre + coordenadas) ═══ --}}
        @if($editClave !== null)
            @php $editando = collect($direcciones)->firstWhere('clave', $editClave); @endphp
            @if($editando)
                <div class="alta-direccion" wire:key="edicion-{{ $editClave }}">
                    <div class="small fw-semibold mb-1"><i class="ti ti-pencil"></i> Editando {{ $editando['nombre'] }}</div>
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-0 small">Nombre *</label>
                            <input type="text" class="form-control form-control-sm @error('editNombre') is-invalid @enderror" maxlength="60"
                                   wire:model="editNombre" wire:keydown.enter="guardar('{{ $editClave }}')" autocomplete="off"
                                   placeholder="{{ $editando['fija'] ? 'Ej.: Casa, Casa de la mamá' : 'Ej.: Taller, Chacra' }}">
                            @error('editNombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label mb-0 small">Coordenadas <span class="text-muted">(o el enlace de Google Maps; vacío = se conservan las de ahora)</span></label>
                            <input type="text" class="form-control form-control-sm" autocomplete="off"
                                   wire:model="pegado.{{ $editClave }}" wire:keydown.enter="guardar('{{ $editClave }}')"
                                   placeholder="{{ $editando['url'] ? $editando['lat'].', '.$editando['lng'] : '-12.014431, -76.824936 o https://maps.app.goo.gl/…' }}"
                                   title="En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-dark" wire:click="guardar('{{ $editClave }}')"
                                wire:loading.attr="disabled" wire:target="guardar('{{ $editClave }}')">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="guardar('{{ $editClave }}')">Guardar cambios</span>
                            <span wire:loading wire:target="guardar('{{ $editClave }}')">Guardando…</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelarEdicion"><i class="ti ti-x"></i> Cancelar</button>
                        @if($editando['url'] || ! $editando['fija'])
                            {{-- Borrar solo aquí, dentro de la edición: Casa/Negocio borran sus coordenadas (solo el director:
                                 no tienen fecha propia); una dirección adicional se elimina (regla del mismo día). --}}
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                                    wire:click="borrar('{{ $editClave }}')" @creadoEl($editando['modelo'])
                                    data-confirmar="{{ $editando['fija'] ? "¿Borrar las coordenadas de {$editando['nombre']}?" : "¿Eliminar la dirección {$editando['nombre']}?" }}"
                                    title="{{ $editando['fija'] ? 'Borrar coordenadas' : 'Eliminar esta dirección' }}">
                                <i class="ti ti-trash"></i> {{ $editando['fija'] ? 'Borrar coordenadas' : 'Eliminar dirección' }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- ═══ Tabla de direcciones ═══ --}}
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover tabla-direcciones-gps tabla-apilable">
                <thead class="table-secondary">
                    <tr>
                        @if($puedeEditar)<th class="text-center" width="40">Op.</th>@endif
                        <th class="text-center" width="40">N°</th>
                        <th class="text-center" width="120">Fecha</th>
                        <th class="text-center" width="100">Usuario</th>
                        <th width="180">Dirección</th>
                        <th>Coordenadas</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($direcciones as $i => $d)
                    <tr wire:key="dir-{{ $d['clave'] }}" @class(['table-warning' => $editClave === $d['clave']])>
                        @if($puedeEditar)
                            <td class="text-center celda-acciones">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0" wire:click="editar('{{ $d['clave'] }}')"
                                        title="Editar nombre o coordenadas">
                                    <i class="ti ti-pencil"></i>
                                </button>
                            </td>
                        @endif
                        <td class="text-center" data-label="N°">{{ $i + 1 }}</td>
                        {{-- 14/10 (Antony): último registro de coordenadas, fecha y usuario (de la auditoría). --}}
                        <td class="text-center" style="white-space:nowrap;" data-label="Fecha">
                            @if($d['url'] && $d['fecha'])
                                {{ $d['fecha']->format('d/m/Y') }} <span class="text-muted">{{ $d['fecha']->format('H:i') }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Usuario">{{ ($d['url'] && $d['quien']) ? $d['quien'] : '—' }}</td>
                        <td class="fw-semibold" data-label="Dirección"><i class="ti {{ $d['icono'] }} f-s-16"></i> {{ $d['nombre'] }}</td>
                        <td data-label="Coordenadas" style="word-break:break-all;">
                            @if($d['url'])
                                <span class="font-monospace">{{ $d['lat'] }}, {{ $d['lng'] }}</span>
                                <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="btn btn-success btn-sm py-0 ms-1" style="font-size:11px;" title="Ver en el mapa (pestaña nueva)">
                                    <i class="ti ti-map-pin"></i> Mapa
                                </a>
                            @else
                                <span class="badge bg-secondary" style="font-size:10px;"><i class="ti ti-map-pin-off"></i> Sin ubicación</span>
                                @if($puedeEditar)
                                    <a href="#" class="small ms-1" wire:click.prevent="editar('{{ $d['clave'] }}')">pegar coordenadas</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- ═══ Agregar dirección (Alpine: el formulario aparece sin viaje al servidor) ═══ --}}
        @if($puedeEditar)
            <div class="pt-2" wire:key="dir-nueva">
                <button type="button" class="btn btn-outline-primary btn-sm py-0" style="font-size:11px;"
                        x-show="!agregando" x-on:click="agregando = true; $nextTick(() => $refs.nuevoNombre.focus())">
                    <i class="ti ti-plus"></i> Agregar dirección
                </button>
                <div class="d-flex flex-column flex-md-row gap-2" x-show.important="agregando" x-cloak>
                    <input type="text" class="form-control form-control-sm @error('nuevaNombre') is-invalid @enderror" style="max-width:220px;"
                           x-ref="nuevoNombre" wire:model="nuevaNombre" maxlength="60"
                           placeholder="Nombre (p. ej. Taller, Chacra)">
                    <div class="input-group input-group-sm flex-grow-1">
                        <input type="text" class="form-control @error('nuevaCoordenadas') is-invalid @enderror" autocomplete="off"
                               wire:model="nuevaCoordenadas" wire:keydown.enter="agregar"
                               placeholder="Coordenadas o enlace de Google Maps">
                        <button type="button" class="btn btn-dark" wire:click="agregar"
                                wire:loading.attr="disabled" wire:target="agregar">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="agregar">Guardar</span>
                            <span wire:loading wire:target="agregar">Guardando…</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary"
                                x-on:click="agregando = false; $wire.set('nuevaNombre', '', false); $wire.set('nuevaCoordenadas', '', false)">Cancelar</button>
                    </div>
                </div>
                @error('nuevaNombre') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @error('nuevaCoordenadas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        @endif
    </div>

    {{-- Cambios de ubicación (10/10): qué dirección, de qué a qué, quién y cuándo. Sale de la auditoría.
         13/10 (Antony): la tabla va escondida tras "Ver movimientos" (Alpine, sin viaje al servidor). --}}
    <div class="border rounded px-2 py-2 mb-2" style="background:#fcfcfa;" x-data="{ verCambios: false }">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            <span class="fw-semibold small"><i class="ti ti-history f-s-16"></i> Cambios de ubicación</span>
            @if($historial->isNotEmpty())
                <button type="button" class="btn btn-outline-secondary btn-sm py-0" style="font-size:11px;" x-on:click="verCambios = !verCambios">
                    <i class="ti" :class="verCambios ? 'ti-eye-off' : 'ti-eye'"></i>
                    <span x-text="verCambios ? 'Ocultar movimientos' : 'Ver movimientos ({{ $historial->count() }})'">Ver movimientos ({{ $historial->count() }})</span>
                </button>
            @endif
        </div>
        @if($historial->isEmpty())
            <div class="small text-muted">Todavía no hay cambios registrados.</div>
        @else
            <div class="table-responsive" x-show="verCambios" x-cloak x-transition.opacity>
                <table class="table table-sm table-bordered align-middle mb-0 tabla-cambios-gps tabla-apilable" style="font-size:11px;">
                    <thead class="bg-primary">
                        <tr>
                            <th class="text-center" width="120">Fecha</th>
                            <th width="90">Acción</th>
                            <th>Dirección</th>
                            <th>Antes</th>
                            <th>Después</th>
                            <th width="110">Quién</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historial as $h)
                            <tr>
                                <td class="text-center text-nowrap" data-label="Fecha">{{ $h['fecha']?->format('d/m/Y H:i') }}</td>
                                <td data-label="Acción">{{ $h['accion'] }}</td>
                                <td data-label="Dirección">{{ $h['tipo'] }}</td>
                                <td class="text-muted" style="word-break:break-all;" data-label="Antes">{{ $h['antes'] }}</td>
                                <td class="fw-semibold" style="word-break:break-all;" data-label="Después">{{ $h['despues'] }}</td>
                                <td data-label="Quién">{{ $h['quien'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 26/09: reportes de GPS de los vehículos en garantía, debajo de las direcciones --}}
    <livewire:clients.gps-vehiculos :id="$clientId" :key="'gps-veh-'.$clientId" />
</div>
