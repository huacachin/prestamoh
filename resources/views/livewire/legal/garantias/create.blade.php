<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">NUEVA GARANTÍA MOBILIARIA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-gavel f-s-16"></i>
                    <a href="{{ route('legal.garantias.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Garantías</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Nueva</span></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">

                {{-- ─── Indicador de pasos ─── --}}
                @php
                    $titulosPasos = [1 => 'Crédito y deudores', 2 => 'Vehículos', 3 => 'Parámetros', 4 => 'Resumen'];
                @endphp
                <div class="card-header py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @foreach($titulosPasos as $n => $titulo)
                            <span class="d-flex align-items-center gap-1 {{ $n < $paso ? 'cursor-pointer' : '' }}"
                                  @if($n < $paso) wire:click="irAPaso({{ $n }})" style="cursor:pointer;" @endif>
                                <span class="badge rounded-pill {{ $n === $paso ? 'bg-primary' : ($n < $paso ? 'bg-success' : 'bg-secondary') }}">
                                    @if($n < $paso)<i class="ti ti-check"></i>@else{{ $n }}@endif
                                </span>
                                <span class="small {{ $n === $paso ? 'fw-bold' : 'text-muted' }}">{{ $titulo }}</span>
                            </span>
                            @if($n < 4)
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

                    {{-- ════════════ PASO 1: Crédito y deudores ════════════ --}}
                    @if($paso === 1)
                        <h6 class="fw-bold mb-2"><i class="ti ti-credit-card"></i> Crédito a garantizar</h6>

                        @if(! $creditId)
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label mb-0 small fw-semibold">Buscar crédito activo</label>
                                    <input type="text" autocomplete="off" class="form-control form-control-sm"
                                           wire:model.live.debounce.400ms="buscarCredito"
                                           placeholder="N° de crédito, nombre o documento del cliente">
                                </div>
                            </div>

                            @if(trim($buscarCredito) !== '')
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-striped table-hover mb-0" style="font-size:11px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th class="text-center" width="70">N°</th>
                                                <th>Cliente</th>
                                                <th class="text-center" width="90">Documento</th>
                                                <th class="text-end" width="90">Importe S/</th>
                                                <th class="text-center" width="60">Cuotas</th>
                                                <th class="text-center" width="80">Tipo</th>
                                                <th class="text-center" width="90">Opción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($creditosEncontrados as $c)
                                            <tr wire:key="cred-{{ $c->id }}">
                                                <td class="text-center fw-bold">{{ $c->id }}</td>
                                                <td>{{ $c->client?->fullName() }}</td>
                                                <td class="text-center">{{ $c->client?->documento }}</td>
                                                <td class="text-end">{{ number_format($c->importe, 2) }}</td>
                                                <td class="text-center">{{ $c->cuotas }}</td>
                                                <td class="text-center">{{ $c->tipoPlanillaLabel() }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-success" style="padding:2px 8px; font-size:10px;"
                                                            wire:click="seleccionarCredito({{ $c->id }})">
                                                        Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="py-3 text-muted text-center">No se encontraron créditos activos</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        @if($credit)
                            <div class="border rounded p-2 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="row g-2 flex-grow-1" style="font-size:12px;">
                                        <div class="col-md-2"><b>Crédito N°:</b> {{ $credit->id }}</div>
                                        <div class="col-md-4"><b>Cliente:</b> {{ $credit->client?->fullName() }} ({{ $credit->client?->documento }})</div>
                                        <div class="col-md-2"><b>Importe:</b> S/ {{ number_format($credit->importe, 2) }}</div>
                                        <div class="col-md-2"><b>Cuotas:</b> {{ $credit->cuotas }}</div>
                                        <div class="col-md-2"><b>Planilla:</b> {{ $credit->tipoPlanillaLabel() }}</div>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-danger ms-2" style="padding:2px 8px; font-size:10px;"
                                            wire:click="quitarCredito">
                                        <i class="ti ti-x"></i> Quitar
                                    </button>
                                </div>
                            </div>

                            @if($garantiasPrevias > 0)
                                <div class="alert alert-info py-2 px-3 mb-3" style="font-size:12px;">
                                    <i class="ti ti-info-circle"></i>
                                    Este crédito ya tiene {{ $garantiasPrevias }} garantía(s) registrada(s). Se creará una adicional.
                                </div>
                            @endif

                            @if($faltaEstadoCivil || $faltaOcupacion)
                                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12px;">
                                    <div class="mb-1">
                                        <i class="ti ti-alert-triangle"></i>
                                        <b>Ficha del deudor incompleta:</b> el contrato SIGM requiere
                                        {{ $faltaEstadoCivil && $faltaOcupacion ? 'el estado civil y la ocupación' : ($faltaEstadoCivil ? 'el estado civil' : 'la ocupación') }}
                                        del cliente. Complétalo aquí (se guardará en su ficha al finalizar).
                                    </div>
                                    <div class="row g-2">
                                        @if($faltaEstadoCivil)
                                            <div class="col-md-3">
                                                <label class="form-label mb-0 small fw-semibold">Estado civil</label>
                                                <select class="form-select form-select-sm @error('deudorEstadoCivil') is-invalid @enderror"
                                                        wire:model="deudorEstadoCivil">
                                                    <option value="">Seleccione</option>
                                                    @foreach($estadosCiviles as $ec)
                                                        <option value="{{ $ec }}">{{ $ec }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        @if($faltaOcupacion)
                                            <div class="col-md-4">
                                                <label class="form-label mb-0 small fw-semibold">Ocupación</label>
                                                <input type="text" autocomplete="off"
                                                       class="form-control form-control-sm @error('deudorOcupacion') is-invalid @enderror"
                                                       wire:model="deudorOcupacion" placeholder="Ej. Comerciante">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif

                        <h6 class="fw-bold mb-2 mt-3"><i class="ti ti-user"></i> Tipo de persona del deudor</h6>
                        <div class="d-flex gap-4 mb-3">
                            @foreach(\App\Models\Garantia::TIPO_PERSONAS as $valor => $etiqueta)
                                <div class="form-check">
                                    <input class="form-check-input @error('tipo_persona') is-invalid @enderror" type="radio"
                                           wire:model="tipo_persona" value="{{ $valor }}" id="tp-{{ $valor }}">
                                    <label class="form-check-label small" for="tp-{{ $valor }}">{{ $etiqueta }}</label>
                                </div>
                            @endforeach
                        </div>

                        <h6 class="fw-bold mb-2"><i class="ti ti-users"></i> Codeudor <span class="text-muted small fw-normal">(opcional)</span></h6>
                        @if(! $codeudorId)
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <input type="text" autocomplete="off" class="form-control form-control-sm"
                                           wire:model.live.debounce.400ms="buscarCodeudor"
                                           placeholder="Nombre o documento del codeudor">
                                </div>
                            </div>

                            @if(trim($buscarCodeudor) !== '')
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-striped table-hover mb-0" style="font-size:11px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th>Cliente</th>
                                                <th class="text-center" width="100">Documento</th>
                                                <th class="text-center" width="90">Opción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($codeudoresEncontrados as $cl)
                                            <tr wire:key="codeudor-{{ $cl->id }}">
                                                <td>{{ $cl->fullName() }}</td>
                                                <td class="text-center">{{ $cl->documento }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-success" style="padding:2px 8px; font-size:10px;"
                                                            wire:click="seleccionarCodeudor({{ $cl->id }})">
                                                        Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="py-3 text-muted text-center">No se encontraron clientes</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <div class="border rounded p-2 mb-3 bg-light d-flex justify-content-between align-items-center" style="font-size:12px;">
                                <span><b>Codeudor:</b> {{ $codeudor?->fullName() }} ({{ $codeudor?->documento }})</span>
                                <button type="button" class="btn btn-xs btn-outline-danger" style="padding:2px 8px; font-size:10px;"
                                        wire:click="quitarCodeudor">
                                    <i class="ti ti-x"></i> Quitar
                                </button>
                            </div>
                        @endif
                    @endif

                    {{-- ════════════ PASO 2: Vehículos ════════════ --}}
                    @if($paso === 2)
                        <h6 class="fw-bold mb-2"><i class="ti ti-car"></i> Vehículos en garantía <span class="text-muted small fw-normal">(mínimo 1, máximo 2)</span></h6>

                        @foreach($vehiculos as $i => $item)
                            <div class="border rounded p-2 mb-3" wire:key="veh-{{ $i }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small">
                                        <span class="badge bg-dark">Vehículo {{ $i + 1 }}</span>
                                        @if($item['vehiculo_id'])
                                            {{ $item['descripcion'] }} <span class="badge bg-info">Existente</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Nuevo</span>
                                        @endif
                                    </span>
                                    <button type="button" class="btn btn-xs btn-outline-danger" style="padding:2px 8px; font-size:10px;"
                                            wire:click="quitarVehiculo({{ $i }})">
                                        <i class="ti ti-trash"></i> Quitar
                                    </button>
                                </div>

                                @if(! $item['vehiculo_id'])
                                    {{-- Vehículo nuevo: ficha técnica inline (client_id = deudor) --}}
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Placa *</label>
                                            <input type="text" autocomplete="off" maxlength="10"
                                                   class="form-control form-control-sm text-uppercase @error('vehiculos.'.$i.'.placa') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.placa">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Marca *</label>
                                            <input type="text" autocomplete="off"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.marca') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.marca">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Modelo *</label>
                                            <input type="text" autocomplete="off"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.modelo') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.modelo">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">N° Motor</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.nro_motor">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">N° Serie</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.nro_serie">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Categoría</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.categoria" placeholder="Ej. M1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Año</label>
                                            <input type="number" autocomplete="off"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.anio') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.anio" min="1950" max="{{ now()->year + 1 }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Carrocería</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.carroceria">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Color</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.color">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Combustible</label>
                                            <input type="text" autocomplete="off" class="form-control form-control-sm"
                                                   wire:model="vehiculos.{{ $i }}.combustible" placeholder="Ej. Gasolina">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Valor S/</label>
                                            <input type="number" step="0.01" min="0" autocomplete="off"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.valor') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.valor">
                                        </div>
                                    </div>
                                @endif

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           wire:model.live="vehiculos.{{ $i }}.es_bien_futuro" id="bf-{{ $i }}">
                                    <label class="form-check-label small" for="bf-{{ $i }}">
                                        Es <b>bien futuro</b> (acta de transferencia notarial, aún sin inscripción registral)
                                    </label>
                                </div>

                                @if($item['es_bien_futuro'])
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-3">
                                            <label class="form-label mb-0 small fw-semibold">Acta notarial *</label>
                                            <input type="text" autocomplete="off" maxlength="60"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.acta_notarial') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.acta_notarial">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0 small fw-semibold">Kardex *</label>
                                            <input type="text" autocomplete="off" maxlength="20"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.kardex') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.kardex" placeholder="Ej. 0373-2026">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label mb-0 small fw-semibold">Notario *</label>
                                            <input type="text" autocomplete="off" maxlength="120"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.notario') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.notario">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label mb-0 small fw-semibold">Fecha del acta *</label>
                                            <input type="date" autocomplete="off"
                                                   class="form-control form-control-sm @error('vehiculos.'.$i.'.fecha_acta') is-invalid @enderror"
                                                   wire:model="vehiculos.{{ $i }}.fecha_acta">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if(count($vehiculos) < 2)
                            <div class="border rounded p-2 mb-2 bg-light">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label mb-0 small fw-semibold">Buscar vehículo existente por placa</label>
                                        <input type="text" autocomplete="off" class="form-control form-control-sm text-uppercase"
                                               wire:model.live.debounce.400ms="buscarVehiculo"
                                               placeholder="Ej. ABC-123">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-sm btn-danger" wire:click="agregarVehiculoNuevo">
                                            <i class="ti ti-plus f-s-12"></i> Registrar vehículo nuevo
                                        </button>
                                    </div>
                                </div>

                                @if(trim($buscarVehiculo) !== '')
                                    <div class="table-responsive mt-2">
                                        <table class="table table-bordered table-striped table-hover mb-0" style="font-size:11px;">
                                            <thead class="bg-primary">
                                                <tr>
                                                    <th class="text-center" width="90">Placa</th>
                                                    <th>Marca / Modelo</th>
                                                    <th>Propietario</th>
                                                    <th class="text-center" width="60">Año</th>
                                                    <th class="text-center" width="90">Opción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($vehiculosEncontrados as $v)
                                                <tr wire:key="veh-busq-{{ $v->id }}">
                                                    <td class="text-center fw-bold">{{ $v->placa }}</td>
                                                    <td>{{ trim($v->marca.' '.$v->modelo) }}</td>
                                                    <td>{{ $v->client?->fullName() ?? \App\Models\Vehiculo::PROPIETARIO_TIPOS[$v->propietario_tipo] ?? '—' }}</td>
                                                    <td class="text-center">{{ $v->anio ?? '—' }}</td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-success" style="padding:2px 8px; font-size:10px;"
                                                                wire:click="agregarVehiculoExistente({{ $v->id }})">
                                                            Agregar
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="py-3 text-muted text-center">No se encontraron vehículos activos con esa placa</td></tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- ════════════ PASO 3: Parámetros ════════════ --}}
                    @if($paso === 3)
                        <h6 class="fw-bold mb-3"><i class="ti ti-adjustments"></i> Parámetros de la garantía</h6>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" wire:model="gps" id="chk-gps">
                                    <label class="form-check-label small" for="chk-gps"><b>GPS</b> instalado en el vehículo</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" wire:model="custodia" id="chk-custodia">
                                    <label class="form-check-label small" for="chk-custodia"><b>Custodia</b> del vehículo</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-0 small fw-semibold">Monto de gravamen S/ *</label>
                                <input type="number" step="0.01" min="0" autocomplete="off"
                                       class="form-control form-control-sm @error('monto_gravamen') is-invalid @enderror"
                                       wire:model="monto_gravamen" style="background-color:yellow;">
                                <div class="form-text" style="font-size:11px;">
                                    Monto máximo de la garantía; normalmente cuota × n° de cuotas.
                                    @if($montoSugerido !== null)
                                        <br>
                                        Sugerido según el cronograma del crédito: <b>S/ {{ number_format($montoSugerido, 2) }}</b>
                                        <button type="button" class="btn btn-xs btn-outline-primary ms-1" style="padding:1px 6px; font-size:10px;"
                                                wire:click="usarSugerencia">
                                            Usar sugerencia
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label mb-0 small fw-semibold">Observaciones</label>
                                <textarea class="form-control form-control-sm @error('observaciones') is-invalid @enderror"
                                          rows="3" wire:model="observaciones"
                                          placeholder="Notas internas de la garantía (opcional)"></textarea>
                            </div>
                        </div>
                    @endif

                    {{-- ════════════ PASO 4: Resumen y confirmación ════════════ --}}
                    @if($paso === 4)
                        <h6 class="fw-bold mb-3"><i class="ti ti-clipboard-check"></i> Resumen de la garantía</h6>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-2 h-100" style="font-size:12px;">
                                    <div class="fw-bold border-bottom pb-1 mb-2">Crédito y deudores</div>
                                    <div><b>Crédito N°:</b> {{ $credit?->id }} — S/ {{ number_format($credit?->importe ?? 0, 2) }}
                                        ({{ $credit?->cuotas }} cuotas, {{ $credit?->tipoPlanillaLabel() }})</div>
                                    <div><b>Deudor:</b> {{ $credit?->client?->fullName() }} ({{ $credit?->client?->documento }})</div>
                                    <div><b>Tipo de persona:</b> {{ \App\Models\Garantia::TIPO_PERSONAS[$tipo_persona] ?? $tipo_persona }}</div>
                                    <div><b>Codeudor:</b> {{ $codeudor ? $codeudor->fullName().' ('.$codeudor->documento.')' : 'Sin codeudor' }}</div>
                                    @if($faltaEstadoCivil && filled($deudorEstadoCivil))
                                        <div><b>Estado civil (se guardará en la ficha):</b> {{ $deudorEstadoCivil }}</div>
                                    @endif
                                    @if($faltaOcupacion && filled($deudorOcupacion))
                                        <div><b>Ocupación (se guardará en la ficha):</b> {{ $deudorOcupacion }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded p-2 h-100" style="font-size:12px;">
                                    <div class="fw-bold border-bottom pb-1 mb-2">Parámetros</div>
                                    <div><b>GPS:</b> {{ $gps ? 'Sí' : 'No' }} &nbsp;|&nbsp; <b>Custodia:</b> {{ $custodia ? 'Sí' : 'No' }}</div>
                                    <div><b>Monto de gravamen:</b> S/ {{ number_format((float) $monto_gravamen, 2) }}</div>
                                    <div><b>Estado inicial:</b> {{ \App\Models\Garantia::ESTADOS['en_constitucion'] }}</div>
                                    @if(trim($observaciones) !== '')
                                        <div><b>Observaciones:</b> {{ $observaciones }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0" style="font-size:11px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th class="text-center" width="40">N°</th>
                                                <th class="text-center" width="100">Placa</th>
                                                <th>Vehículo</th>
                                                <th class="text-center" width="90">Bien futuro</th>
                                                <th>Acta / Kardex / Notario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($vehiculos as $i => $item)
                                            <tr wire:key="resumen-veh-{{ $i }}">
                                                <td class="text-center">{{ $i + 1 }}</td>
                                                <td class="text-center fw-bold">{{ $item['placa'] }}</td>
                                                <td>
                                                    @if($item['vehiculo_id'])
                                                        {{ $item['descripcion'] }} <span class="badge bg-info">Existente</span>
                                                    @else
                                                        {{ trim($item['marca'].' '.$item['modelo']) }}
                                                        {{ $item['anio'] ? '('.$item['anio'].')' : '' }}
                                                        <span class="badge bg-warning text-dark">Se creará</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($item['es_bien_futuro'])
                                                        <span class="badge bg-danger">Sí</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($item['es_bien_futuro'])
                                                        {{ $item['acta_notarial'] }} / {{ $item['kardex'] }} / {{ $item['notario'] }}
                                                        ({{ $item['fecha_acta'] ? \Carbon\Carbon::parse($item['fecha_acta'])->format('d/m/Y') : '—' }})
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size:12px;">
                            <i class="ti ti-info-circle"></i>
                            La garantía se creará <b>en constitución</b>. Pasará a <b>vigente</b> al registrar el aviso SIGM de constitución.
                        </div>
                    @endif

                    {{-- ─── Navegación del wizard ─── --}}
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            @if($paso > 1)
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="anterior">
                                    <i class="ti ti-chevron-left"></i> Anterior
                                </button>
                            @else
                                <a href="{{ route('legal.garantias.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-x"></i> Cancelar
                                </a>
                            @endif
                        </div>
                        <div>
                            @if($paso < 4)
                                <button type="button" class="btn btn-sm btn-primary" wire:click="siguiente"
                                        wire:loading.attr="disabled" wire:target="siguiente">
                                    Siguiente <i class="ti ti-chevron-right"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-dark" wire:click="guardar"
                                        wire:loading.attr="disabled" wire:target="guardar">
                                    <i class="ti ti-check"></i>
                                    <span wire:loading.remove wire:target="guardar">Crear garantía</span>
                                    <span wire:loading wire:target="guardar">Guardando…</span>
                                </button>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
