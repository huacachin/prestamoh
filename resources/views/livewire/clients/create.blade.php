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
                {{-- Campos traídos por la API (RENIEC/SUNAT/placa): en rojo para
                     distinguirlos de lo tecleado a mano. --}}
                <style>
                    .campo-api {
                        color: #c0392b !important;
                        font-weight: 600;
                        border-color: #e6a6a0 !important;
                        background-color: #fff7f6 !important;
                    }
                </style>

                {{-- ════════ Pasos del wizard ════════ --}}
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    @foreach([1 => 'Datos del cliente', 2 => 'Vehículos (opcional)'] as $n => $titulo)
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold"
                                  style="width:26px; height:26px; font-size:12px;
                                         background:{{ $paso === $n ? '#d9534f' : ($paso > $n ? '#198754' : '#e9ecef') }};
                                         color:{{ $paso >= $n ? '#fff' : '#6c757d' }};">
                                @if($paso > $n)<i class="ti ti-check"></i>@else{{ $n }}@endif
                            </span>
                            <span class="small {{ $paso === $n ? 'fw-bold' : 'text-muted' }}">{{ $titulo }}</span>
                        </div>
                        @if($n === 1)
                            <div class="flex-grow-1" style="height:2px; background:{{ $paso > 1 ? '#198754' : '#e9ecef' }}; max-width:120px;"></div>
                        @endif
                    @endforeach
                </div>

                {{-- Resumen de errores --}}
                @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-2">
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- PASO 1 · Datos del cliente                              --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div @if($paso !== 1) style="display:none;" @endif>

                    {{-- ════════ Búsqueda por documento ════════ --}}
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold" style="color:red;">TIPO DE DOCUMENTO</label>
                            <select class="form-select form-select-sm" wire:model.live="tipo_documento">
                                @foreach(\App\Livewire\Clients\Create::TIPOS_DOCUMENTO as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold" style="color:red;">
                                {{ $tipo_documento === 'RUC' ? 'INGRESE RUC' : ($tipo_documento === 'CE' ? 'INGRESE CARNÉ' : 'INGRESE DNI') }}
                            </label>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   style="border:2px solid #d9534f;"
                                   wire:model.defer="docBuscar"
                                   wire:keydown.enter.prevent="consultarDocumento"
                                   name="docBuscar" autocomplete="off" maxlength="12"
                                   placeholder="{{ $tipo_documento === 'RUC' ? '11 dígitos' : ($tipo_documento === 'CE' ? 'N° de carné' : '8 dígitos') }}">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-danger w-100"
                                    wire:click="consultarDocumento"
                                    wire:loading.attr="disabled" wire:target="consultarDocumento">
                                <span wire:loading.remove wire:target="consultarDocumento"><i class="ti ti-search"></i> Consultar</span>
                                <span wire:loading wire:target="consultarDocumento">Buscando…</span>
                            </button>
                        </div>
                    </div>

                    @if($docMsg)
                        @php
                            $cls = match($docMsgType) { 'ok' => 'alert-success', 'warn' => 'alert-warning', default => 'alert-danger' };
                            $ico = match($docMsgType) { 'ok' => 'ti-circle-check', 'warn' => 'ti-alert-triangle', default => 'ti-alert-circle' };
                        @endphp
                        <div class="alert {{ $cls }} py-2 mb-2 d-flex align-items-center gap-2">
                            <i class="ti {{ $ico }} f-s-16"></i>
                            <span class="small">{{ $docMsg }}</span>
                        </div>
                    @endif

                    {{-- ════════ Datos Personales ════════ --}}
                    <h6 class="mb-1" style="color:red;">Datos Personales</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">
                                Apellido Paterno
                                @if($tipo_documento === 'RUC')<span class="text-muted small">(opcional)</span>@endif
                            </label>
                            <input type="text" class="form-control form-control-sm @error('apellido_pat') is-invalid @enderror @if(in_array('apellido_pat', $autoCliente)) campo-api @endif"
                                   wire:model.defer="apellido_pat" name="apellido_pat" autocomplete="family-name" placeholder="Apellido Paterno">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">
                                Apellido Materno <span class="text-muted small">(opcional)</span>
                            </label>
                            <input type="text" class="form-control form-control-sm @if(in_array('apellido_mat', $autoCliente)) campo-api @endif"
                                   wire:model.defer="apellido_mat" name="apellido_mat" autocomplete="off" placeholder="Apellido Materno">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">
                                {{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}
                            </label>
                            <input type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror @if(in_array('nombre', $autoCliente)) campo-api @endif"
                                   wire:model.defer="nombre" name="nombre" autocomplete="given-name"
                                   placeholder="{{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Nacionalidad</label>
                            {{-- No flexiona: el contrato dice PERUANO / VENEZOLANO
                                 tanto para deudor como para deudora. --}}
                            <select class="form-select form-select-sm @error('nacionalidad') is-invalid @enderror"
                                    wire:model.defer="nacionalidad" name="nacionalidad">
                                @foreach(\App\Support\Documentos\Nacionalidades::OPCIONES as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </select>
                            @error('nacionalidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Sexo</label>
                            <select class="form-select form-select-sm @if(in_array('sexo', $autoCliente)) campo-api @endif" wire:model.defer="sexo">
                                <option value="F">Femenino</option>
                                <option value="M">Masculino</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Nacimiento</label>
                            <input type="text" autocomplete="off" name="fecha_nacimiento" class="form-control form-control-sm dates @if(in_array('fecha_nacimiento', $autoCliente)) campo-api @endif" wire:model.defer="fecha_nacimiento">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">N° de documento</label>
                            <input type="text" class="form-control form-control-sm @error('documento') is-invalid @enderror @if(in_array('documento', $autoCliente)) campo-api @endif"
                                   wire:model.defer="documento" name="documento" autocomplete="off" placeholder="Número de documento" maxlength="12">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Expediente</label>
                            <input type="number" class="form-control form-control-sm @error('expediente') is-invalid @enderror"
                                   wire:model.defer="expediente" name="expediente" autocomplete="off" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Tipo</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ \App\Livewire\Clients\Create::TIPOS_DOCUMENTO[$tipo_documento] ?? $tipo_documento }}" readonly>
                        </div>

                        {{-- Campos obligatorios agregados el 28/08 --}}
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Correo electrónico</label>
                            <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                                   wire:model.defer="email" name="email" autocomplete="email" placeholder="cliente@correo.com">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Ocupación</label>
                            <select class="form-select form-select-sm @error('ocupacion') is-invalid @enderror" wire:model.defer="ocupacion">
                                @foreach(\App\Livewire\Clients\Create::OCUPACIONES as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small fw-semibold">Estado civil</label>
                            <select class="form-select form-select-sm @error('estado_civil') is-invalid @enderror" wire:model.defer="estado_civil">
                                @foreach(\App\Livewire\Clients\Create::ESTADOS_CIVILES as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="my-2" style="border-color:#e8e2d5;">

                    {{-- ════════ Dirección Principal ════════ --}}
                    <h6 class="mb-1" style="color:red;">Dirección Principal</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label mb-0 small fw-semibold">Dirección</label>
                            <input type="text" class="form-control form-control-sm @error('direccion') is-invalid @enderror @if(in_array('direccion', $autoCliente)) campo-api @endif"
                                   wire:model.defer="direccion" name="direccion" autocomplete="street-address" placeholder="Av. Arequipa Nro. 3400">
                            @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        {{-- Ubigeo: arma el domicilio legal de la cláusula PRIMERO.
                             La provincia es combo porque cambia la frase registral
                             (Lima colapsa, Callao es "PROVINCIA CONSTITUCIONAL"). --}}
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Distrito</label>
                            <input type="text" class="form-control form-control-sm text-uppercase @error('distrito') is-invalid @enderror @if(in_array('distrito', $autoCliente)) campo-api @endif"
                                   wire:model.defer="distrito" name="distrito" placeholder="Ate">
                            @error('distrito') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Provincia</label>
                            <select class="form-select form-select-sm @error('provincia') is-invalid @enderror @if(in_array('provincia', $autoCliente)) campo-api @endif"
                                    wire:model.live="provincia">
                                @foreach(\App\Livewire\Clients\Create::PROVINCIAS as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('provincia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Departamento</label>
                            <input type="text" class="form-control form-control-sm text-uppercase @error('departamento') is-invalid @enderror @if(in_array('departamento', $autoCliente)) campo-api @endif"
                                   wire:model.defer="departamento" name="departamento" placeholder="Lima">
                            @error('departamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Giro</label>
                            <input type="text" class="form-control form-control-sm"
                                   wire:model.defer="giro" name="giro" autocomplete="on" placeholder="Giro del negocio">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">T. Crédito</label>
                            <select class="form-select form-select-sm @error('zona') is-invalid @enderror" wire:model.defer="zona">
                                <option value="">— Seleccione —</option>
                                @foreach(\App\Support\TiposCredito::OPCIONES as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </select>
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

                    {{-- Acciones paso 1 --}}
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button type="button" class="btn btn-sm btn-dark" wire:click="siguientePaso">
                            Continuar <i class="ti ti-arrow-right"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="clean">
                            <i class="ti ti-eraser"></i> Limpiar
                        </button>
                        <a href="{{ route('clients.index') }}" class="btn btn-sm btn-danger">
                            <i class="ti ti-x"></i> Cancelar
                        </a>
                    </div>
                </div>

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- PASO 2 · Vehículos (varios, opcional)                  --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                <div @if($paso !== 2) style="display:none;" @endif>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div>
                            <h6 class="mb-0" style="color:red;">
                                Vehículos <span class="text-muted small fw-normal">(opcional · puedes registrar varios)</span>
                            </h6>
                            <div class="small text-muted">
                                Cliente: <b>{{ trim($apellido_pat.' '.$apellido_mat.' '.$nombre) }}</b> · Doc. {{ $documento }}
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" wire:click="agregarVehiculo">
                            <i class="ti ti-plus"></i> Agregar vehículo
                        </button>
                    </div>

                    @if($vehMsg)
                        @php
                            $vcls = match($vehMsgType) { 'ok' => 'alert-success', 'warn' => 'alert-warning', default => 'alert-danger' };
                            $vico = match($vehMsgType) { 'ok' => 'ti-circle-check', 'warn' => 'ti-alert-triangle', default => 'ti-alert-circle' };
                        @endphp
                        <div class="alert {{ $vcls }} py-2 mb-2 d-flex align-items-center gap-2">
                            <i class="ti {{ $vico }} f-s-16"></i>
                            <span class="small">{{ $vehMsg }}</span>
                        </div>
                    @endif

                    @forelse($vehiculos as $i => $v)
                        <div class="border rounded p-2 mb-2" wire:key="veh-{{ $i }}" style="background:#fcfcfa;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-dark">Vehículo {{ $i + 1 }}</span>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                        wire:click="quitarVehiculo({{ $i }})" title="Quitar este vehículo">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Placa</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm text-uppercase @error('vehiculos.'.$i.'.placa') is-invalid @enderror"
                                               wire:model.defer="vehiculos.{{ $i }}.placa" maxlength="10" placeholder="ABC-123">
                                        <button type="button" class="btn btn-danger"
                                                wire:click="consultarPlaca({{ $i }})"
                                                wire:loading.attr="disabled" wire:target="consultarPlaca({{ $i }})"
                                                title="Buscar datos de la placa">
                                            <span wire:loading.remove wire:target="consultarPlaca({{ $i }})"><i class="ti ti-search"></i></span>
                                            <span wire:loading wire:target="consultarPlaca({{ $i }})" class="small">…</span>
                                        </button>
                                    </div>
                                    @error('vehiculos.'.$i.'.placa') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Marca</label>
                                    <input type="text" class="form-control form-control-sm @if(in_array('marca', $autoVehiculo[$i] ?? [])) campo-api @endif" wire:model.defer="vehiculos.{{ $i }}.marca" placeholder="Toyota">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Modelo</label>
                                    <input type="text" class="form-control form-control-sm @if(in_array('modelo', $autoVehiculo[$i] ?? [])) campo-api @endif" wire:model.defer="vehiculos.{{ $i }}.modelo" placeholder="Hiace">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Valor del vehículo (S/)</label>
                                    <input type="number" step="0.01" min="0" style="background-color:#fff9db;"
                                           class="form-control form-control-sm @error('vehiculos.'.$i.'.valor') is-invalid @enderror"
                                           wire:model.defer="vehiculos.{{ $i }}.valor" placeholder="0.00">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">N° de Motor</label>
                                    <input type="text" class="form-control form-control-sm text-uppercase @if(in_array('nro_motor', $autoVehiculo[$i] ?? [])) campo-api @endif" wire:model.defer="vehiculos.{{ $i }}.nro_motor">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">N° de Serie</label>
                                    <input type="text" class="form-control form-control-sm text-uppercase @if(in_array('nro_serie', $autoVehiculo[$i] ?? [])) campo-api @endif" wire:model.defer="vehiculos.{{ $i }}.nro_serie">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Categoría</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="vehiculos.{{ $i }}.categoria" placeholder="M2-C3">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Año de Modelo</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="vehiculos.{{ $i }}.anio_modelo" placeholder="2018">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Carrocería</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="vehiculos.{{ $i }}.carroceria" placeholder="Microbús">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Color</label>
                                    <input type="text" class="form-control form-control-sm @if(in_array('color', $autoVehiculo[$i] ?? [])) campo-api @endif" wire:model.defer="vehiculos.{{ $i }}.color" placeholder="Blanco">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small fw-semibold">Combustible</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="vehiculos.{{ $i }}.combustible" placeholder="GNV / Gasolina">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 border rounded" style="border-style:dashed !important;">
                            <i class="ti ti-car f-s-32 d-block mb-2"></i>
                            <div class="small">
                                Este cliente aún no tiene vehículos registrados.<br>
                                Puedes <b>agregar uno o varios</b>, o guardar el cliente sin vehículos.
                            </div>
                        </div>
                    @endforelse

                    {{-- Acciones paso 2 --}}
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="pasoAnterior">
                            <i class="ti ti-arrow-left"></i> Atrás
                        </button>
                        <button type="submit" class="btn btn-sm btn-dark" wire:loading.attr="disabled" wire:target="save">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="save">Guardar cliente{{ count($vehiculos) > 0 ? ' y '.count($vehiculos).' vehículo'.(count($vehiculos) > 1 ? 's' : '') : '' }}</span>
                            <span wire:loading wire:target="save">Guardando…</span>
                        </button>
                        <a href="{{ route('clients.index') }}" class="btn btn-sm btn-danger">
                            <i class="ti ti-x"></i> Cancelar
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
