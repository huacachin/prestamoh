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

    {{-- Casa a todo el ancho (26/09): desde que Negocio se fue, la tarjeta a media
         columna quedaba flotando. Izquierda: estado y acciones; derecha: registrar. --}}
    @foreach(\App\Livewire\Clients\Gps::TIPOS as $tipo => $titulo)
        @php
            $u = $this->ubicacion($tipo);
            $tiene = $u['url'] !== null;
        @endphp
        <div class="border rounded p-3 mb-2" style="background:{{ $tiene ? '#f4faf6' : '#fcfcfa' }};">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-semibold"><i class="ti ti-home f-s-18"></i> {{ $titulo }}</span>
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
                                        wire:click="borrar('{{ $tipo }}')"
                                        data-confirmar="¿Borrar las coordenadas de {{ $titulo }}?">
                                    <i class="ti ti-trash"></i> Borrar
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="small text-muted">Este cliente aún no tiene la ubicación de {{ mb_strtolower($titulo) }}. Es la que sale como domicilio en los reportes de GPS de abajo.</div>
                    @endif
                </div>
                @if($puedeEditar)
                    <div class="col-12 col-md-7">
                        <label class="form-label mb-1 small fw-semibold">{{ $tiene ? 'Actualizar ubicación' : 'Registrar ubicación' }}</label>
                        <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-start">
                            <textarea class="form-control form-control-sm flex-grow-1" rows="2"
                                      wire:model.defer="pegado.{{ $tipo }}"
                                      placeholder="-12.014431, -76.824936  o el enlace de Google Maps"></textarea>
                            <button type="button" class="btn btn-sm btn-dark flex-shrink-0"
                                    wire:click="guardar('{{ $tipo }}')"
                                    wire:loading.attr="disabled" wire:target="guardar('{{ $tipo }}')">
                                <i class="ti ti-device-floppy"></i>
                                <span wire:loading.remove wire:target="guardar('{{ $tipo }}')">Guardar {{ $titulo }}</span>
                                <span wire:loading wire:target="guardar('{{ $tipo }}')">Guardando…</span>
                            </button>
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="ti ti-info-circle"></i>
                            En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- 26/09: reportes de GPS de los vehículos en garantía, debajo de Casa --}}
    <livewire:clients.gps-vehiculos :id="$clientId" :key="'gps-veh-'.$clientId" />
</div>
