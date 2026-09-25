<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EDITAR INGRESO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="{{ route('cash.incomes') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Ingresos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Editar</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Fecha (*)</label>
                                <input type="text" autocomplete="off" class="form-control form-control-sm @error('date') is-invalid @enderror @if($canEditDate) dates @endif"
                                       wire:model.defer="date" @unless($canEditDate) disabled @endunless>
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">A (*)</label>
                                <select class="form-select form-select-sm @error('reason') is-invalid @enderror"
                                        wire:model.defer="reason">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($concepts as $concept)
                                        <option value="{{ $concept->name }}">{{ $concept->name }}</option>
                                    @endforeach
                                </select>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Detalle</label>
                                <input type="text" name="detail" autocomplete="on" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Detalle del ingreso"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Total (*)</label>
                                <input type="number" step="0.01" name="total" autocomplete="off" class="form-control form-control-sm @error('total') is-invalid @enderror"
                                       placeholder="0.00"
                                       wire:model.defer="total">
                                @error('total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                    </div>

                    {{-- 26/09: las imágenes se eligen aquí (varias, con miniaturas) y se
                         suben con el MISMO "Guardar cambios"; antes había un campo suelto
                         y otro botón "Subir" en la galería de abajo. --}}
                    <div class="mb-3">
                        <label class="form-label">Imágenes del comprobante (opcional)</label>
                        @include('livewire.cash.partials.zona-imagenes')
                        <small class="text-muted">Se suben al pulsar "Guardar cambios".</small>
                        @if($current_image)
                            <div><small class="text-muted">Imagen antigua: {{ basename($current_image) }}</small></div>
                        @endif
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update"
                                wire:loading.attr="disabled" wire:target="update,files,removeFile">
                            <i class="ti ti-device-floppy f-s-12"></i>
                            <span wire:loading.remove wire:target="update">Guardar cambios{{ ! empty($files) ? ' y subir '.count($files).(count($files) === 1 ? ' imagen' : ' imágenes') : '' }}</span>
                            <span wire:loading wire:target="update">Guardando…</span>
                        </button>
                        @can('caja.eliminar')
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $incomeId }})">
                            <i class="ti ti-trash f-s-12"></i> Eliminar
                        </button>
                        @endcan
                        <a href="{{ route('cash.incomes') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>

            {{-- Galería de adjuntos ya subidos (ver y eliminar; la subida va arriba) --}}
            <livewire:cash.income-gallery :id="$incomeId" :embedded="true" />
        </div>
    </div>

</div>
