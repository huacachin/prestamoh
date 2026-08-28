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

    <div class="row g-2">
        @foreach(\App\Livewire\Clients\Gps::TIPOS as $tipo => $titulo)
            @php
                $u = $this->ubicacion($tipo);
                $tiene = $u['url'] !== null;
                $icono = $tipo === 'casa' ? 'ti-home' : 'ti-building-store';
            @endphp
            <div class="col-12 col-lg-6">
                <div class="border rounded p-2 h-100" style="background:{{ $tiene ? '#f4faf6' : '#fcfcfa' }};">

                    {{-- Cabecera: título + estado --}}
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-semibold">
                            <i class="ti {{ $icono }} f-s-18"></i> {{ $titulo }}
                        </span>
                        @if($tiene)
                            <span class="badge bg-success"><i class="ti ti-map-pin"></i> Registrada</span>
                        @else
                            <span class="badge bg-secondary"><i class="ti ti-map-pin-off"></i> Sin ubicación</span>
                        @endif
                    </div>

                    @if($tiene)
                        <div class="small text-muted mb-2" style="word-break:break-all;">
                            {{ $u['lat'] }}, {{ $u['lng'] }}
                        </div>
                        {{-- Botones a ancho completo en móvil (es donde se usan) --}}
                        <div class="d-grid d-sm-flex gap-2 mb-2">
                            <a href="{{ $u['url'] }}" target="_blank" class="btn btn-sm btn-success">
                                <i class="ti ti-map-pin"></i> Ver en el mapa
                            </a>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="borrar('{{ $tipo }}')"
                                        wire:confirm="¿Borrar las coordenadas de {{ $titulo }}?">
                                    <i class="ti ti-trash"></i> Borrar
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="small text-muted mb-2">
                            Este cliente aún no tiene la ubicación de {{ mb_strtolower($titulo) }}.
                        </div>
                    @endif

                    @if($puedeEditar)
                        <label class="form-label mb-1 small fw-semibold">
                            {{ $tiene ? 'Actualizar ubicación' : 'Registrar ubicación' }}
                        </label>
                        <textarea class="form-control form-control-sm" rows="2"
                                  wire:model.defer="pegado.{{ $tipo }}"
                                  placeholder="-12.014431, -76.824936"></textarea>
                        <div class="d-grid d-sm-block mt-2">
                            <button type="button" class="btn btn-sm btn-dark"
                                    wire:click="guardar('{{ $tipo }}')"
                                    wire:loading.attr="disabled" wire:target="guardar('{{ $tipo }}')">
                                <i class="ti ti-device-floppy"></i>
                                <span wire:loading.remove wire:target="guardar('{{ $tipo }}')">Guardar {{ $titulo }}</span>
                                <span wire:loading wire:target="guardar('{{ $tipo }}')">Guardando…</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="small text-muted mt-2">
        <i class="ti ti-info-circle"></i>
        En el celular: abre Google Maps, mantén pulsado el punto, copia las coordenadas y pégalas aquí.
    </div>
</div>
