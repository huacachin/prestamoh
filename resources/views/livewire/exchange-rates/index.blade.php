<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">TIPO DE CAMBIO</h4>
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
                <div class="card-body">

                    @if ($saved)
                        <div class="alert alert-success py-2">
                            <i class="ti ti-check"></i> Se actualizó el Tipo de Cambio con éxito
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Form --}}
                    <form wire:submit.prevent="save">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Fecha</b></label>
                                <input type="date" class="form-control form-control-sm"
                                       wire:model="fecha">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Venta</b></label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm"
                                       placeholder="0.0000" wire:model="venta">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Compra</b></label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm"
                                       placeholder="0.0000" wire:model="compra">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ti ti-device-floppy f-s-12"></i> Actualizar
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr>

                    {{-- Link SUNAT --}}
                    <div>
                        <a href="#" class="text-primary text-decoration-none"
                           onclick="event.preventDefault(); document.getElementById('tiposunat').style.display = (document.getElementById('tiposunat').style.display === 'none' || document.getElementById('tiposunat').style.display === '') ? 'block' : 'none';">
                            <i class="ti ti-external-link"></i> Consultar tipo de cambio en la SUNAT
                        </a>
                    </div>

                    {{-- Iframe SUNAT (colapsable) --}}
                    <div id="tiposunat" style="display: none; background: #333; padding: 8px; margin-top: 12px; border-radius: 4px;">
                        <div class="text-end mb-2">
                            <button type="button" class="btn btn-sm btn-light"
                                    onclick="document.getElementById('tiposunat').style.display='none';">
                                <i class="ti ti-x f-s-12"></i> Cerrar
                            </button>
                        </div>
                        <iframe src="https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias"
                                width="100%" height="300px" frameborder="0" scrolling="no"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
