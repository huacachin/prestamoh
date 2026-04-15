<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">SUCURSALES : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{ route('settings.headquarters.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Sucursales</span>
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
            <div class="card">
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
                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Orden (*)</label>
                                <input type="number" class="form-control form-control-sm @error('sort_order') is-invalid @enderror"
                                       placeholder="0"
                                       wire:model.defer="sort_order">
                                @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nombre (*)</label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                       placeholder="Nombre de la sucursal"
                                       wire:model.defer="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Estado (*)</label>
                                <select class="form-select form-select-sm @error('status') is-invalid @enderror"
                                        wire:model.defer="status">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="update">
                            Guardar cambios
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="questionDelete({{ $headquarterId }})">
                            Eliminar
                        </button>
                        <a href="{{ route('settings.headquarters.index') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
