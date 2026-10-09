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
                    'prestamos' => ['Préstamos', 'ti-cash'],
                    'vehiculos' => ['Vehículos', 'ti-car'],
                    'documentos' => ['Documentos', 'ti-file-text'],
                    'adjuntos' => ['Adjuntos', 'ti-photo'],
                    'gps' => ['GPS', 'ti-map-pin'],
                ];
            @endphp
            {{-- En móvil las pestañas no se apilan: se deslizan en una tira
                 horizontal (patrón habitual y no roba alto de pantalla). --}}
            <style>
                .tabs-cliente { display:flex; gap:4px; overflow-x:auto; -webkit-overflow-scrolling:touch;
                                border-bottom:1px solid #e9ecef; padding-bottom:8px; margin-bottom:16px; }
                .tabs-cliente::-webkit-scrollbar { height:0; }
                .tabs-cliente .btn { white-space:nowrap; flex:0 0 auto; font-size:12px; line-height:1.2; }
                @media (max-width: 575.98px) {
                    .tabs-cliente .btn { font-size:13px; padding:6px 10px; }
                }
            </style>
            <div class="tabs-cliente">
                @foreach($tabs as $clave => [$titulo, $icono])
                    <button type="button"
                            class="btn btn-sm py-1 px-2 {{ $tab === $clave ? 'btn-dark' : 'btn-link text-muted text-decoration-none' }}"
                            wire:click="$set('tab', '{{ $clave }}')">
                        <i class="ti {{ $icono }} f-s-14"></i> {{ $titulo }}
                    </button>
                @endforeach
            </div>

            {{-- ════════ Préstamos (10/10: lista + alta en la misma pestaña) ════════ --}}
            @if($tab === 'prestamos')
                <livewire:clients.prestamos :id="$clientId" :key="'pre-'.$clientId" />
            @endif

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

            {{-- ════════ GPS (antes columnas C. y N. del listado) ════════ --}}
            @if($tab === 'gps')
                <livewire:clients.gps :id="$clientId" :key="'gps-'.$clientId" />
            @endif

            {{-- ════════ Datos del cliente ════════ --}}
            <div @if($tab !== 'datos') style="display:none;" @endif>
            <form x-on:submit.prevent="$captura('update')" data-captura>

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
                        Apellidos, nombres, documento, sexo, nacimiento y nacionalidad solo pueden ser editados por SuperUsuario.
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

                    {{-- Obs. 6.1 del Área Legal (29/09): sexo, nacimiento y nacionalidad
                         se bloquean como la identidad (solo SuperUsuario). --}}
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Sexo</label>
                        <select class="form-select form-select-sm @unless($puedeEditarIdentidad) bg-light @endunless" wire:model.defer="sexo"
                                @unless($puedeEditarIdentidad) disabled @endunless>
                            <option value="F">Femenino</option>
                            <option value="M">Masculino</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Nacimiento</label>
                        @if($puedeEditarIdentidad)
                            <input type="text" autocomplete="off" name="fecha_nacimiento" class="form-control form-control-sm dates" wire:model.defer="fecha_nacimiento">
                        @else
                            <input type="text" class="form-control form-control-sm bg-light" value="{{ $fecha_nacimiento }}" readonly>
                        @endif
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

                    {{-- Datos que el contrato de garantía exige en la cláusula
                         PRIMERO y que antes solo se capturaban en el alta. --}}
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Nacionalidad</label>
                        {{-- No flexiona: el contrato dice PERUANO / VENEZOLANO
                             tanto para deudor como para deudora. --}}
                        <select class="form-select form-select-sm select2-simple @error('nacionalidad') is-invalid @enderror"
                                wire:model.defer="nacionalidad" @unless($puedeEditarIdentidad) disabled @endunless>
                            @foreach(\App\Support\Documentos\Nacionalidades::paraValor($nacionalidad) as $opcion)
                                <option value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                        @error('nacionalidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Ocupación</label>
                        <select class="form-select form-select-sm select2-tags @error('ocupacion') is-invalid @enderror" wire:model.defer="ocupacion">
                            @foreach(\App\Livewire\Clients\Create::ocupacionesPara($ocupacion) as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('ocupacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Estado civil</label>
                        <select class="form-select form-select-sm select2-tags @error('estado_civil') is-invalid @enderror" wire:model.defer="estado_civil">
                            @foreach(\App\Livewire\Clients\Create::estadosCivilesPara($estado_civil) as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('estado_civil') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Correo</label>
                        <input type="email" class="form-control form-control-sm correo-sugerencias @error('email') is-invalid @enderror"
                               wire:model.defer="email" autocomplete="off" placeholder="cliente@correo.com">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                    {{-- Ubigeo: arma el domicilio legal del contrato (la provincia
                         gobierna la frase registral, ver DomicilioLegal). Los tres
                         son select2 con búsqueda; el wire:key fuerza nodo nuevo
                         cuando cambia la cascada para que select2 se reinicie
                         limpio tras el morph de Livewire. --}}
                    <div class="col-md-3" wire:key="ubigeo-dep">
                        <label class="form-label mb-0 small fw-semibold">Departamento</label>
                        <select class="form-select form-select-sm select2-tags @error('departamento') is-invalid @enderror"
                                wire:model.live="departamento">
                            @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::departamentos(), $departamento) as $dep)
                                <option value="{{ $dep }}">{{ $dep }}</option>
                            @endforeach
                        </select>
                        @error('departamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3" wire:key="ubigeo-prov-{{ md5((string) $departamento) }}">
                        <label class="form-label mb-0 small fw-semibold">Provincia</label>
                        <select class="form-select form-select-sm select2-tags @error('provincia') is-invalid @enderror"
                                wire:model.live="provincia">
                            @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::provinciasDe($departamento), $provincia) as $prov)
                                <option value="{{ $prov }}">{{ $prov }}</option>
                            @endforeach
                        </select>
                        @error('provincia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3" wire:key="ubigeo-dist-{{ md5($departamento.'|'.$provincia) }}">
                        <label class="form-label mb-0 small fw-semibold">Distrito</label>
                        {{-- tags: permite texto libre (historial migrado / casos borde) --}}
                        <select class="form-select form-select-sm select2-tags @error('distrito') is-invalid @enderror"
                                wire:model="distrito" data-placeholder="Busca o escribe...">
                            <option value=""></option>
                            @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::distritosDe($departamento, $provincia), $distrito) as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                        @error('distrito') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                        <select class="form-select form-select-sm select2-tags @error('zona') is-invalid @enderror" wire:model.defer="zona" data-placeholder="— Seleccione —">
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

                <hr class="my-2" style="border-color:#e8e2d5;">

                {{-- ════════ Copropietarios (10/10, Antony): acordeón con el nombre de cada uno y, al pulsar +,
                     el formulario para editar SOLO los datos que piden los documentos (contrato/anexos). ════════ --}}
                <h6 class="mb-1 seccion-copropietarios" style="color:red;">Copropietarios</h6>
                @if($copropietarios->isEmpty())
                    <div class="small mb-2"><span class="text-muted">ninguno</span>
                        <a href="#" class="ms-1" wire:click.prevent="$set('tab', 'vehiculos')">agregar desde Vehículos</a>
                    </div>
                @else
                    {{-- Enter dentro del acordeón no debe enviar el formulario del titular --}}
                    <div class="acordeon-copro mb-2" x-on:keydown.enter.prevent>
                        @foreach($copropietarios as $c)
                            @php $p = $c['persona']; $abierto = $coproAbierto === $p->id; @endphp
                            <div class="border rounded mb-1" wire:key="copro-{{ $p->id }}">
                                <div class="d-flex align-items-center gap-2 px-2 py-1 copro-cabecera" style="background:#f8f9fa; cursor:pointer;"
                                     wire:click="abrirCopro({{ $p->id }})">
                                    <button type="button" class="btn btn-xs {{ $abierto ? 'btn-dark' : 'btn-outline-dark' }}" style="padding: 0 6px; font-size: 11px; line-height: 18px;"
                                            title="{{ $abierto ? 'Cerrar' : 'Editar datos para los documentos' }}">
                                        <i class="ti {{ $abierto ? 'ti-minus' : 'ti-plus' }}"></i>
                                    </button>
                                    <b class="small">{{ $p->fullName() }}</b>
                                    <span class="small text-muted">{{ $p->tipo_documento }} {{ $p->documento }} · {{ count($c['placas']) === 1 ? 'vehículo' : 'vehículos' }} {{ implode(', ', $c['placas']) }}</span>
                                    <a href="{{ route('clients.edit', $p->id) }}" class="ms-auto small" wire:click.stop title="Ficha completa">ficha</a>
                                </div>
                                @if($abierto)
                                    @php $identidad = $puedeEditarIdentidad || $p->es_relacionado; @endphp
                                    <div class="p-2 border-top" style="background:#fcfcfa;">
                                        @unless($identidad)
                                            <div class="small text-muted mb-1"><i class="ti ti-lock"></i> Es cliente titular: su documento, nombres, sexo y nacionalidad se editan desde su ficha (identidad).</div>
                                        @endunless
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <label class="form-label mb-0 small fw-semibold">Documento *</label>
                                                <div class="input-group input-group-sm">
                                                    <select class="form-select form-select-sm" style="max-width:4.8rem;" wire:model="coproForm.tipo_documento" @disabled(! $identidad)>
                                                        <option value="DNI">DNI</option>
                                                        <option value="CE">CE</option>
                                                        <option value="RUC">RUC</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm @error('coproForm.documento') is-invalid @enderror" wire:model="coproForm.documento" @disabled(! $identidad)>
                                                </div>
                                                @error('coproForm.documento') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label mb-0 small fw-semibold">Ap. paterno *</label>
                                                <input type="text" class="form-control form-control-sm @error('coproForm.apellido_pat') is-invalid @enderror" wire:model="coproForm.apellido_pat" @disabled(! $identidad)>
                                                @error('coproForm.apellido_pat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label mb-0 small fw-semibold">Ap. materno</label>
                                                <input type="text" class="form-control form-control-sm" wire:model="coproForm.apellido_mat" @disabled(! $identidad)>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label mb-0 small fw-semibold">Nombres *</label>
                                                <input type="text" class="form-control form-control-sm @error('coproForm.nombre') is-invalid @enderror" wire:model="coproForm.nombre" @disabled(! $identidad)>
                                                @error('coproForm.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Sexo *</label>
                                                <select class="form-select form-select-sm" wire:model="coproForm.sexo" @disabled(! $identidad)>
                                                    <option value="M">Masculino</option>
                                                    <option value="F">Femenino</option>
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Nacionalidad *</label>
                                                <select class="form-select form-select-sm" wire:model="coproForm.nacionalidad" @disabled(! $identidad)>
                                                    @foreach(\App\Support\Documentos\Nacionalidades::OPCIONES as $op)
                                                        <option value="{{ $op }}">{{ $op }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Ocupación *</label>
                                                <select class="form-select form-select-sm @error('coproForm.ocupacion') is-invalid @enderror" wire:model="coproForm.ocupacion">
                                                    @foreach(\App\Livewire\Clients\Create::OCUPACIONES as $v => $et)
                                                        <option value="{{ $v }}">{{ $et }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Estado civil *</label>
                                                <select class="form-select form-select-sm @error('coproForm.estado_civil') is-invalid @enderror" wire:model="coproForm.estado_civil">
                                                    @foreach(\App\Livewire\Clients\Create::ESTADOS_CIVILES as $v => $et)
                                                        <option value="{{ $v }}">{{ $et }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Celular / WhatsApp *</label>
                                                <input type="text" inputmode="tel" maxlength="20" class="form-control form-control-sm @error('coproForm.celular1') is-invalid @enderror" wire:model="coproForm.celular1" placeholder="987654321">
                                                @error('coproForm.celular1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label mb-0 small fw-semibold">Correo *</label>
                                                <input type="email" class="form-control form-control-sm @error('coproForm.email') is-invalid @enderror" wire:model="coproForm.email" placeholder="persona@correo.com">
                                                @error('coproForm.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label mb-0 small fw-semibold">Dirección *</label>
                                                <input type="text" class="form-control form-control-sm @error('coproForm.direccion') is-invalid @enderror" wire:model="coproForm.direccion">
                                                @error('coproForm.direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Distrito *</label>
                                                <input type="text" class="form-control form-control-sm text-uppercase @error('coproForm.distrito') is-invalid @enderror" wire:model="coproForm.distrito">
                                                @error('coproForm.distrito') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label mb-0 small fw-semibold">Provincia *</label>
                                                <select class="form-select form-select-sm @error('coproForm.provincia') is-invalid @enderror" wire:model="coproForm.provincia">
                                                    @foreach(\App\Livewire\Clients\Create::PROVINCIAS as $v => $et)
                                                        <option value="{{ $v }}">{{ $et }}</option>
                                                    @endforeach
                                                </select>
                                                @error('coproForm.provincia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-dark" wire:click="guardarCopro" wire:loading.attr="disabled" wire:target="guardarCopro">
                                                <i class="ti ti-device-floppy"></i>
                                                <span wire:loading.remove wire:target="guardarCopro">Guardar copropietario</span>
                                                <span wire:loading wire:target="guardarCopro">Guardando…</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelarCopro">Cancelar</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($copropiedades->isNotEmpty())
                    <div class="small mb-2">
                        <i class="ti ti-user-check"></i> <b>Es copropietario en:</b>
                        {{ $copropiedades->map(fn ($c) => ($c['placas'] ? implode(', ', $c['placas']).' de ' : 'contrato de ').$c['titular']->fullName())->implode(' · ') }}
                    </div>
                @endif

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
                                wire:click="questionDelete({{ $clientId }})" @creadoEl($client)>
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
