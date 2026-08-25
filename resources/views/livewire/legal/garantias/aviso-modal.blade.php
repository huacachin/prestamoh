<div>
    {{-- ═══ Modal de registro de aviso SIGM (hijo de Legal\Garantias\Show) ═══ --}}
    <div class="modal fade" id="avisoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:aviso-modal-open.window="modal.show()"
         x-on:aviso-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-timeline-event-plus text-danger"></i>
                        Registrar aviso SIGM
                        @if($garantiaLabel !== '')
                            <span class="text-muted d-block" style="font-size:11px;">{{ $garantiaLabel }}</span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        <div class="row g-2">
                            {{-- Tipo --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Tipo de aviso *</b></label>
                                <select class="form-select form-select-sm @error('tipo') is-invalid @enderror"
                                        wire:model.live="tipo">
                                    @foreach(\App\Models\SigmAviso::TIPOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- N° formulario --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>N° de formulario</b></label>
                                <input type="text" class="form-control form-control-sm @error('nroFormulario') is-invalid @enderror"
                                       wire:model="nroFormulario" maxlength="12" autocomplete="off"
                                       placeholder="AAAA-NNNNNN (ej. 2026-123456)">
                                @error('nroFormulario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Folio --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Folio</b></label>
                                <input type="text" class="form-control form-control-sm @error('folio') is-invalid @enderror"
                                       wire:model="folio" maxlength="25" autocomplete="off"
                                       placeholder="Solo dígitos">
                                @error('folio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Fecha de presentación --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Fecha de presentación *</b></label>
                                <input type="date" class="form-control form-control-sm @error('fechaPresentacion') is-invalid @enderror"
                                       wire:model="fechaPresentacion">
                                @error('fechaPresentacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Vigencia hasta --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Vigencia hasta</b></label>
                                <input type="date" class="form-control form-control-sm @error('vigenciaHasta') is-invalid @enderror"
                                       wire:model="vigenciaHasta">
                                @error('vigenciaHasta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if(in_array($tipo, ['constitucion', 'renovacion'], true))
                                    <div class="form-text" style="font-size:10px;">
                                        Si se deja vacía, se calcula presentación + {{ \App\Models\SigmAviso::VIGENCIA_ANIOS_DEFAULT }} años (D. Leg. 1400).
                                    </div>
                                @endif
                            </div>

                            {{-- Tasa (informativa) --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Tasa registral</b></label>
                                <input type="text" class="form-control form-control-sm" disabled
                                       value="S/ {{ number_format(\App\Models\SigmAviso::TASA_SIGM, 2) }}">
                                <div class="form-text" style="font-size:10px;">Tasa fija por aviso electrónico en el SIGM.</div>
                            </div>

                            {{-- Campos de ejecución (solo tipo = ejecución) --}}
                            @if($tipo === 'ejecucion')
                                <div class="col-12">
                                    <div class="border rounded p-2" style="background:#fff8f5;">
                                        <div class="small fw-semibold mb-1">
                                            <i class="ti ti-gavel text-danger"></i> Datos de la ejecución
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label mb-0 small"><b>Modalidad *</b></label>
                                                <select class="form-select form-select-sm @error('modalidadEjecucion') is-invalid @enderror"
                                                        wire:model="modalidadEjecucion">
                                                    <option value="">— Seleccionar —</option>
                                                    @foreach(\App\Models\SigmAviso::MODALIDADES_EJECUCION as $clave => $etiqueta)
                                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                                    @endforeach
                                                </select>
                                                @error('modalidadEjecucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-0 small"><b>Inicio de ejecución</b></label>
                                                <input type="date" class="form-control form-control-sm @error('fechaInicioEjecucion') is-invalid @enderror"
                                                       wire:model="fechaInicioEjecucion">
                                                @error('fechaInicioEjecucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-0 small"><b>Término de ejecución</b></label>
                                                <input type="date" class="form-control form-control-sm @error('fechaTerminoEjecucion') is-invalid @enderror"
                                                       wire:model="fechaTerminoEjecucion">
                                                @error('fechaTerminoEjecucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Nota --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Nota</b></label>
                                <textarea class="form-control form-control-sm @error('nota') is-invalid @enderror"
                                          rows="2" wire:model="nota" maxlength="2000"
                                          placeholder="Observaciones del aviso (opcional)"></textarea>
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
                        <span wire:loading.remove wire:target="guardar">Registrar aviso</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
