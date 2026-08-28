<div>
    {{-- ═══ Modal crear/editar papeleta (hijo de Legal\Papeletas\Index) ═══ --}}
    <div class="modal fade" id="papeletaModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:papeleta-modal-open.window="modal.show()"
         x-on:papeleta-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-file-alert text-danger"></i>
                        {{ $editingId ? 'Editar papeleta — '.$nro_papeleta : 'Nueva papeleta' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        {{-- Vehículo --}}
                        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;">
                            <label class="form-label mb-0 small"><b>Vehículo</b> <span class="text-danger">*</span></label>
                            @if($vehiculo_id)
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-dark" style="font-size:11px;">
                                        <i class="ti ti-car"></i> {{ $vehiculoLabel }}
                                    </span>
                                    @if(! $editingId)
                                        <button type="button" class="btn btn-xs btn-outline-danger"
                                                style="padding: 1px 6px; font-size: 10px;"
                                                wire:click="quitarVehiculo">
                                            <i class="ti ti-x"></i> Quitar
                                        </button>
                                    @endif
                                </div>
                            @else
                                <input type="text" class="form-control form-control-sm text-uppercase @error('vehiculo_id') is-invalid @enderror"
                                       autocomplete="off"
                                       wire:model.live.debounce.400ms="vehiculoBusqueda"
                                       placeholder="Busca por placa (mín. 2 caracteres)">
                                @error('vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                @if($vehiculosEncontrados->isNotEmpty())
                                    <div class="list-group mt-1" style="max-height: 160px; overflow-y: auto;">
                                        @foreach($vehiculosEncontrados as $vehiculo)
                                            <button type="button"
                                                    class="list-group-item list-group-item-action py-1 px-2"
                                                    style="font-size: 11px;"
                                                    wire:click="seleccionarVehiculo({{ $vehiculo->id }})">
                                                <b>{{ $vehiculo->placa }}</b>
                                                <span class="text-muted">— {{ trim("{$vehiculo->marca} {$vehiculo->modelo}") ?: 'sin marca/modelo' }}</span>
                                                <span class="badge bg-light text-dark border" style="font-size:9px;">
                                                    {{ \App\Models\Vehiculo::PROPIETARIO_TIPOS[$vehiculo->propietario_tipo] ?? $vehiculo->propietario_tipo }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif(mb_strlen(trim($vehiculoBusqueda)) >= 2)
                                    <div class="form-text text-danger">
                                        Sin coincidencias. Registra primero el vehículo en
                                        <a href="{{ route('legal.vehiculos') }}" target="_blank">Vehículos</a>.
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- Datos de la papeleta --}}
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Entidad</b> <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('entidad') is-invalid @enderror"
                                        wire:model.live="entidad">
                                    @foreach(\App\Models\Papeleta::ENTIDADES as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('entidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>N° de papeleta / acta</b> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase font-monospace @error('nro_papeleta') is-invalid @enderror"
                                       wire:model="nro_papeleta" maxlength="30" autocomplete="off"
                                       placeholder="Ej. M0123456">
                                @error('nro_papeleta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Código de falta</b></label>
                                <input type="text" class="form-control form-control-sm text-uppercase @error('codigo_falta') is-invalid @enderror"
                                       wire:model="codigo_falta" maxlength="10" autocomplete="off"
                                       placeholder="Ej. M08, G58">
                                @error('codigo_falta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Puntos</b></label>
                                <input type="number" class="form-control form-control-sm @error('puntos') is-invalid @enderror"
                                       wire:model="puntos" min="0" max="100" placeholder="0">
                                @error('puntos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Fecha de infracción</b> <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm @error('fecha_infraccion') is-invalid @enderror"
                                       wire:model="fecha_infraccion" max="{{ now()->toDateString() }}">
                                @error('fecha_infraccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Monto S/</b> <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('monto') is-invalid @enderror"
                                       wire:model="monto" placeholder="0.00">
                                @error('monto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Responsable del pago</b> <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('responsable_pago') is-invalid @enderror"
                                        wire:model.live="responsable_pago">
                                    @foreach(\App\Models\Papeleta::RESPONSABLES as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('responsable_pago') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Estado</b> <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('estado') is-invalid @enderror"
                                        wire:model="estado">
                                    @foreach(\App\Models\Papeleta::ESTADOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Conductor (solo si él responde por el pago) --}}
                            @if($responsable_pago === 'conductor')
                                <div class="col-md-6">
                                    <label class="form-label mb-0 small"><b>Nombre del conductor</b> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('conductor_nombre') is-invalid @enderror"
                                           wire:model="conductor_nombre" maxlength="255"
                                           placeholder="Nombre completo del conductor">
                                    @error('conductor_nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small"><b>Documento del conductor</b></label>
                                    <input type="text" class="form-control form-control-sm @error('conductor_documento') is-invalid @enderror"
                                           wire:model="conductor_documento" maxlength="15" placeholder="DNI / CE">
                                    @error('conductor_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            @if($editingId)
                                <div class="col-12">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="papRevision"
                                               wire:model="requiere_revision">
                                        <label class="form-check-label small" for="papRevision">
                                            Requiere revisión (datos importados por validar)
                                        </label>
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Nota</b></label>
                                <textarea class="form-control form-control-sm @error('nota') is-invalid @enderror"
                                          rows="2" wire:model="nota" maxlength="2000"
                                          placeholder="Observaciones de la papeleta (opcional)"></textarea>
                                @error('nota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">{{ $editingId ? 'Guardar cambios' : 'Registrar papeleta' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
