<div>
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
    <div class="border rounded px-2 py-2 mb-2 lista-direcciones-gps" style="background:#fcfcfa;"
         x-data="{ modificando: null, agregando: false }"
         x-on:gps-guardado.window="modificando = null; agregando = false">
        <table class="table table-sm align-middle mb-0" style="font-size:12px;">
            <thead>
                <tr class="text-muted" style="font-size:11px;">
                    <th style="width:160px;">Dirección</th>
                    <th>Coordenadas</th>
                    <th class="text-end" style="width:170px;"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($direcciones as $d)
                @php
                    $tiene = $d['url'] !== null;
                    $icono = match ($d['clave']) { 'casa' => 'ti-home', 'negocio' => 'ti-building-store', default => 'ti-map-pin' };
                @endphp
                <tr wire:key="dir-{{ $d['clave'] }}">
                    <td class="fw-semibold text-nowrap">
                        <i class="ti {{ $icono }} f-s-16"></i> {{ $d['nombre'] }}
                    </td>
                    <td>
                        @if($tiene)
                            <span class="text-muted" style="word-break:break-all;"
                                  x-show="modificando !== '{{ $d['clave'] }}'">{{ $d['lat'] }}, {{ $d['lng'] }}</span>
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
                    </td>
                    <td class="text-end text-nowrap">
                        @if($tiene)
                            <a href="{{ $d['url'] }}" target="_blank" class="btn btn-success btn-sm py-0" style="font-size:11px;" title="Ver en el mapa">
                                <i class="ti ti-map-pin"></i> Mapa
                            </a>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-outline-dark btn-sm py-0" style="font-size:11px;"
                                        x-show="modificando !== '{{ $d['clave'] }}'"
                                        x-on:click="modificando = '{{ $d['clave'] }}'; $nextTick(() => $refs['campo-{{ $d['clave'] }}'].focus())"
                                        title="Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.">
                                    <i class="ti ti-pencil"></i> Modificar
                                </button>
                            @endif
                        @endif
                        @if($puedeEditar && ($tiene || ! $d['fija']))
                            <button type="button" class="btn btn-outline-danger btn-sm py-0" style="font-size:11px;"
                                    wire:click="borrar('{{ $d['clave'] }}')" @creadoEl($d['modelo'])
                                    data-confirmar="{{ $d['fija'] ? "¿Borrar las coordenadas de {$d['nombre']}?" : "¿Eliminar la dirección {$d['nombre']}?" }}"
                                    title="{{ $d['fija'] ? 'Borrar coordenadas' : 'Eliminar esta dirección' }}">
                                <i class="ti ti-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($puedeEditar)
                <tr wire:key="dir-nueva">
                    <td colspan="3" class="pt-2">
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
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    {{-- Cambios de ubicación (10/10): qué dirección, de qué a qué, quién y cuándo. Sale de la auditoría. --}}
    <div class="border rounded px-2 py-2 mb-2" style="background:#fcfcfa;">
        <div class="fw-semibold small mb-1"><i class="ti ti-history f-s-16"></i> Cambios de ubicación</div>
        @if($historial->isEmpty())
            <div class="small text-muted">Todavía no hay cambios registrados.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 tabla-cambios-gps" style="font-size:11px;">
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
                                <td class="text-center text-nowrap">{{ $h['fecha']?->format('d/m/Y H:i') }}</td>
                                <td>{{ $h['accion'] }}</td>
                                <td>{{ $h['tipo'] }}</td>
                                <td class="text-muted" style="word-break:break-all;">{{ $h['antes'] }}</td>
                                <td class="fw-semibold" style="word-break:break-all;">{{ $h['despues'] }}</td>
                                <td>{{ $h['quien'] }}</td>
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
