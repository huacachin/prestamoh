<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CONCEPTOS : AGREGAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="{{ route('settings.concepts.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Conceptos</span>
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
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Código (*)</label>
                                <input type="text" class="form-control form-control-sm @error('code') is-invalid @enderror"
                                       placeholder="Ingresar Código"
                                       wire:model.defer="code">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Concepto (*)</label>
                                <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                       placeholder="Ingresar Nombre"
                                       wire:model.defer="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Estado (*)</label>
                                <select class="form-select form-select-sm @error('status') is-invalid @enderror"
                                        wire:model.defer="status">
                                    <option value="active">Vigente</option>
                                    <option value="inactive">Cancelado</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto">
                            <div class="mb-3">
                                <label class="form-label">Tipo (*)</label>
                                <select class="form-select form-select-sm @error('type') is-invalid @enderror"
                                        wire:model.defer="type">
                                    <option value="ingreso">Ingreso</option>
                                    <option value="egreso">Egreso</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save">
                            Agregar
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="clear">
                            Limpiar
                        </button>
                        <a href="{{ route('settings.concepts.index') }}" class="btn btn-sm btn-secondary">Volver</a>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
