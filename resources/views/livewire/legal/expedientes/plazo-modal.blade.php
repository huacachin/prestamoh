<div>
    {{-- ═══ Modal de registro de plazo judicial (hijo de Legal\Expedientes\Show) ═══ --}}
    <div class="modal fade" id="plazoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:plazo-modal-open.window="modal.show()"
         x-on:plazo-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-alarm-plus text-danger"></i>
                        Agregar plazo
                        @if($expedienteLabel !== '')
                            <span class="text-muted d-block" style="font-size:11px;">{{ $expedienteLabel }}</span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        <div class="row g-2">
                            {{-- Descripción --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Descripción *</b></label>
                                <input type="text" class="form-control form-control-sm @error('descripcion') is-invalid @enderror"
                                       wire:model="descripcion" maxlength="255" autocomplete="off"
                                       placeholder="Ej. Presentar escrito de subsanación">
                                @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Fecha de vencimiento --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Vence el *</b></label>
                                <input type="date" class="form-control form-control-sm @error('fechaVencimiento') is-invalid @enderror"
                                       wire:model="fechaVencimiento">
                                @error('fechaVencimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text" style="font-size:10px;">
                                    El plazo entra a la campana legal {{ \App\Models\PlazoJudicial::DIAS_AVISO }} días antes de vencer.
                                </div>
                            </div>

                            {{-- Responsable --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Responsable *</b></label>
                                <select class="form-select form-select-sm @error('responsableId') is-invalid @enderror"
                                        wire:model="responsableId">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($usuarios as $usuario)
                                        <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                                    @endforeach
                                </select>
                                @error('responsableId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">Registrar plazo</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
