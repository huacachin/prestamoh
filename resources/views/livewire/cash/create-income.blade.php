<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">NUEVO INGRESO</h4>
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
                    <span class="f-s-14">Nuevo</span>
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
                                <input type="date" class="form-control form-control-sm @error('date') is-invalid @enderror"
                                       wire:model.defer="date">
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Motivo (*)</label>
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
                                <input type="text" class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       placeholder="Detalle del ingreso"
                                       wire:model.defer="detail">
                                @error('detail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Total (*)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('total') is-invalid @enderror"
                                       placeholder="0.00"
                                       wire:model.defer="total">
                                @error('total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Imagen (opcional)</label>
                                <input type="file" class="form-control form-control-sm @error('image') is-invalid @enderror"
                                       wire:model="image" accept="image/*">
                                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save">
                            <i class="ti ti-device-floppy f-s-12"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="clear">
                            Limpiar
                        </button>
                        <a href="{{ route('cash.incomes') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
