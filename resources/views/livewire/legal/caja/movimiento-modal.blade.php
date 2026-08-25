<div>
    {{-- ═══ Modal de movimiento manual de la Caja Legal (hijo de Legal\Caja\Index) ═══ --}}
    <div class="modal fade" id="movimientoCajaModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:movimiento-modal-open.window="modal.show()"
         x-on:movimiento-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-cash text-danger"></i>
                        Registrar movimiento de la Caja Legal
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        <div class="row g-2">

                            {{-- Tipo --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Tipo</b> <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" id="movTipoIngreso"
                                               value="ingreso" wire:model.live="tipo">
                                        <label class="form-check-label small text-success fw-bold" for="movTipoIngreso">
                                            Ingreso
                                        </label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" id="movTipoEgreso"
                                               value="egreso" wire:model.live="tipo">
                                        <label class="form-check-label small text-danger fw-bold" for="movTipoEgreso">
                                            Egreso
                                        </label>
                                    </div>
                                </div>
                                @error('tipo') <div class="text-danger" style="font-size:11px;">{{ $message }}</div> @enderror
                            </div>

                            {{-- Fecha --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Fecha</b> <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                                       wire:model="fecha" max="{{ now()->toDateString() }}">
                                @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Motivo --}}
                            <div class="col-md-8">
                                <label class="form-label mb-0 small"><b>Motivo</b> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                       wire:model="motivo" maxlength="255" list="motivosCajaLegal"
                                       autocomplete="off" placeholder="Ej. Tarifa constitución SIGM">
                                <datalist id="motivosCajaLegal">
                                    @foreach(\App\Livewire\Legal\Caja\MovimientoModal::MOTIVOS_SUGERIDOS as $sugerido)
                                        <option value="{{ $sugerido }}"></option>
                                    @endforeach
                                </datalist>
                                @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Monto --}}
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Monto S/</b> <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control form-control-sm @error('monto') is-invalid @enderror"
                                       wire:model="monto" placeholder="0.00">
                                @error('monto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Detalle --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Detalle</b></label>
                                <textarea class="form-control form-control-sm @error('detalle') is-invalid @enderror"
                                          rows="2" wire:model="detalle" maxlength="500"
                                          placeholder="Ej. Cliente, expediente o concepto del movimiento (opcional)"></textarea>
                                @error('detalle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="form-text" style="font-size:10px;">
                                    <i class="ti ti-info-circle"></i>
                                    Solo movimientos manuales del área (tarifas cobradas, gastos sueltos).
                                    Las tasas de avisos SIGM y los costos notariales se registran desde su documento.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm {{ $tipo === 'ingreso' ? 'btn-success' : 'btn-danger' }}"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">
                            Registrar {{ $tipo === 'ingreso' ? 'ingreso' : 'egreso' }}
                        </span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
