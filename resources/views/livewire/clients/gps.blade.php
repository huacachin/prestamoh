<div>
    {{-- 10/10 (Antony): "en móvil se ve muy mal". La lista de direcciones ya no es
         una tabla de anchos fijos: cada dirección es una fila flexible (nombre ·
         coordenadas · botones) que en el celular se parte en dos líneas (nombre y
         botones arriba, coordenadas o el campo a lo ancho abajo). Las tablas de
         cambios y de ubicaciones de vehículos (.tabla-apilable) se apilan en
         tarjetas con el rótulo de cada columna (data-label) por debajo de 576 px. --}}
    <style>
        .direccion-gps { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; padding: 5px 0; border-bottom: 1px dashed #e3e6ea; }
        .direccion-gps:last-child { border-bottom: 0; }
        .direccion-gps .dg-nombre { flex: 0 0 150px; font-weight: 600; white-space: nowrap; }
        .direccion-gps .dg-coord { flex: 1 1 220px; min-width: 0; word-break: break-all; }
        .direccion-gps .dg-acciones { margin-left: auto; display: flex; gap: 4px; white-space: nowrap; }
        .direccion-gps .dg-acciones .btn { font-size: 11px; }
        @media (max-width: 575.98px) {
            .direccion-gps .dg-nombre { flex: 1 1 auto; }
            .direccion-gps .dg-acciones { order: 2; }
            .direccion-gps .dg-coord { order: 3; flex: 1 1 100%; }
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

    {{-- 10/10 (Antony): lista de direcciones —Casa, Negocio y las que se agreguen con
         nombre propio—, compacta. Si la dirección ya tiene coordenadas, el campo va
         bloqueado hasta pulsar "Modificar" (estado local de Alpine: `modificando` guarda
         la clave de la fila abierta; al guardar o borrar, gps-guardado lo cierra). --}}
    <div class="border rounded px-2 py-1 mb-2 lista-direcciones-gps" style="background:#fcfcfa; font-size:12px;"
         x-data="{ modificando: null, agregando: false }"
         x-on:gps-guardado.window="modificando = null; agregando = false">
        @foreach($direcciones as $d)
            @php
                $tiene = $d['url'] !== null;
                $icono = match ($d['clave']) { 'casa' => 'ti-home', 'negocio' => 'ti-building-store', default => 'ti-map-pin' };
            @endphp
            <div class="direccion-gps" wire:key="dir-{{ $d['clave'] }}">
                <span class="dg-nombre"><i class="ti {{ $icono }} f-s-16"></i> {{ $d['nombre'] }}</span>
                <span class="dg-coord">
                    @if($tiene)
                        <span class="text-muted" x-show="modificando !== '{{ $d['clave'] }}'">{{ $d['lat'] }}, {{ $d['lng'] }}</span>
                    @elseif(! $puedeEditar)
                        <span class="badge bg-secondary" style="font-size:10px;"><i class="ti ti-map-pin-off"></i> Sin ubicación</span>
                    @endif
                    @if($puedeEditar)
                        @if($tiene)
                        <div class="input-group input-group-sm" x-show="modificando === '{{ $d['clave'] }}'" x-cloak>
                        @else
                        <div class="input-group input-group-sm">
                        @endif
                            <input type="text" class="form-control" autocomplete="off" x-ref="campo-{{ $d['clave'] }}"
                                   wire:model="pegado.{{ $d['clave'] }}"
                                   placeholder="{{ $tiene ? 'Nuevas coordenadas o enlace de Google Maps' : 'Coordenadas o enlace de Google Maps' }}"
                                   title="En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.">
                            <button type="button" class="btn btn-dark" wire:click="guardar('{{ $d['clave'] }}')"
                                    wire:loading.attr="disabled" wire:target="guardar('{{ $d['clave'] }}')">
                                <i class="ti ti-device-floppy"></i>
                                <span wire:loading.remove wire:target="guardar('{{ $d['clave'] }}')">Guardar</span>
                                <span wire:loading wire:target="guardar('{{ $d['clave'] }}')">Guardando…</span>
                            </button>
                            @if($tiene)
                                <button type="button" class="btn btn-outline-secondary"
                                        x-on:click="modificando = null; $wire.set('pegado.{{ $d['clave'] }}', '', false)">Cancelar</button>
                            @endif
                        </div>
                    @endif
                </span>
                @if($tiene || ($puedeEditar && ! $d['fija']))
                    <span class="dg-acciones">
                        @if($tiene)
                            <a href="{{ $d['url'] }}" target="_blank" class="btn btn-success btn-sm py-0" title="Ver en el mapa">
                                <i class="ti ti-map-pin"></i> Mapa
                            </a>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-outline-dark btn-sm py-0"
                                        x-show="modificando !== '{{ $d['clave'] }}'"
                                        x-on:click="modificando = '{{ $d['clave'] }}'; $nextTick(() => $refs['campo-{{ $d['clave'] }}'].focus())"
                                        title="Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.">
                                    <i class="ti ti-pencil"></i> Modificar
                                </button>
                            @endif
                        @endif
                        @if($puedeEditar && ($tiene || ! $d['fija']))
                            <button type="button" class="btn btn-outline-danger btn-sm py-0"
                                    wire:click="borrar('{{ $d['clave'] }}')" @creadoEl($d['modelo'])
                                    data-confirmar="{{ $d['fija'] ? "¿Borrar las coordenadas de {$d['nombre']}?" : "¿Eliminar la dirección {$d['nombre']}?" }}"
                                    title="{{ $d['fija'] ? 'Borrar coordenadas' : 'Eliminar esta dirección' }}">
                                <i class="ti ti-trash"></i>
                            </button>
                        @endif
                    </span>
                @endif
            </div>
        @endforeach
        @if($puedeEditar)
            <div class="pt-2 pb-1" wire:key="dir-nueva">
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

    {{-- Cambios de ubicación (10/10): qué dirección, de qué a qué, quién y cuándo. Sale de la auditoría. --}}
    <div class="border rounded px-2 py-2 mb-2" style="background:#fcfcfa;">
        <div class="fw-semibold small mb-1"><i class="ti ti-history f-s-16"></i> Cambios de ubicación</div>
        @if($historial->isEmpty())
            <div class="small text-muted">Todavía no hay cambios registrados.</div>
        @else
            <div class="table-responsive">
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
