<div>
    {{-- ═══ Modal de registro de actuación judicial (hijo de Legal\Expedientes\Show) ═══ --}}
    <div class="modal fade" id="actuacionModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:actuacion-modal-open.window="modal.show()"
         x-on:actuacion-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-timeline-event-plus text-danger"></i>
                        Registrar actuación judicial
                        @if($expedienteLabel !== '')
                            <span class="text-muted d-block" style="font-size:11px;">{{ $expedienteLabel }}</span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        <div class="row g-2">
                            {{-- Tipo --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Tipo de actuación *</b></label>
                                <select class="form-select form-select-sm @error('tipo') is-invalid @enderror"
                                        wire:model="tipo">
                                    @foreach(\App\Models\ActuacionJudicial::TIPOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Número --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Número</b></label>
                                <input type="text" class="form-control form-control-sm @error('numero') is-invalid @enderror"
                                       wire:model="numero" maxlength="40" autocomplete="off"
                                       placeholder="Ej. CUATRO">
                                @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text" style="font-size:10px;">
                                    El PJ numera en letras: CUATRO, QUINCE.
                                </div>
                            </div>

                            {{-- Fecha --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Fecha *</b></label>
                                <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                                       wire:model="fecha" max="{{ now()->addDay()->toDateString() }}">
                                @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Sumilla --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Sumilla *</b></label>
                                <input type="text" class="form-control form-control-sm @error('sumilla') is-invalid @enderror"
                                       wire:model="sumilla" maxlength="500" autocomplete="off"
                                       placeholder="Ej. Admiten demanda y corren traslado por 5 días">
                                @error('sumilla') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Detalle --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Detalle</b></label>
                                <textarea class="form-control form-control-sm @error('detalle') is-invalid @enderror"
                                          rows="3" wire:model="detalle" maxlength="5000"
                                          placeholder="Transcripción o resumen extendido de la actuación (opcional)"></textarea>
                                @error('detalle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Check: genera un plazo --}}
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="actuacionGeneraPlazo"
                                           wire:model.live="generaPlazo">
                                    <label class="form-check-label small" for="actuacionGeneraPlazo">
                                        <b>Genera un plazo</b>
                                        <span class="text-muted">(ej.: traslado de 3 días)</span>
                                    </label>
                                </div>
                            </div>

                            @if($generaPlazo)
                                <div class="col-12">
                                    <div class="border rounded p-2" style="background:#fff8f5;">
                                        <div class="small fw-semibold mb-1">
                                            <i class="ti ti-alarm text-danger"></i> Plazo generado por la actuación
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-8">
                                                <label class="form-label mb-0 small"><b>Descripción del plazo *</b></label>
                                                <input type="text" class="form-control form-control-sm @error('descripcionPlazo') is-invalid @enderror"
                                                       wire:model="descripcionPlazo" maxlength="255" autocomplete="off"
                                                       placeholder="Ej. Absolver traslado de la contradicción">
                                                @error('descripcionPlazo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-0 small"><b>Vence el *</b></label>
                                                <input type="date" class="form-control form-control-sm @error('fechaVencimiento') is-invalid @enderror"
                                                       wire:model="fechaVencimiento">
                                                @error('fechaVencimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="form-text" style="font-size:10px;">
                                            El plazo quedará vinculado a esta actuación, con el usuario actual como responsable.
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">Registrar actuación</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
