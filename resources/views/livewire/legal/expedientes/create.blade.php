<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">NUEVO EXPEDIENTE JUDICIAL</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="{{ route('legal.expedientes.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Expedientes</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Nuevo</span></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <form wire:submit.prevent="guardar">

                        {{-- ════════════ Cliente ════════════ --}}
                        <h6 class="fw-bold mb-2"><i class="ti ti-user"></i> Cliente demandado</h6>

                        @if(! $client_id)
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label mb-0 small fw-semibold">Buscar cliente activo</label>
                                    <input type="text" autocomplete="off"
                                           class="form-control form-control-sm @error('client_id') is-invalid @enderror"
                                           wire:model.live.debounce.400ms="buscarCliente"
                                           placeholder="Nombre, documento o exp. interno (mínimo 2 caracteres)">
                                </div>
                            </div>

                            @if(mb_strlen(trim($buscarCliente)) >= 2)
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-striped table-hover mb-0" style="font-size:11px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th>Cliente</th>
                                                <th class="text-center" width="100">Documento</th>
                                                <th class="text-center" width="90">Exp. interno</th>
                                                <th class="text-center" width="90">Opción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($clientesEncontrados as $cl)
                                            <tr wire:key="cliente-{{ $cl->id }}">
                                                <td>{{ $cl->fullName() }}</td>
                                                <td class="text-center">{{ $cl->documento }}</td>
                                                <td class="text-center">{{ $cl->expediente ?: '—' }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-success" style="padding:2px 8px; font-size:10px;"
                                                            wire:click="seleccionarCliente({{ $cl->id }})">
                                                        Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="py-3 text-muted text-center">No se encontraron clientes activos</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        @if($cliente)
                            <div class="border rounded p-2 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div style="font-size:12px;">
                                        <span class="badge bg-success me-1"><i class="ti ti-user-check"></i> Cliente</span>
                                        <b>{{ $cliente->fullName() }}</b>
                                        ({{ $cliente->documento }})
                                        @if($cliente->expediente)
                                            <span class="badge bg-light text-dark border ms-1">Exp. interno: {{ $cliente->expediente }}</span>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-danger ms-2" style="padding:2px 8px; font-size:10px;"
                                            wire:click="quitarCliente">
                                        <i class="ti ti-x"></i> Quitar
                                    </button>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small fw-semibold">Crédito vinculado <span class="text-muted fw-normal">(opcional)</span></label>
                                    <select class="form-select form-select-sm @error('credit_id') is-invalid @enderror"
                                            wire:model.live="credit_id">
                                        <option value="">Sin crédito vinculado</option>
                                        @foreach($creditos as $c)
                                            <option value="{{ $c->id }}">
                                                N° {{ $c->id }} — S/ {{ number_format($c->importe, 2) }} — {{ $c->cuotas }} cuotas
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($creditos->isEmpty())
                                        <div class="form-text" style="font-size:10px;">El cliente no tiene créditos activos.</div>
                                    @endif
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label mb-0 small fw-semibold">Garantía vinculada <span class="text-muted fw-normal">(opcional)</span></label>
                                    <select class="form-select form-select-sm @error('garantia_id') is-invalid @enderror"
                                            wire:model.live="garantia_id">
                                        <option value="">Sin garantía vinculada</option>
                                        @foreach($garantias as $g)
                                            <option value="{{ $g->id }}">
                                                Garantía #{{ $g->id }}{{ $g->vehiculos->pluck('placa')->filter()->isNotEmpty() ? ' — '.$g->vehiculos->pluck('placa')->filter()->implode(', ') : '' }} — {{ \App\Models\Garantia::ESTADOS[$g->estado] ?? $g->estado }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($garantias->isEmpty())
                                        <div class="form-text" style="font-size:10px;">El cliente no tiene garantías registradas.</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- ════════════ Datos del expediente (cuaderno principal) ════════════ --}}
                        <h6 class="fw-bold mb-2 mt-3"><i class="ti ti-gavel"></i> Datos del expediente (cuaderno principal)</h6>

                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0 small fw-semibold">N° de expediente (PJ) <span class="text-danger">*</span></label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm font-monospace @error('nro_expediente') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="nro_expediente"
                                       placeholder="04388-2024-0-3209-JP-CI-01">
                                <div class="form-text" style="font-size:10px;">Formato: 5 dígitos - año - cuaderno - 4 dígitos - 2 letras - 2 letras - 2 dígitos.</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Exp. interno</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm @error('exp_interno') is-invalid @enderror"
                                       wire:model="exp_interno" placeholder="Ej. 0245">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Vía de recupero <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('via') is-invalid @enderror" wire:model="via">
                                    <option value="">Seleccione</option>
                                    @foreach(\App\Models\ExpedienteJudicial::VIAS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm @error('fecha_inicio') is-invalid @enderror"
                                       wire:model="fecha_inicio">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-5">
                                <label class="form-label mb-0 small fw-semibold">Materia</label>
                                <input type="text" autocomplete="off" list="materias-frecuentes"
                                       class="form-control form-control-sm @error('materia') is-invalid @enderror"
                                       wire:model="materia" placeholder="Ej. OBLIGACION DE DAR SUMA DE DINERO">
                                <datalist id="materias-frecuentes">
                                    @foreach(\App\Livewire\Legal\Expedientes\Create::MATERIAS as $m)
                                        <option value="{{ $m }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Proceso</label>
                                <input type="text" autocomplete="off" list="procesos-frecuentes"
                                       class="form-control form-control-sm @error('proceso') is-invalid @enderror"
                                       wire:model="proceso" placeholder="Ej. Único de ejecución">
                                <datalist id="procesos-frecuentes">
                                    @foreach(\App\Livewire\Legal\Expedientes\Create::PROCESOS as $p)
                                        <option value="{{ $p }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Monto pretensión S/</label>
                                <input type="number" step="0.01" min="0" autocomplete="off"
                                       class="form-control form-control-sm @error('monto_pretension') is-invalid @enderror"
                                       wire:model="monto_pretension" placeholder="0.00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Asesor responsable <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('asesor_responsable_id') is-invalid @enderror"
                                        wire:model="asesor_responsable_id">
                                    <option value="">Seleccione</option>
                                    @foreach($usuarios as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0 small fw-semibold">Juzgado</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm @error('juzgado') is-invalid @enderror"
                                       wire:model="juzgado" placeholder="Ej. 1° Juzgado de Paz Letrado de SJL">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Distrito judicial</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm @error('distrito_judicial') is-invalid @enderror"
                                       wire:model="distrito_judicial" placeholder="Ej. Lima Este">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Juez</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm @error('juez') is-invalid @enderror"
                                       wire:model="juez">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small fw-semibold">Secretario</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm @error('secretario') is-invalid @enderror"
                                       wire:model="secretario">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-12">
                                <label class="form-label mb-0 small fw-semibold">Observaciones</label>
                                <textarea rows="2" class="form-control form-control-sm @error('observaciones') is-invalid @enderror"
                                          wire:model="observaciones"
                                          placeholder="Notas internas del expediente (opcional)"></textarea>
                            </div>
                        </div>

                        {{-- ════════════ Cuaderno cautelar (opcional) ════════════ --}}
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="crearCautelar" wire:model.live="crearCautelar">
                            <label class="form-check-label small fw-semibold" for="crearCautelar">
                                <i class="ti ti-shield-lock"></i> Crear también el cuaderno cautelar
                            </label>
                        </div>

                        @if($crearCautelar)
                            <div class="border rounded p-2 mb-3 bg-light">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-0 small fw-semibold">N° del cuaderno cautelar</label>
                                        <input type="text" readonly
                                               class="form-control form-control-sm font-monospace bg-white"
                                               value="{{ $nroCautelarPreview !== '' ? $nroCautelarPreview : '' }}"
                                               placeholder="Se deriva del N° principal">
                                        <div class="form-text" style="font-size:10px;">
                                            Se genera automáticamente cambiando el dígito de cuaderno del N° principal (0 → 1).
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small fw-semibold">Forma de la medida <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('forma_medida') is-invalid @enderror"
                                                wire:model="forma_medida">
                                            <option value="">Seleccione</option>
                                            @foreach(\App\Models\ExpedienteJudicial::FORMAS_MEDIDA as $clave => $etiqueta)
                                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small fw-semibold">Bien afectado <span class="text-danger">*</span></label>
                                        <input type="text" autocomplete="off"
                                               class="form-control form-control-sm @error('bien_descripcion') is-invalid @enderror"
                                               wire:model="bien_descripcion" placeholder="Ej. VEHÍCULO placa ABC-123">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0 small fw-semibold">Fecha de inicio <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm @error('fecha_inicio_cautelar') is-invalid @enderror"
                                               wire:model="fecha_inicio_cautelar">
                                    </div>
                                </div>
                                <div class="form-text mt-1" style="font-size:10px;">
                                    El cuaderno cautelar nace en estado <b>«Medida solicitada»</b>, vinculado al principal y con el mismo cliente, crédito, garantía y asesor.
                                </div>
                            </div>
                        @endif

                        {{-- ════════════ Acciones ════════════ --}}
                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ route('legal.expedientes.index') }}" class="btn btn-sm btn-secondary">
                                <i class="ti ti-arrow-left f-s-12"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-sm btn-danger"
                                    wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">
                                    <i class="ti ti-device-floppy f-s-12"></i> Registrar expediente
                                </span>
                                <span wire:loading wire:target="guardar">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando…
                                </span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
