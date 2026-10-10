<div class="mt-3">
    <style>
        .placa-gps { border: 1px solid #e3e6ea; border-radius: 6px; margin-bottom: 10px; background: #fff; }
        .placa-gps .placa-cabecera { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
                                     padding: 6px 10px; background: #f6f7f9; border-bottom: 1px solid #e3e6ea; border-radius: 6px 6px 0 0; }
        .placa-gps .placa-nombre { font-weight: 700; font-size: 14px; letter-spacing: .5px; }
        .placa-gps .placa-cuerpo { padding: 8px 10px; }
        .placa-gps .alta-ubicacion { border: 1px dashed #c9cdd2; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; background: #fcfcfa; }
        .placa-gps table { font-size: 12px; margin-bottom: 0; }
    </style>

    <h6 class="mb-2" style="color:red;">
        Ubicaciones GPS de los vehículos
        <span class="text-muted small fw-normal d-block d-sm-inline">(dónde estaba cada vehículo en garantía y cuándo)</span>
    </h6>

    @if($msg)
        <div class="alert {{ $msgType === 'ok' ? 'alert-success' : 'alert-danger' }} py-2 mb-2 small">{{ $msg }}</div>
    @endif

    @if($vehiculos->isEmpty() && $huerfanas->isEmpty())
        <div class="text-muted small fst-italic">
            Aún no se han agregado vehículos a este cliente.
            @if($puedeEditar)
                <a href="{{ route('clients.edit', ['id' => $clientId, 'tab' => 'vehiculos']) }}">Agregar desde la pestaña Vehículos</a>
            @endif
        </div>
    @endif

    {{-- ═══ Una tarjeta por placa: botón, formulario y sus ubicaciones ═══ --}}
    @foreach($vehiculos as $v)
        @php $ubicaciones = $porVehiculo->get($v->id, collect()); @endphp
        <div class="placa-gps" wire:key="placa-{{ $v->id }}">
            <div class="placa-cabecera">
                <div>
                    <span class="placa-nombre"><i class="ti ti-car"></i> {{ $v->placa }}</span>
                    <span class="text-muted small ms-1">{{ trim($v->marca.' '.$v->modelo) }}</span>
                    <span class="text-muted small ms-1">· {{ $ubicaciones->count() }} {{ $ubicaciones->count() === 1 ? 'ubicación' : 'ubicaciones' }}</span>
                </div>
                @if($puedeEditar && $formVehiculoId !== $v->id)
                    <button type="button" class="btn btn-sm btn-dark py-0" wire:click="nuevo({{ $v->id }})">
                        <i class="ti ti-plus"></i> Agregar ubicación
                    </button>
                @endif
            </div>
            <div class="placa-cuerpo">
                @if($formVehiculoId === $v->id)
                    <div class="alta-ubicacion">
                        <div class="row g-2">
                            <div class="col-12 col-md-5">
                                <label class="form-label mb-0 small">Coordenadas * <span class="text-muted">(o el enlace de Google Maps)</span></label>
                                <input type="text" class="form-control form-control-sm @error('form.coordenadas') is-invalid @enderror"
                                       wire:model="form.coordenadas" wire:keydown.enter="guardar" autocomplete="off" autofocus
                                       placeholder="-12.014431, -76.824936 o https://maps.app.goo.gl/…">
                                @error('form.coordenadas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label mb-0 small">Descripción</label>
                                <input type="text" class="form-control form-control-sm @error('form.descripcion') is-invalid @enderror" maxlength="1000"
                                       wire:model="form.descripcion" wire:keydown.enter="guardar" autocomplete="off"
                                       placeholder="Ej.: parado frente al mercado de Huaycán, el cliente dice que trabaja ahí">
                                @error('form.descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-dark" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                                <i class="ti ti-device-floppy"></i>
                                <span wire:loading.remove wire:target="guardar">Guardar</span>
                                <span wire:loading wire:target="guardar">Guardando…</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelar"><i class="ti ti-x"></i> Cancelar</button>
                        </div>
                    </div>
                @endif

                @include('livewire.clients.partials._ubicaciones-vehiculo', ['ubicaciones' => $ubicaciones, 'placa' => $v->placa])
            </div>
        </div>
    @endforeach

    {{-- ═══ Ubicaciones de placas que ya no están entre los vehículos del cliente ═══ --}}
    @foreach($huerfanas as $placa => $ubicaciones)
        <div class="placa-gps" wire:key="placa-huerfana-{{ $placa }}">
            <div class="placa-cabecera">
                <div>
                    <span class="placa-nombre"><i class="ti ti-car-off"></i> {{ $placa }}</span>
                    <span class="text-muted small ms-1">vehículo ya no registrado en la ficha</span>
                    <span class="text-muted small ms-1">· {{ $ubicaciones->count() }} {{ $ubicaciones->count() === 1 ? 'ubicación' : 'ubicaciones' }}</span>
                </div>
            </div>
            <div class="placa-cuerpo">
                @include('livewire.clients.partials._ubicaciones-vehiculo', ['ubicaciones' => $ubicaciones, 'placa' => $placa])
            </div>
        </div>
    @endforeach
</div>
