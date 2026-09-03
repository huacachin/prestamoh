<div>
    {{-- ═══ Modal de edición de la garantía (hijo de Legal\Garantias\Show) ═══ --}}
    <div class="modal fade" id="garantiaEditarModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:garantia-editar-modal-open.window="modal.show()"
         x-on:garantia-editar-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-pencil text-danger"></i>
                        Editar garantía
                        @if($garantiaLabel !== '')
                            <span class="text-muted d-block" style="font-size:11px;">{{ $garantiaLabel }}</span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        <div class="row g-2">

                            {{-- Monto máximo de la garantía --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Monto máximo de la garantía (S/) *</b></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('montoGravamen') is-invalid @enderror"
                                       wire:model="montoGravamen">
                                @error('montoGravamen') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if($totalCronograma !== null)
                                    <div class="form-text" style="font-size:11px;">
                                        Total real del cronograma del crédito: <b>S/ {{ number_format($totalCronograma, 2) }}</b>
                                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" style="font-size:11px;"
                                                wire:click="usarTotalCronograma">usar este monto</button>
                                        <br>El contrato solo se emite si ambos montos cuadran (±S/ 1.00).
                                    </div>
                                @endif
                            </div>

                            {{-- Parámetros --}}
                            <div class="col-md-6">
                                <label class="form-label mb-0 small"><b>Parámetros del contrato</b></label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="edGps" wire:model="gps">
                                    <label class="form-check-label small" for="edGps">Vehículo con GPS (cláusula de credenciales)</label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="edCustodia" wire:model="custodia">
                                    <label class="form-check-label small" for="edCustodia">Custodia (garantía con posesión)</label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="edRevision" wire:model="requiereRevision">
                                    <label class="form-check-label small" for="edRevision">Requiere revisión (datos importados por validar)</label>
                                </div>
                            </div>

                            {{-- Valor de los vehículos --}}
                            @if(count($vehiculosInfo))
                                <div class="col-12">
                                    <label class="form-label mb-1 small"><b>Valor de los bienes (S/)</b> <span class="text-muted">— cláusula de valor del contrato</span></label>
                                    <div class="row g-2">
                                        @foreach($vehiculosInfo as $vehiculoId => $info)
                                            <div class="col-md-6">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text" style="font-size:11px;">{{ $info }}</span>
                                                    <input type="number" step="0.01" min="0"
                                                           class="form-control @error('valoresVehiculos.'.$vehiculoId) is-invalid @enderror"
                                                           wire:model="valoresVehiculos.{{ $vehiculoId }}"
                                                           placeholder="Valor S/">
                                                </div>
                                                @error('valoresVehiculos.'.$vehiculoId)
                                                    <div class="text-danger" style="font-size:11px;">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Observaciones --}}
                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Observaciones</b></label>
                                <textarea class="form-control form-control-sm @error('observaciones') is-invalid @enderror"
                                          rows="2" wire:model="observaciones"></textarea>
                                @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-danger" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="guardar"><i class="ti ti-device-floppy"></i> Guardar</span>
                                <span wire:loading wire:target="guardar">Guardando…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
