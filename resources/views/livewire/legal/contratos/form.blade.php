<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">GENERAR CONTRATO — GARANTÍA #{{ $garantia->id }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="{{ route('legal.garantias.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Garantías</span>
                    </a>
                </li>
                <li class="d-flex">
                    <a href="{{ route('legal.garantias.show', $garantia->id) }}" class="f-s-14">Garantía #{{ $garantia->id }}</a>
                </li>
                <li class="breadcrumb-item active"><span>Contrato</span></li>
            </ul>
        </div>
    </div>

    {{-- ─── Contexto de la garantía ─── --}}
    <div class="border rounded p-2 mb-3 bg-light" style="font-size:12px;">
        <div class="row g-2">
            <div class="col-md-3"><b>Deudor:</b> {{ $garantia->client?->fullName() ?? '—' }}</div>
            <div class="col-md-2"><b>Crédito N°:</b> {{ $garantia->credit?->id ?? '—' }}</div>
            <div class="col-md-2"><b>Importe:</b> S/ {{ $garantia->credit ? number_format($garantia->credit->importe, 2) : '—' }}</div>
            <div class="col-md-2"><b>Gravamen:</b> S/ {{ number_format($garantia->monto_gravamen, 2) }}</div>
            <div class="col-md-3">
                <b>Vehículo(s):</b> {{ $garantia->vehiculos->pluck('placa')->filter()->implode(', ') ?: '—' }}
                — GPS {{ $garantia->gps ? 'Sí' : 'No' }} / Custodia {{ $garantia->custodia ? 'Sí' : 'No' }}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">

                {{-- ─── Indicador de pasos ─── --}}
                @php
                    $titulosPasos = [1 => 'Parámetros', 2 => 'Voucher (Anexo 2)', 3 => 'Validación y emisión'];
                @endphp
                <div class="card-header py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @foreach($titulosPasos as $n => $titulo)
                            <span class="d-flex align-items-center gap-1"
                                  @if($n < $paso) wire:click="irAPaso({{ $n }})" style="cursor:pointer;" @endif>
                                <span class="badge rounded-pill {{ $n === $paso ? 'bg-primary' : ($n < $paso ? 'bg-success' : 'bg-secondary') }}">
                                    @if($n < $paso)<i class="ti ti-check"></i>@else{{ $n }}@endif
                                </span>
                                <span class="small {{ $n === $paso ? 'fw-bold' : 'text-muted' }}">{{ $titulo }}</span>
                            </span>
                            @if($n < 3)
                                <i class="ti ti-chevron-right text-muted f-s-12"></i>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- ════════════ PASO 1: Parámetros ════════════ --}}
                    @if($paso === 1)
                        <h6 class="fw-bold mb-2"><i class="ti ti-adjustments"></i> Parámetros del contrato</h6>

                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Fecha del contrato</label>
                                <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                                       wire:model="fecha" max="{{ now()->addDay()->toDateString() }}">
                            </div>
                        </div>

                        <label class="form-label mb-1 small fw-semibold">Destino del desembolso</label>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="destino-propio" value="propio" wire:model.live="destino">
                                <label class="form-check-label small" for="destino-propio">
                                    Cuenta {{ $tipoPersona === 'juridica' ? 'de la empresa deudora' : 'propia del deudor' }}
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="destino-tercero" value="tercero" wire:model.live="destino">
                                <label class="form-check-label small" for="destino-tercero">Depósito a un tercero autorizado</label>
                            </div>
                            @if($tipoPersona === 'juridica')
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" id="destino-gerente" value="gerente" wire:model.live="destino">
                                    <label class="form-check-label small" for="destino-gerente">Cuenta del gerente general</label>
                                </div>
                            @endif
                        </div>

                        @if($destino === 'tercero')
                            <div class="border rounded p-2 mb-3">
                                <div class="small fw-bold mb-1"><i class="ti ti-user-check"></i> Tercero autorizado a recibir el desembolso</div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-0 small fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('tercero.nombre') is-invalid @enderror"
                                               wire:model="tercero.nombre">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">DNI <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" maxlength="8" class="form-control form-control-sm @error('tercero.dni') is-invalid @enderror"
                                               wire:model="tercero.dni">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small fw-semibold">N° de cuenta <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('tercero.cuenta') is-invalid @enderror"
                                               wire:model="tercero.cuenta">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small fw-semibold">Motivo <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('tercero.motivo') is-invalid @enderror"
                                               wire:model="tercero.motivo" placeholder="Ej. El deudor no dispone de cuenta bancaria">
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($tipoPersona === 'juridica')
                            <div class="border rounded p-2 mb-3">
                                <div class="small fw-bold mb-1"><i class="ti ti-building"></i> Empresa deudora</div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-0 small fw-semibold">Razón social <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('empresa.razon_social') is-invalid @enderror"
                                               wire:model="empresa.razon_social">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">RUC <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" maxlength="11" class="form-control form-control-sm @error('empresa.ruc') is-invalid @enderror"
                                               wire:model="empresa.ruc">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Partida registral</label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('empresa.partida') is-invalid @enderror"
                                               wire:model="empresa.partida">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Oficina registral</label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('empresa.oficina_registral') is-invalid @enderror"
                                               wire:model="empresa.oficina_registral" placeholder="Ej. Huancayo">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Correo</label>
                                        <input type="email" autocomplete="off" class="form-control form-control-sm @error('empresa.correo') is-invalid @enderror"
                                               wire:model="empresa.correo">
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-2 mb-3">
                                <div class="small fw-bold mb-1"><i class="ti ti-user-star"></i> Gerente general</div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-0 small fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('gerente.nombre') is-invalid @enderror"
                                               wire:model="gerente.nombre">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">DNI <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off" maxlength="8" class="form-control form-control-sm @error('gerente.dni') is-invalid @enderror"
                                               wire:model="gerente.dni">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Género <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('gerente.genero') is-invalid @enderror" wire:model="gerente.genero">
                                            <option value="M">Masculino</option>
                                            <option value="F">Femenino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Ocupación</label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('gerente.ocupacion') is-invalid @enderror"
                                               wire:model="gerente.ocupacion">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Estado civil</label>
                                        <select class="form-select form-select-sm @error('gerente.estado_civil') is-invalid @enderror" wire:model="gerente.estado_civil">
                                            <option value="">Seleccione</option>
                                            @foreach($estadosCiviles as $ec)
                                                <option value="{{ $ec }}">{{ $ec }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label mb-0 small fw-semibold">Domicilio</label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm @error('gerente.domicilio') is-invalid @enderror"
                                               wire:model="gerente.domicilio">
                                    </div>
                                </div>
                            </div>
                        @endif

                        @foreach($fichas as $clientId => $ficha)
                            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12px;" wire:key="ficha-{{ $clientId }}">
                                <div class="mb-1">
                                    <i class="ti ti-alert-triangle"></i>
                                    <b>Ficha incompleta de {{ $ficha['nombre'] }}:</b> el contrato requiere estos datos.
                                    Complétalos aquí (se guardarán en su ficha al avanzar).
                                </div>
                                <div class="row g-2">
                                    @if(in_array('sexo', $ficha['faltan']))
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Sexo</label>
                                            <select class="form-select form-select-sm @error("fichas.{$clientId}.sexo") is-invalid @enderror"
                                                    wire:model="fichas.{{ $clientId }}.sexo">
                                                <option value="">Seleccione</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Femenino</option>
                                            </select>
                                        </div>
                                    @endif
                                    @if(in_array('estado_civil', $ficha['faltan']))
                                        <div class="col-md-3">
                                            <label class="form-label mb-0 small fw-semibold">Estado civil</label>
                                            <select class="form-select form-select-sm @error("fichas.{$clientId}.estado_civil") is-invalid @enderror"
                                                    wire:model="fichas.{{ $clientId }}.estado_civil">
                                                <option value="">Seleccione</option>
                                                @foreach($estadosCiviles as $ec)
                                                    <option value="{{ $ec }}">{{ $ec }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    @if(in_array('ocupacion', $ficha['faltan']))
                                        <div class="col-md-3">
                                            <label class="form-label mb-0 small fw-semibold">Ocupación</label>
                                            <input type="text" autocomplete="off"
                                                   class="form-control form-control-sm @error("fichas.{$clientId}.ocupacion") is-invalid @enderror"
                                                   wire:model="fichas.{{ $clientId }}.ocupacion" placeholder="Ej. Comerciante">
                                        </div>
                                    @endif
                                    @if(in_array('email', $ficha['faltan']))
                                        <div class="col-md-4">
                                            <label class="form-label mb-0 small fw-semibold">Correo electrónico</label>
                                            <input type="email" autocomplete="off"
                                                   class="form-control form-control-sm @error("fichas.{$clientId}.email") is-invalid @enderror"
                                                   wire:model="fichas.{{ $clientId }}.email">
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label mb-0 small fw-semibold">Cláusulas adicionales (opcional)</label>
                                <textarea rows="3" class="form-control form-control-sm @error('clausulas_adicionales') is-invalid @enderror"
                                          wire:model="clausulas_adicionales"
                                          placeholder="Texto que se añadirá al final del contrato como cláusulas adicionales"></textarea>
                            </div>
                        </div>
                    @endif

                    {{-- ════════════ PASO 2: Voucher (Anexo 2) ════════════ --}}
                    @if($paso === 2)
                        <h6 class="fw-bold mb-2"><i class="ti ti-receipt"></i> Voucher del desembolso (Anexo 2)</h6>

                        <div class="alert alert-info py-2 px-3 mb-3" style="font-size:12px;">
                            <i class="ti ti-info-circle"></i>
                            El monto del voucher debe igualar el importe del crédito:
                            <b>S/ {{ $importeCredito !== null ? number_format($importeCredito, 2) : '—' }}</b>.
                            La constancia reproduce el desembolso exacto.
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Banco <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('banco') is-invalid @enderror" wire:model.live="banco">
                                    <option value="">Seleccione</option>
                                    @foreach($bancos as $clave => $label)
                                        <option value="{{ $clave }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Modalidad <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('modalidad') is-invalid @enderror" wire:model.live="modalidad"
                                        @if($banco === '') disabled @endif>
                                    <option value="">Seleccione</option>
                                    @foreach($modalidadesBanco as $m)
                                        <option value="{{ $m }}">{{ $modalidades[$m] ?? $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if($camposDef !== [])
                            <div class="border rounded p-2 mb-3">
                                <div class="small fw-bold mb-1">
                                    <i class="ti ti-forms"></i> Datos de la operación — {{ \App\Support\Legal\BancosVoucher::titulo($banco, $modalidad) }}
                                </div>
                                <div class="row g-2">
                                    @foreach($camposDef as $clave => [$label, $requerido])
                                        <div class="col-md-4" wire:key="vcampo-{{ $banco }}-{{ $modalidad }}-{{ $clave }}">
                                            <label class="form-label mb-0 small fw-semibold">
                                                {{ $label }} @if($requerido)<span class="text-danger">*</span>@endif
                                            </label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="voucherCampos.{{ $clave }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label mb-0 small fw-semibold">Imagen del voucher</label>
                                <input type="file" accept="image/*"
                                       class="form-control form-control-sm @error('imagen') is-invalid @enderror"
                                       wire:model="imagen">
                                <div class="form-text" style="font-size:11px;">
                                    JPG/PNG, máx. 4 MB. El Anexo 2 inserta su tenor gráfico. Se sube al previsualizar o emitir.
                                </div>
                                <div wire:loading wire:target="imagen" class="text-muted small">Cargando imagen…</div>
                            </div>
                            <div class="col-md-3">
                                @if($imagenPath)
                                    <span class="badge bg-success mt-4"><i class="ti ti-check"></i> Imagen guardada</span>
                                @elseif($imagen && ! $errors->has('imagen'))
                                    <img src="{{ $imagen->temporaryUrl() }}" alt="Voucher"
                                         class="border rounded mt-1" style="max-height:120px; max-width:100%;">
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- ════════════ PASO 3: Validación y emisión ════════════ --}}
                    @if($paso === 3)
                        <h6 class="fw-bold mb-2"><i class="ti ti-file-check"></i> Validación y emisión</h6>

                        @if($erroresValidacion !== [])
                            <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:12px;">
                                <div class="mb-1"><i class="ti ti-alert-octagon"></i> <b>El contrato no puede generarse:</b></div>
                                <ul class="mb-0 ps-3">
                                    @foreach($erroresValidacion as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-primary" wire:click="previsualizar"
                                    wire:loading.attr="disabled" wire:target="previsualizar,emitir">
                                <i class="ti ti-eye"></i>
                                <span wire:loading.remove wire:target="previsualizar">Validar y previsualizar</span>
                                <span wire:loading wire:target="previsualizar">Validando…</span>
                            </button>

                            @if($htmlPreview)
                                <button type="button" class="btn btn-sm btn-dark" wire:click="emitir"
                                        wire:loading.attr="disabled" wire:target="previsualizar,emitir">
                                    <i class="ti ti-file-certificate"></i>
                                    <span wire:loading.remove wire:target="emitir">Emitir contrato</span>
                                    <span wire:loading wire:target="emitir">Emitiendo…</span>
                                </button>
                            @endif
                        </div>

                        @if($htmlPreview)
                            <div class="alert alert-success py-2 px-3 mb-2" style="font-size:12px;">
                                <i class="ti ti-check"></i>
                                Validación superada. Revisa la previsualización: el contrato se emitirá <b>tal cual se muestra</b>
                                (el PDF definitivo lleva el número correlativo en lugar de "BORRADOR").
                            </div>
                            <iframe srcdoc="{{ $htmlPreview }}"
                                    style="width:100%; height:70vh; border:1px solid #ccc; background:#fff;"></iframe>
                        @elseif($erroresValidacion === [])
                            <p class="text-muted small mb-0">
                                Pulsa <b>Validar y previsualizar</b>: se contrastan los datos con el cronograma real del crédito
                                y, si todo cuadra, verás el contrato exactamente como se emitirá.
                            </p>
                        @endif
                    @endif

                    {{-- ─── Navegación ─── --}}
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            @if($paso > 1)
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="anterior">
                                    <i class="ti ti-chevron-left"></i> Anterior
                                </button>
                            @else
                                <a href="{{ route('legal.garantias.show', $garantia->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-x"></i> Cancelar
                                </a>
                            @endif
                        </div>
                        <div>
                            @if($paso < 3)
                                <button type="button" class="btn btn-sm btn-primary" wire:click="siguiente"
                                        wire:loading.attr="disabled" wire:target="siguiente">
                                    Siguiente <i class="ti ti-chevron-right"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
