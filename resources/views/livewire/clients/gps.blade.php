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

    {{-- 10/10 (Antony): Casa y Negocio, lado a lado y compactas ("abarca mucho"). Si la
         dirección ya tiene coordenadas, el campo va bloqueado hasta pulsar "Modificar"
         (estado local de Alpine; al guardar o borrar, gps-guardado lo vuelve a bloquear). --}}
    <div class="row g-2 mb-2">
    @foreach(\App\Livewire\Clients\Gps::TIPOS as $tipo => $titulo)
        @php
            $u = $this->ubicacion($tipo);
            $tiene = $u['url'] !== null;
            $icono = $tipo === 'casa' ? 'ti-home' : 'ti-building-store';
        @endphp
        <div class="col-12 col-md-6"
             x-data="{ modificando: false }"
             x-on:gps-guardado.window="if ($event.detail.tipo === '{{ $tipo }}') modificando = false">
            <div class="border rounded px-2 py-2 h-100" style="background:{{ $tiene ? '#f4faf6' : '#fcfcfa' }};">
                <div class="d-flex align-items-center gap-2 flex-wrap" style="min-height:26px;">
                    <span class="fw-semibold"><i class="ti {{ $icono }} f-s-16"></i> {{ $titulo }}</span>
                    @if($tiene)
                        <span class="badge bg-success" style="font-size:10px;"><i class="ti ti-map-pin"></i> Registrada</span>
                        <span class="small text-muted" style="word-break:break-all;">{{ $u['lat'] }}, {{ $u['lng'] }}</span>
                        <span class="ms-auto d-flex gap-1">
                            <a href="{{ $u['url'] }}" target="_blank" class="btn btn-success btn-sm py-0" style="font-size:11px;" title="Ver en el mapa">
                                <i class="ti ti-map-pin"></i> Mapa
                            </a>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-outline-danger btn-sm py-0" style="font-size:11px;"
                                        wire:click="borrar('{{ $tipo }}')" @creadoEl(null)
                                        data-confirmar="¿Borrar las coordenadas de {{ $titulo }}?" title="Borrar coordenadas">
                                    <i class="ti ti-trash"></i>
                                </button>
                            @endif
                        </span>
                    @else
                        <span class="badge bg-secondary" style="font-size:10px;"><i class="ti ti-map-pin-off"></i> Sin ubicación</span>
                    @endif
                </div>
                @if($puedeEditar)
                    <div class="input-group input-group-sm mt-2">
                        <input type="text" class="form-control" x-ref="campo" autocomplete="off"
                               wire:model="pegado.{{ $tipo }}"
                               :disabled="{{ $tiene ? 'true' : 'false' }} && !modificando"
                               placeholder="Coordenadas o enlace de Google Maps"
                               title="En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.">
                        @if($tiene)
                            <button type="button" class="btn btn-outline-dark" x-show="!modificando"
                                    x-on:click="modificando = true; $nextTick(() => $refs.campo.focus())"
                                    title="Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.">
                                <i class="ti ti-pencil"></i> Modificar
                            </button>
                        @endif
                        <button type="button" class="btn btn-dark" x-show="{{ $tiene ? 'modificando' : 'true' }}"
                                wire:click="guardar('{{ $tipo }}')"
                                wire:loading.attr="disabled" wire:target="guardar('{{ $tipo }}')" title="Guardar {{ $titulo }}">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="guardar('{{ $tipo }}')">Guardar</span>
                            <span wire:loading wire:target="guardar('{{ $tipo }}')">Guardando…</span>
                        </button>
                        @if($tiene)
                            <button type="button" class="btn btn-outline-secondary" x-show="modificando"
                                    x-on:click="modificando = false; $wire.set('pegado.{{ $tipo }}', '', false)">
                                Cancelar
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endforeach
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
