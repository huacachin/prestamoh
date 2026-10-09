<div class="mt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Reportes de GPS de los vehículos
            <span class="text-muted small fw-normal d-block d-sm-inline">(dónde estaba cada vehículo en garantía y cuándo)</span>
        </h6>
        @if($puedeEditar && ! $mostrarForm)
            <button type="button" class="btn btn-sm btn-dark" wire:click="nuevo" @if($vehiculos->isEmpty()) disabled title="El cliente no tiene vehículos" @endif>
                <i class="ti ti-plus"></i> Nuevo reporte
            </button>
        @endif
    </div>

    @if($msg)
        <div class="alert {{ $msgType === 'ok' ? 'alert-success' : 'alert-danger' }} py-2 mb-2 small">{{ $msg }}</div>
    @endif

    {{-- ═══ Formulario: placa, coordenadas y descripción (la fecha de registro se pone sola) ═══ --}}
    @if($mostrarForm)
        <div class="border rounded p-3 mb-3" style="background:#fcfcfa;">
            <div class="row g-2">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-0 small">Placa *</label>
                    <select class="form-select form-select-sm @error('form.vehiculo_id') is-invalid @enderror" wire:model="form.vehiculo_id">
                        @if($vehiculos->count() !== 1)<option value="">-- Elegir --</option>@endif
                        @foreach($vehiculos as $v)
                            <option value="{{ $v->id }}">{{ $v->placa }} — {{ trim($v->marca.' '.$v->modelo) }}</option>
                        @endforeach
                    </select>
                    @error('form.vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label mb-0 small">Coordenadas * <span class="text-muted">(o el enlace de Google Maps)</span></label>
                    <input type="text" class="form-control form-control-sm @error('form.coordenadas') is-invalid @enderror"
                           wire:model="form.coordenadas" wire:keydown.enter="guardar" autocomplete="off"
                           placeholder="-12.014431, -76.824936 o https://maps.app.goo.gl/…">
                    @error('form.coordenadas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label mb-0 small">Descripción</label>
                    <textarea class="form-control form-control-sm @error('form.descripcion') is-invalid @enderror" rows="2" maxlength="1000"
                              wire:model="form.descripcion" placeholder="Ej.: parado frente al mercado de Huaycán, el cliente dice que trabaja ahí"></textarea>
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

    {{-- ═══ Tabla: del último registrado hacia abajo ═══ --}}
    @if($reportes->isEmpty())
        <div class="text-muted small fst-italic">Aún no hay reportes de GPS para los vehículos de este cliente.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0 tabla-reportes-gps" style="font-size:12px;">
                <thead class="table-secondary">
                    <tr>
                        <th class="text-center" width="130">Fecha de registro</th>
                        <th class="text-center" width="90">Placa</th>
                        <th class="text-center" width="210">Coordenadas</th>
                        <th>Descripción</th>
                        <th class="text-center" width="110">Registró</th>
                        @if($puedeEditar)<th width="40"></th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach($reportes as $r)
                    <tr wire:key="rep-{{ $r->id }}">
                        <td class="text-center" style="white-space:nowrap;">{{ $r->fecha->format('d/m/Y') }} <span class="text-muted">{{ $r->fecha->format('H:i') }}</span></td>
                        <td class="text-center fw-bold">{{ $r->placa }}</td>
                        <td class="text-center" style="white-space:nowrap;">
                            @if($r->tieneCoordenadas())
                                <span class="font-monospace">{{ $r->coordenadas() }}</span>
                                <a href="{{ $r->enlaceMaps() }}" target="_blank" rel="noopener" class="text-success ms-1" title="Abrir en Google Maps (pestaña nueva)"><i class="ti ti-map-pin"></i></a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="white-space:pre-line;">{{ $r->descripcion ?: '—' }}</td>
                        <td class="text-center">{{ $r->registradoPor?->username ?? $r->registradoPor?->name ?? '—' }}</td>
                        @if($puedeEditar)
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0" wire:click="eliminar({{ $r->id }})" @creadoEl($r)
                                        data-confirmar="¿Eliminar el reporte del {{ $r->fecha->format('d/m/Y H:i') }} del vehículo {{ $r->placa }}?" title="Eliminar">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
