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

    {{-- 10/10 (Antony): Casa y Negocio. Si la dirección ya tiene coordenadas, el campo va
         bloqueado hasta pulsar "Modificar" (estado local de Alpine; al guardar o borrar, el
         evento gps-guardado lo vuelve a bloquear). --}}
    @foreach(\App\Livewire\Clients\Gps::TIPOS as $tipo => $titulo)
        @php
            $u = $this->ubicacion($tipo);
            $tiene = $u['url'] !== null;
            $icono = $tipo === 'casa' ? 'ti-home' : 'ti-building-store';
        @endphp
        <div class="border rounded p-3 mb-2" style="background:{{ $tiene ? '#f4faf6' : '#fcfcfa' }};"
             x-data="{ modificando: false }"
             x-on:gps-guardado.window="if ($event.detail.tipo === '{{ $tipo }}') modificando = false">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-semibold"><i class="ti {{ $icono }} f-s-18"></i> {{ $titulo }}</span>
                        @if($tiene)
                            <span class="badge bg-success"><i class="ti ti-map-pin"></i> Registrada</span>
                        @else
                            <span class="badge bg-secondary"><i class="ti ti-map-pin-off"></i> Sin ubicación</span>
                        @endif
                    </div>
                    @if($tiene)
                        <div class="small text-muted mb-2" style="word-break:break-all;">{{ $u['lat'] }}, {{ $u['lng'] }}</div>
                        <div class="d-grid d-sm-flex gap-2">
                            <a href="{{ $u['url'] }}" target="_blank" class="btn btn-sm btn-success">
                                <i class="ti ti-map-pin"></i> Ver en el mapa
                            </a>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="borrar('{{ $tipo }}')" @creadoEl(null)
                                        data-confirmar="¿Borrar las coordenadas de {{ $titulo }}?">
                                    <i class="ti ti-trash"></i> Borrar
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="small text-muted">
                            Este cliente aún no tiene la ubicación de {{ mb_strtolower($titulo) }}.
                            @if($tipo === 'casa') Es la que sale como domicilio en los reportes de GPS de abajo. @endif
                        </div>
                    @endif
                </div>
                @if($puedeEditar)
                    <div class="col-12 col-md-7">
                        <label class="form-label mb-1 small fw-semibold">{{ $tiene ? 'Actualizar ubicación' : 'Registrar ubicación' }}</label>
                        <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-start">
                            <textarea class="form-control form-control-sm flex-grow-1" rows="2"
                                      wire:model="pegado.{{ $tipo }}" x-ref="campo"
                                      :disabled="{{ $tiene ? 'true' : 'false' }} && !modificando"
                                      placeholder="-12.014431, -76.824936  o el enlace de Google Maps"></textarea>
                            <div class="d-flex gap-2 flex-shrink-0">
                                @if($tiene)
                                    <button type="button" class="btn btn-sm btn-outline-dark" x-show="!modificando"
                                            x-on:click="modificando = true; $nextTick(() => $refs.campo.focus())">
                                        <i class="ti ti-pencil"></i> Modificar
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-dark" x-show="{{ $tiene ? 'modificando' : 'true' }}"
                                        wire:click="guardar('{{ $tipo }}')"
                                        wire:loading.attr="disabled" wire:target="guardar('{{ $tipo }}')">
                                    <i class="ti ti-device-floppy"></i>
                                    <span wire:loading.remove wire:target="guardar('{{ $tipo }}')">Guardar {{ $titulo }}</span>
                                    <span wire:loading wire:target="guardar('{{ $tipo }}')">Guardando…</span>
                                </button>
                                @if($tiene)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" x-show="modificando"
                                            x-on:click="modificando = false; $wire.set('pegado.{{ $tipo }}', '', false)">
                                        Cancelar
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="ti ti-info-circle"></i>
                            @if($tiene)
                                Las coordenadas ya están registradas: pulsa Modificar si hay que cambiarlas.
                            @else
                                En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Cambios de ubicación (10/10): qué dirección, de qué a qué, quién y cuándo. Sale de la auditoría. --}}
    <div class="border rounded p-3 mb-3" style="background:#fcfcfa;">
        <div class="fw-semibold mb-2"><i class="ti ti-history f-s-16"></i> Cambios de ubicación</div>
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
