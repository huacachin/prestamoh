<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESO GENERALES : NUEVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="{{ route('cash.expenses') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Egresos</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Nuevo</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    @unless($canEditDate)
                        <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                            <i class="ti ti-info-circle"></i>
                            La fecha queda fija en hoy. Solo SuperUsuario / Administrador / Director pueden modificarla.
                        </div>
                    @endunless

                    @unless($canChooseOtros)
                        <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                            <i class="ti ti-info-circle"></i>
                            Como Asesor / Cobranza solo puedes registrar egresos tipo <strong>Fijos</strong> con motivo <strong>Diario</strong>.
                        </div>
                    @endunless

                    {{-- Paso 1: selector --}}
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Tipo de Egreso</label>
                            <select class="form-select form-select-sm @error('modo') is-invalid @enderror"
                                    wire:model.live="modo"
                                    {{ $canChooseOtros ? '' : 'disabled' }}>
                                <option value="">— Seleccione —</option>
                                <option value="Fijos">Fijos</option>
                                @if($canChooseOtros)
                                    <option value="Otros">Otros</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    {{-- Paso 2: form principal --}}
                    @if($modo)
                        <hr class="my-2" style="border-color:#e8e2d5;">

                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Fecha (*)</label>
                                <input type="date"
                                       class="form-control form-control-sm @error('date') is-invalid @enderror @unless($canEditDate) bg-light @endunless"
                                       wire:model.defer="date"
                                       @unless($canEditDate) readonly @endunless>
                            </div>

                            <div class="col-md-{{ $modo === 'Fijos' ? 3 : 4 }}">
                                <label class="form-label mb-0 small fw-semibold">A (*)</label>
                                @if($modo === 'Fijos')
                                    <select class="form-select form-select-sm @error('reason') is-invalid @enderror"
                                            wire:model.live="reason"
                                            {{ !$canChooseOtros ? 'disabled' : '' }}>
                                        <option value="">— Seleccione —</option>
                                        @foreach($concepts as $c)
                                            <option value="{{ $c->name }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text"
                                           class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                           wire:model.defer="reason"
                                           placeholder="Motivo libre (proveedor, ej. 'Recarga teléfono')"
                                           maxlength="255">
                                @endif
                            </div>

                            @if($modo === 'Fijos')
                                <div class="col-md-1">
                                    <label class="form-label mb-0 small fw-semibold">Cant.</label>
                                    <input type="number" min="1" step="1"
                                           class="form-control form-control-sm"
                                           wire:model.live.debounce.400ms="cantidad">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0 small fw-semibold">Precio</label>
                                    <input type="number" min="0" step="0.01"
                                           class="form-control form-control-sm"
                                           wire:model.live.debounce.400ms="precio_unitario"
                                           placeholder="0.00">
                                </div>
                            @endif

                            <div class="col-md-{{ $modo === 'Fijos' ? 4 : 6 }}">
                                <label class="form-label mb-0 small fw-semibold">Detalle (*)</label>
                                <input type="text"
                                       class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       wire:model.defer="detail"
                                       placeholder="Descripción del egreso"
                                       maxlength="500">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Monto (*)</label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control form-control-sm @error('total') is-invalid @enderror"
                                       wire:model.defer="total"
                                       placeholder="0.00"
                                       style="background:yellow;">
                                @if($modo === 'Fijos' && $precio_unitario > 0)
                                    <small class="text-muted" style="font-size:10px;">
                                        Auto: {{ $cantidad }} × {{ number_format((float)$precio_unitario, 2) }}. Editable.
                                    </small>
                                @endif
                            </div>

                            <div class="col-md-1">
                                <label class="form-label mb-0 small fw-semibold">Moneda</label>
                                <input type="text" class="form-control form-control-sm bg-light"
                                       value="Soles" readonly>
                            </div>

                            {{-- Campos exclusivos del legacy de gastos --}}
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">T.Comp.</label>
                                <input type="text"
                                       class="form-control form-control-sm @error('document_type') is-invalid @enderror"
                                       wire:model.defer="document_type"
                                       placeholder="Boleta, Factura, Recibo, etc."
                                       maxlength="100">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-0 small fw-semibold">Respons.</label>
                                <input type="text"
                                       class="form-control form-control-sm @error('in_charge') is-invalid @enderror"
                                       wire:model.defer="in_charge"
                                       placeholder="Nombre del responsable"
                                       maxlength="255">
                            </div>
                        </div>

                        {{-- Botones + hint discreto, todo centrado --}}
                        <div class="d-flex flex-column align-items-center gap-1 mt-3">
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-dark"
                                        wire:click="save"
                                        wire:loading.attr="disabled" wire:target="save">
                                    <i class="ti ti-device-floppy"></i>
                                    <span wire:loading.remove wire:target="save">Guardar y subir adjuntos</span>
                                    <span wire:loading wire:target="save">Guardando…</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="clear">
                                    <i class="ti ti-eraser"></i> Limpiar
                                </button>
                                <a href="{{ route('cash.expenses') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-arrow-back"></i> Regresar
                                </a>
                            </div>
                            <small class="text-muted" style="font-size:11px;">
                                <i class="ti ti-info-circle"></i>
                                Los adjuntos se cargan en el siguiente paso, después de guardar.
                            </small>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-arrow-up" style="font-size:24px; opacity:.5;"></i>
                            <p class="mt-2 mb-0 small">Seleccione el Tipo de Egreso para continuar.</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

</div>
