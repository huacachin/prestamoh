<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CRÉDITOS : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Editar</span></li>
            </ul>
        </div>
    </div>

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

            @if($hasPayments)
                <div class="alert alert-warning py-2 mb-3">
                    <i class="ti ti-alert-triangle"></i>
                    Este crédito ya tiene pagos registrados. Solo puedes modificar algunos campos.
                </div>
            @endif

            <div class="row g-3">
                <div class="col-auto">
                    <label class="form-label">Fecha Préstamo</label>
                    <input type="date" class="form-control form-control-sm" wire:model.defer="fecha_prestamo"
                           @if($hasPayments) disabled style="opacity:.6" @endif>
                </div>
                <div class="col-auto">
                    <label class="form-label">Importe</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="importe"
                           @if($hasPayments) disabled style="opacity:.6" @endif>
                </div>
                <div class="col-auto">
                    <label class="form-label">Cuotas</label>
                    <input type="number" class="form-control form-control-sm" wire:model.defer="cuotas"
                           @if($hasPayments) disabled style="opacity:.6" @endif>
                </div>
                <div class="col-auto">
                    <label class="form-label">Tipo Planilla</label>
                    <select class="form-select form-select-sm" wire:model.defer="tipo_planilla"
                            @if($hasPayments) disabled style="opacity:.6" @endif>
                        <option value="4">Diario</option>
                        <option value="1">Semanal</option>
                        <option value="3">Mensual</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Interés %</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="interes"
                           @if($hasPayments) disabled style="opacity:.6" @endif>
                </div>
                <div class="col-auto">
                    <label class="form-label">Moneda</label>
                    <select class="form-select form-select-sm" wire:model.defer="moneda">
                        <option value="PEN">Soles</option>
                        <option value="USD">Dólares</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Documento</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="documento">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Glosa</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="glosa">
                </div>
                <div class="col-auto">
                    <label class="form-label">Situación</label>
                    <select class="form-select form-select-sm" wire:model.defer="situacion">
                        <option value="Activo">Activo</option>
                        <option value="Cancelado">Cancelado</option>
                        <option value="Refinanciado">Refinanciado</option>
                        <option value="Eliminado">Eliminado</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" wire:click="update">Guardar cambios</button>
                <button class="btn btn-sm btn-danger" wire:click="questionDelete({{ $creditId }})">Eliminar</button>
                <a href="{{ route('credits.show', $creditId) }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    </div>
</div>
