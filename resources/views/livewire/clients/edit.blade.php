<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EDITAR CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Editar #{{ $client->expediente ?? $clientId }}</span></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            {{-- ════════ Pestañas (compactas: píldoras pequeñas) ════════ --}}
            @php
                $tabs = [
                    'datos' => ['Datos', 'ti-user'],
                    'vehiculos' => ['Vehículos', 'ti-car'],
                    'documentos' => ['Documentos', 'ti-file-text'],
                    'adjuntos' => ['Adjuntos', 'ti-photo'],
                ];
            @endphp
            <div class="d-flex flex-wrap gap-1 mb-3 pb-2" style="border-bottom:1px solid #e9ecef;">
                @foreach($tabs as $clave => [$titulo, $icono])
                    <button type="button"
                            class="btn btn-sm py-1 px-2 {{ $tab === $clave ? 'btn-dark' : 'btn-link text-muted text-decoration-none' }}"
                            style="font-size:12px; line-height:1.2;"
                            wire:click="$set('tab', '{{ $clave }}')">
                        <i class="ti {{ $icono }} f-s-14"></i> {{ $titulo }}
                    </button>
                @endforeach
            </div>

            {{-- ════════ Vehículos ════════ --}}
            @if($tab === 'vehiculos')
                <livewire:clients.vehiculos :id="$clientId" :key="'veh-'.$clientId" />
            @endif

            {{-- ════════ Documentos ════════ --}}
            @if($tab === 'documentos')
                <livewire:clients.documentos :id="$clientId" :embebido="true" :key="'doc-'.$clientId" />
            @endif

            {{-- ════════ Adjuntos ════════ --}}
            @if($tab === 'adjuntos')
                <livewire:clients.gallery :id="$clientId" :embebido="true" :key="'gal-'.$clientId" />
            @endif

            {{-- ════════ Datos del cliente ════════ --}}
            <div @if($tab !== 'datos') style="display:none;" @endif>
            <form wire:submit.prevent="update">

                @if ($errors->any())
                    <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                @unless($puedeEditarIdentidad)
                    <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                        <i class="ti ti-info-circle"></i>
                        Apellidos, nombres y documento solo pueden ser editados por SuperUsuario.
                    </div>
                @endunless

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
                        <input type="text" class="form-control form-control-sm @error('apellido_pat') is-invalid @enderror @unless($puedeEditarIdentidad) bg-light @endunless"
                               wire:model.defer="apellido_pat" name="apellido_pat" autocomplete="family-name" placeholder="Apellido Paterno"
                               @unless($puedeEditarIdentidad) readonly @endunless>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">
                            Apellido Materno <span class="text-muted small">(opcional)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm @unless($puedeEditarIdentidad) bg-light @endunless"
                               wire:model.defer="apellido_mat" name="apellido_mat" autocomplete="off" placeholder="Apellido Materno"
                               @unless($puedeEditarIdentidad) readonly @endunless>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">
                            {{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}
                        </label>
                        <input type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror @unless($puedeEditarIdentidad) bg-light @endunless"
                               wire:model.defer="nombre"
                               name="nombre" autocomplete="given-name"
                               placeholder="{{ $tipo_documento === 'RUC' ? 'Razón social' : 'Nombres' }}"
                               @unless($puedeEditarIdentidad) readonly @endunless>
                    </div>

                    @unless($tieneCreditosVigentes)
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Estado</label>
                            <select class="form-select form-select-sm" wire:model.defer="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                    @else
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">Estado</label>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="Activo · {{ $tieneCreditosVigentes ? 'tiene créditos vigentes' : '' }}"
                                   readonly>
                        </div>
                    @endunless

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
                        <input type="text" class="form-control form-control-sm @error('documento') is-invalid @enderror @unless($puedeEditarIdentidad) bg-light @endunless"
                               wire:model.defer="documento" name="documento" autocomplete="off" placeholder="Número de documento" maxlength="11"
                               @unless($puedeEditarIdentidad) readonly @endunless>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Tipo</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $tipo_documento }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Expediente</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $client->expediente }}" readonly>
                    </div>
                </div>

                <hr class="my-2" style="border-color:#e8e2d5;">

                {{-- ════════ Dirección Principal ════════ --}}
                <h6 class="mb-1" style="color:red;">Dirección Principal</h6>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label mb-0 small fw-semibold">Dirección</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="direccion" name="direccion" autocomplete="street-address" placeholder="Av. Arequipa Nro. 3400">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold">Referencia</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="referencia" name="referencia" autocomplete="off" placeholder="Cerca de…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Giro</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="giro" name="giro" autocomplete="on" placeholder="Giro del negocio">
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
                        <label class="form-label mb-0 small fw-semibold">Teléfono Secundario</label>
                        <input type="text" class="form-control form-control-sm"
                               wire:model.defer="telefono_secundario" name="telefono_secundario" autocomplete="tel" placeholder="999-9999">
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
                        <label class="form-label mb-0 small fw-semibold">Asesor</label>
                        <select class="form-select form-select-sm" wire:model.defer="asesor_id">
                            <option value="">— Seleccione —</option>
                            @foreach($asesores as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">T.Credito</label>
                        <select class="form-select form-select-sm @error('zona') is-invalid @enderror" wire:model.defer="zona">
                            <option value="">— Seleccione —</option>
                            @foreach(\App\Support\TiposCredito::paraValor($zona) as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Casa / Negocio: solo SuperUsuario y solo si la coord existe --}}
                    @if($puedeEditarIdentidad && ($latitud || $latitud2))
                        <div class="col-md-6 d-flex align-items-end gap-3">
                            @if($latitud)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="casa" wire:model.defer="casa">
                                    <label class="form-check-label small" for="casa">
                                        <i class="ti ti-home"></i> Resetear coords <strong>Casa</strong>
                                    </label>
                                </div>
                            @endif
                            @if($latitud2)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="negocio" wire:model.defer="negocio">
                                    <label class="form-check-label small" for="negocio">
                                        <i class="ti ti-building-store"></i> Resetear coords <strong>Negocio</strong>
                                    </label>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ════════ Acciones ════════ --}}
                <div class="d-flex gap-2 justify-content-center mt-3">
                    @if($puedeGuardar)
                        <button type="submit" class="btn btn-sm btn-dark"
                                wire:loading.attr="disabled" wire:target="update">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="update">Guardar</span>
                            <span wire:loading wire:target="update">Guardando…</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                wire:click="questionDelete({{ $clientId }})">
                            <i class="ti ti-user-off"></i> Desactivar
                        </button>
                    @endif
                    <a href="{{ route('clients.index') }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-arrow-back"></i> Regresar
                    </a>
                </div>
            </form>
            </div>

        </div>
    </div>
</div>
