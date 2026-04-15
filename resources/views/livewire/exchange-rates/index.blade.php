<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">TIPO DE CAMBIO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Tipo Cambio</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0">
                                    <label class="form-label">Fecha</label>
                                    <input type="date" class="form-control form-control-sm"
                                           wire:model.defer="fecha">
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="form-label">Compra</label>
                                    <input type="number" step="0.0001" class="form-control form-control-sm"
                                           placeholder="0.0000" wire:model.defer="compra">
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="form-label">Venta</label>
                                    <input type="number" step="0.0001" class="form-control form-control-sm"
                                           placeholder="0.0000" wire:model.defer="venta">
                                </div>
                                <button class="btn btn-sm btn-primary flex-shrink-0" wire:click="save">
                                    <i class="ti ti-device-floppy f-s-12"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Compra</th>
                                <th>Venta</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rates as $rate)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $rate->fecha->format('d/m/Y') }}</td>
                                    <td>{{ number_format($rate->compra, 4) }}</td>
                                    <td>{{ number_format($rate->venta, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-muted">No hay registros</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
