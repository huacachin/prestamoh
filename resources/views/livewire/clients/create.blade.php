<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">NUEVO CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Nuevo</span></li>
            </ul>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="card shadow-sm">
            <div class="card-body">

                {{-- ════════ Búsqueda DNI / RUC ════════ --}}
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold" style="color:red;">
                            INGRESE DNI O RUC
                        </label>
                        <input type="text"
                               class="form-control form-control-sm"
                               wire:model.defer="docBuscar"
                               name="docBuscar" autocomplete="off"
                               placeholder="DNI (8) o RUC (11)"
                               maxlength="11"
                               wire:keydown.enter.prevent="consultarDocumento"
                               style="border:2px solid #d9534f; color:#d9534f;">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-danger w-100"
                                wire:click="consultarDocumento"
                                wire:loading.attr="disabled" wire:target="consultarDocumento">
                            <span wire:loading.remove wire:target="consultarDocumento">
                                <i class="ti ti-search"></i> Consultar
                            </span>
                            <span wire:loading wire:target="consultarDocumento">
                                <i class="ti ti-loader-2"></i> Buscando…
                            </span>
                        </button>
                    </div>
                    <div class="col-md-6">
                        @if($docMsg)
                            @php
                                $alertClass = match($docMsgType) {
                                    'ok'   => 'alert-success',
                                    'warn' => 'alert-warning',
                                    'err'  => 'alert-danger',
                                    default => 'alert-info',
                                };
                                $icon = match($docMsgType) {
                                    'ok'   => 'ti-circle-check',
                                    'warn' => 'ti-alert-triangle',
                                    'err'  => 'ti-alert-circle',
                                    default => 'ti-info-circle',
                                };
                            @endphp
                            <div class="alert {{ $alertClass }} mb-0 py-1 px-2" style="font-size:12px;">
                                <i class="ti {{ $icon }}"></i> {{ $docMsg }}
                            </div>
                        @endif
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:12px;">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ════════ Datos Personales ════════ --}}
                <h6 class="mb-1" style="color:red;">Datos Personales</h6>
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">
                            Apellido Paterno
                            @if($tipo_documento === 'RUC')
                                <span class="text-muted small">(opcional)</span>
                            @endif
                        </label>
                        <input type="text" class="form-control form-control-sm @error('apellido_pat') is-invalid @enderror"
                               wire:model.defer="apellido_pat" name="apellido_pat" autocomplete="family-name" placeholder="Apellido Paterno">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">
                            Apellido Materno <span class="text-muted small">(opcional)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="apellido_mat" name="apellido_mat" autocomplete="off" placeholder="Apellido Materno">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">
                            {{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}
                        </label>
                        <input type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror"
                               wire:model.defer="nombre"
                               name="nombre" autocomplete="given-name"
                               placeholder="{{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Nacionalidad</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $nacionalidad }}" readonly>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Sexo</label>
                        <select class="form-select form-select-sm" wire:model.defer="sexo">
                            <option value="F">Femenino</option>
                            <option value="M">Masculino</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Nacimiento</label>
                        <input type="text" autocomplete="off" name="fecha_nacimiento" class="form-control form-control-sm dates" wire:model.defer="fecha_nacimiento">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">DNI / RUC</label>
                        <input type="text" class="form-control form-control-sm @error('documento') is-invalid @enderror"
                               wire:model.defer="documento" name="documento" autocomplete="off" placeholder="Número de documento" maxlength="11">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Expediente</label>
                        <input type="number" class="form-control form-control-sm @error('expediente') is-invalid @enderror"
                               wire:model.defer="expediente" name="expediente" autocomplete="off" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Tipo</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $tipo_documento }}" readonly>
                    </div>
                </div>

                <hr class="my-2" style="border-color:#e8e2d5;">

                {{-- ════════ Dirección Principal ════════ --}}
                <h6 class="mb-1" style="color:red;">Dirección Principal</h6>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label mb-0 small fw-semibold">Dirección</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="direccion" name="direccion" autocomplete="street-address" placeholder="Av. Arequipa Nro. 3400">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Giro</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="giro" name="giro" autocomplete="on" placeholder="Giro del negocio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">T. Crédito</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="zona" name="zona" autocomplete="on" placeholder="Zona o ruta">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Capital</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                               style="background-color:#fff9db;"
                               wire:model.live="capital" name="capital" autocomplete="off" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Crédito ({{ \App\Models\Client::LINEA_CREDITO_PCT }}% del capital)</label>
                        <input type="text" class="form-control form-control-sm bg-light" readonly tabindex="-1"
                               value="{{ is_numeric($capital) ? number_format((float) $capital * (\App\Models\Client::LINEA_CREDITO_PCT / 100), 2) : '' }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Celular / Whatsapp</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="celular1" name="celular1" autocomplete="tel" placeholder="999-999-999">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Celular Secundario</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="celular2" name="celular2" autocomplete="tel" placeholder="999-999-999">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-0 small fw-semibold">Ejecutivo / Asesor</label>
                        <select class="form-select form-select-sm" wire:model.defer="asesor_id">
                            <option value="">— Seleccione —</option>
                            @foreach($asesores as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- ════════ Acciones ════════ --}}
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button type="submit" class="btn btn-sm btn-dark" wire:loading.attr="disabled" wire:target="save">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="save">Aceptar</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" wire:click="clean">
                        <i class="ti ti-eraser"></i> Limpiar
                    </button>
                    <a href="{{ route('clients.index') }}" class="btn btn-sm btn-danger">
                        <i class="ti ti-x"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
