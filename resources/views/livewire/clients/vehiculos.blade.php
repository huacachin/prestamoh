<div>
    {{-- Campos traídos por la consulta de placa: en rojo. --}}
    <style>
        .campo-api {
            color: #c0392b !important;
            font-weight: 600;
            border-color: #e6a6a0 !important;
            background-color: #fff7f6 !important;
        }
    </style>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Vehículos <span class="text-muted small fw-normal">({{ $listado->count() }})</span>
        </h6>
        @if($puedeEditar && ! $creando && ! $editandoId)
            <button type="button" class="btn btn-sm btn-success" wire:click="nuevo">
                <i class="ti ti-plus"></i> Agregar vehículo
            </button>
        @endif
    </div>

    @if($msg)
        @php
            $cls = match($msgType) { 'ok' => 'alert-success', 'warn' => 'alert-warning', default => 'alert-danger' };
            $ico = match($msgType) { 'ok' => 'ti-circle-check', 'warn' => 'ti-alert-triangle', default => 'ti-alert-circle' };
        @endphp
        <div class="alert {{ $cls }} py-2 mb-2 d-flex align-items-center gap-2">
            <i class="ti {{ $ico }} f-s-16"></i><span class="small">{{ $msg }}</span>
        </div>
    @endif

    {{-- ── Formulario de alta / edición ── --}}
    @if($creando || $editandoId)
        <div class="border rounded p-2 mb-3" style="background:#fcfcfa;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-dark">
                    {{ $editandoId ? 'Editando vehículo' : 'Nuevo vehículo' }}
                </span>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 mb-2">
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Placa</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm text-uppercase @error('placa') is-invalid @enderror"
                               wire:model.defer="placa" maxlength="10" placeholder="ABC-123">
                        <button type="button" class="btn btn-danger" wire:click="consultarPlaca"
                                wire:loading.attr="disabled" wire:target="consultarPlaca" title="Buscar datos de la placa">
                            <span wire:loading.remove wire:target="consultarPlaca"><i class="ti ti-search"></i></span>
                            <span wire:loading wire:target="consultarPlaca" class="small">…</span>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Marca</label>
                    <input type="text" class="form-control form-control-sm @if(in_array('marca', $autoCampos)) campo-api @endif" wire:model.defer="marca" placeholder="Toyota">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Modelo</label>
                    <input type="text" class="form-control form-control-sm @if(in_array('modelo', $autoCampos)) campo-api @endif" wire:model.defer="modelo" placeholder="Hiace">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Valor del vehículo (S/)</label>
                    <input type="number" step="0.01" min="0" style="background-color:#fff9db;"
                           class="form-control form-control-sm @error('valor') is-invalid @enderror"
                           wire:model.defer="valor" placeholder="0.00">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">N° de Motor</label>
                    <input type="text" class="form-control form-control-sm text-uppercase @if(in_array('nro_motor', $autoCampos)) campo-api @endif" wire:model.defer="nro_motor">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">N° de Serie</label>
                    <input type="text" class="form-control form-control-sm text-uppercase @if(in_array('nro_serie', $autoCampos)) campo-api @endif" wire:model.defer="nro_serie">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Categoría</label>
                    <select class="form-select form-select-sm select2-tags" wire:model.defer="categoria"
                            data-placeholder="— Seleccione —">
                        <option value=""></option>
                        @foreach(\App\Support\VehiculoCatalogos::paraValor(\App\Support\VehiculoCatalogos::CATEGORIAS, $categoria) as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Año de Modelo</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="anio_modelo" placeholder="2018">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Carrocería</label>
                    <select class="form-select form-select-sm select2-tags" wire:model.defer="carroceria"
                            data-placeholder="— Seleccione —">
                        <option value=""></option>
                        @foreach(\App\Support\VehiculoCatalogos::paraValor(\App\Support\VehiculoCatalogos::CARROCERIAS, $carroceria) as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Color</label>
                    <select class="form-select form-select-sm select2-tags @if(in_array('color', $autoCampos)) campo-api @endif" wire:model.defer="color"
                            data-placeholder="— Seleccione —">
                        <option value=""></option>
                        @foreach(\App\Support\VehiculoCatalogos::paraValor(\App\Support\VehiculoCatalogos::COLORES, $color) as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Combustible</label>
                    <select class="form-select form-select-sm select2-tags" wire:model.defer="combustible"
                            data-placeholder="— Seleccione —">
                        <option value=""></option>
                        @foreach(\App\Support\Combustibles::paraValor($combustible) as $comb)
                            <option value="{{ $comb }}">{{ $comb }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-dark" wire:click="guardar"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <i class="ti ti-device-floppy"></i>
                    <span wire:loading.remove wire:target="guardar">{{ $editandoId ? 'Guardar cambios' : 'Agregar' }}</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
                <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelar">Cancelar</button>
            </div>
        </div>
    @endif

    {{-- ── Listado ── --}}
    @if($listado->isEmpty())
        <div class="text-center text-muted py-4 border rounded" style="border-style:dashed !important;">
            <i class="ti ti-car f-s-32 d-block mb-2"></i>
            <div class="small">Este cliente no tiene vehículos registrados.</div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-autofit mb-0" style="font-size: 12px;">
                <thead class="bg-primary">
                    {{-- Motor y serie son códigos largos (hasta 30 car.): van sin
                         cortar y con ancho propio; marca/modelo y color ceden espacio. --}}
                    <tr>
                        <th class="text-center" width="80">Placa</th>
                        <th width="190">Marca / Modelo</th>
                        <th class="text-center" width="180">N° Motor</th>
                        <th class="text-center" width="180">N° Serie</th>
                        <th class="text-center" width="60">Año</th>
                        <th width="120">Color</th>
                        <th class="text-end" width="95">Valor (S/)</th>
                        @if($puedeEditar)<th class="text-center" width="75">Op.</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($listado as $v)
                        <tr wire:key="veh-{{ $v->id }}">
                            <td class="text-center fw-bold">{{ $v->placa }}</td>
                            <td>{{ trim($v->marca.' '.$v->modelo) ?: '—' }}</td>
                            <td class="text-center" style="white-space:nowrap;">{{ $v->nro_motor ?: '—' }}</td>
                            <td class="text-center" style="white-space:nowrap;">{{ $v->nro_serie ?: '—' }}</td>
                            <td class="text-center">{{ $v->anio_modelo ?: '—' }}</td>
                            <td>{{ $v->color ?: '—' }}</td>
                            <td class="text-end">{{ $v->valor !== null ? number_format((float) $v->valor, 2) : '—' }}</td>
                            @if($puedeEditar)
                                <td class="text-center" style="white-space:nowrap;">
                                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1"
                                            wire:click="editar({{ $v->id }})" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs {{ $v->copropietarios->isNotEmpty() ? 'btn-dark' : 'btn-outline-dark' }} py-0 px-1"
                                            wire:click="abrirCopro({{ $v->id }})" title="Copropietario">
                                        <i class="ti ti-users"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1"
                                            wire:click="eliminar({{ $v->id }})"
                                            wire:confirm="¿Eliminar el vehículo {{ $v->placa }}? Esta acción no se puede deshacer."
                                            title="Eliminar">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>

                        {{-- Copropietarios: habilitan el contrato de DOS deudores
                             (a.3.x) con este mismo vehículo compartido. --}}
                        @if($v->copropietarios->isNotEmpty() || $coproVehiculoId === $v->id)
                            <tr wire:key="veh-copro-{{ $v->id }}" class="table-light">
                                <td colspan="{{ $puedeEditar ? 8 : 7 }}" class="py-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                                        <span class="text-muted"><i class="ti ti-users"></i> Copropietarios:</span>
                                        @forelse($v->copropietarios as $cop)
                                            <span class="badge bg-dark" wire:key="copro-{{ $v->id }}-{{ $cop->id }}">
                                                {{ $cop->fullName() }} — {{ $cop->documento }}
                                                @if($puedeEditar)
                                                    <a href="#" class="text-white ms-1" title="Quitar copropietario"
                                                       wire:click.prevent="quitarCopro({{ $v->id }}, {{ $cop->id }})"
                                                       wire:confirm="¿Quitar a {{ $cop->fullName() }} como copropietario de {{ $v->placa }}?">
                                                        <i class="ti ti-x"></i>
                                                    </a>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-muted fst-italic">ninguno</span>
                                        @endforelse
                                    </div>

                                    @if($puedeEditar && $coproVehiculoId === $v->id)
                                        {{-- Resultados EN FLUJO NORMAL (la fila crece), nunca
                                             position-absolute: el overflow del .table-responsive
                                             recortaba el desplegable al escribir. --}}
                                        <div class="mt-1" style="max-width: 420px;">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="Buscar cliente por nombre o DNI (mín. 2 caracteres)…"
                                                   wire:model.live.debounce.300ms="buscarCopro">
                                            @if($coproCandidatos->isNotEmpty())
                                                <div class="list-group mt-1 shadow-sm"
                                                     style="max-height:200px; overflow:auto;">
                                                    @foreach($coproCandidatos as $cand)
                                                        <button type="button" class="list-group-item list-group-item-action py-1 small"
                                                                wire:key="copro-cand-{{ $v->id }}-{{ $cand->id }}"
                                                                wire:click="vincularCopro({{ $v->id }}, {{ $cand->id }})">
                                                            {{ $cand->fullName() }} — {{ $cand->documento }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($buscarCopro)) >= 2)
                                                <div class="small text-muted mt-1">Sin resultados para "{{ $buscarCopro }}".</div>
                                            @endif

                                            @unless($coproCreando)
                                                <button type="button" class="btn btn-sm btn-outline-success mt-1"
                                                        wire:click="abrirCrearCopro">
                                                    <i class="ti ti-user-plus"></i> No está registrado: crear persona
                                                </button>
                                            @endunless
                                        </div>

                                        {{-- Alta rápida de PERSONA RELACIONADA: solo los datos que el
                                             contrato exige. No aparece en el listado de clientes ni en
                                             reportes (es_relacionado) hasta que pida su propio crédito. --}}
                                        @if($coproCreando)
                                            <div class="border rounded p-2 mt-2" style="background:#f6fbf7; max-width: 900px;">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-success">Nueva persona relacionada</span>
                                                    <span class="small text-muted">No aparece en la lista de clientes; solo firma contratos.</span>
                                                </div>

                                                @if($coproDocMsg)
                                                    <div class="alert alert-info py-1 px-2 mb-2 small">{{ $coproDocMsg }}</div>
                                                @endif

                                                <div class="row g-2">
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Documento *</label>
                                                        <div class="input-group input-group-sm">
                                                            <select class="form-select form-select-sm" style="max-width:4.8rem;"
                                                                    wire:model.live="nuevoCopro.tipo_documento">
                                                                <option value="DNI">DNI</option>
                                                                <option value="CE">CE</option>
                                                            </select>
                                                            <input type="text" class="form-control form-control-sm @error('nuevoCopro.documento') is-invalid @enderror"
                                                                   wire:model.blur="nuevoCopro.documento">
                                                            <button type="button" class="btn btn-danger" wire:click="consultarDocCopro"
                                                                    wire:loading.attr="disabled" wire:target="consultarDocCopro" title="Consultar documento">
                                                                <span wire:loading.remove wire:target="consultarDocCopro"><i class="ti ti-search"></i></span>
                                                                <span wire:loading wire:target="consultarDocCopro" class="small">…</span>
                                                            </button>
                                                        </div>
                                                        @error('nuevoCopro.documento') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Ap. paterno *</label>
                                                        <input type="text" class="form-control form-control-sm @error('nuevoCopro.apellido_pat') is-invalid @enderror @if(in_array('apellido_pat', $autoCopro)) campo-api @endif"
                                                               wire:model.blur="nuevoCopro.apellido_pat">
                                                        @error('nuevoCopro.apellido_pat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Ap. materno</label>
                                                        <input type="text" class="form-control form-control-sm @if(in_array('apellido_mat', $autoCopro)) campo-api @endif" wire:model.blur="nuevoCopro.apellido_mat">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Nombres *</label>
                                                        <input type="text" class="form-control form-control-sm @error('nuevoCopro.nombre') is-invalid @enderror @if(in_array('nombre', $autoCopro)) campo-api @endif"
                                                               wire:model.blur="nuevoCopro.nombre">
                                                        @error('nuevoCopro.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>

                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label mb-0 small fw-semibold">Sexo *</label>
                                                        <select class="form-select form-select-sm @if(in_array('sexo', $autoCopro)) campo-api @endif" wire:model.blur="nuevoCopro.sexo">
                                                            <option value="M">Masculino</option>
                                                            <option value="F">Femenino</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label mb-0 small fw-semibold">Nacionalidad *</label>
                                                        <select class="form-select form-select-sm" wire:model.blur="nuevoCopro.nacionalidad">
                                                            @foreach(\App\Support\Documentos\Nacionalidades::OPCIONES as $op)
                                                                <option value="{{ $op }}">{{ $op }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label mb-0 small fw-semibold">Ocupación *</label>
                                                        <select class="form-select form-select-sm" wire:model.blur="nuevoCopro.ocupacion">
                                                            @foreach(\App\Livewire\Clients\Create::OCUPACIONES as $v => $et)
                                                                <option value="{{ $v }}">{{ $et }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label mb-0 small fw-semibold">Estado civil *</label>
                                                        <select class="form-select form-select-sm" wire:model.blur="nuevoCopro.estado_civil">
                                                            @foreach(\App\Livewire\Clients\Create::ESTADOS_CIVILES as $v => $et)
                                                                <option value="{{ $v }}">{{ $et }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <label class="form-label mb-0 small fw-semibold">Correo *</label>
                                                        <input type="email" class="form-control form-control-sm @error('nuevoCopro.email') is-invalid @enderror"
                                                               wire:model.blur="nuevoCopro.email" placeholder="persona@correo.com">
                                                        @error('nuevoCopro.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>

                                                    <div class="col-12 col-md-4">
                                                        <label class="form-label mb-0 small fw-semibold">Dirección *</label>
                                                        <input type="text" class="form-control form-control-sm @error('nuevoCopro.direccion') is-invalid @enderror @if(in_array('direccion', $autoCopro)) campo-api @endif"
                                                               wire:model.blur="nuevoCopro.direccion">
                                                        @error('nuevoCopro.direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Distrito *</label>
                                                        <input type="text" class="form-control form-control-sm text-uppercase @error('nuevoCopro.distrito') is-invalid @enderror @if(in_array('distrito', $autoCopro)) campo-api @endif"
                                                               wire:model.blur="nuevoCopro.distrito">
                                                        @error('nuevoCopro.distrito') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label mb-0 small fw-semibold">Provincia *</label>
                                                        <select class="form-select form-select-sm @if(in_array('provincia', $autoCopro)) campo-api @endif" wire:model.blur="nuevoCopro.provincia">
                                                            @foreach(\App\Livewire\Clients\Create::PROVINCIAS as $v => $et)
                                                                <option value="{{ $v }}">{{ $et }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-0 small fw-semibold">Departamento *</label>
                                                        <input type="text" class="form-control form-control-sm text-uppercase @error('nuevoCopro.departamento') is-invalid @enderror @if(in_array('departamento', $autoCopro)) campo-api @endif"
                                                               wire:model.blur="nuevoCopro.departamento">
                                                        @error('nuevoCopro.departamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>

                                                <div class="d-flex gap-2 mt-2">
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="crearYVincularCopro"
                                                            wire:loading.attr="disabled" wire:target="crearYVincularCopro">
                                                        <i class="ti ti-user-plus"></i>
                                                        <span wire:loading.remove wire:target="crearYVincularCopro">Crear y vincular</span>
                                                        <span wire:loading wire:target="crearYVincularCopro">Creando…</span>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelarCrearCopro">Cancelar</button>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
