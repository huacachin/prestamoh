<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">VEHÍCULOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="{{ route('legal.vehiculos') }}" class="f-s-14">Vehículos</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="Placa, marca, modelo o cliente (nombre/documento)">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroEstado">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\Vehiculo::ESTADOS as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Propietario</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroPropietario">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\Vehiculo::PROPIETARIO_TIPOS as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="filtroVencidos"
                                           wire:model.live="filtroVencidos">
                                    <label class="form-check-label small" for="filtroVencidos">
                                        <b>Docs. vencidos/por vencer</b>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('legal.garantias')
                                <button type="button" class="btn btn-sm btn-danger" wire:click="nuevo">
                                    <i class="ti ti-plus f-s-12"></i> Nuevo Vehículo
                                </button>
                            @endcan
                        </div>
                    </form>

                    {{-- Tabla --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="40">N°</th>
                                    <th class="text-center" width="90">Placa</th>
                                    <th>Marca / Modelo</th>
                                    <th class="text-center" width="50">Año</th>
                                    <th>Propietario</th>
                                    <th class="text-end" width="100">Valor S/</th>
                                    <th class="text-center" width="80">Garantías</th>
                                    <th class="text-center" width="150">Vencimientos</th>
                                    <th class="text-center" width="90">Estado</th>
                                    <th class="text-center" width="80">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($vehiculos as $vehiculo)
                                @php
                                    $badgeEstado = [
                                        'activo' => 'bg-success',
                                        'vendido' => 'bg-secondary',
                                        'adjudicado' => 'bg-warning text-dark',
                                        'baja' => 'bg-danger',
                                    ][$vehiculo->estado] ?? 'bg-secondary';

                                    $propietario = match ($vehiculo->propietario_tipo) {
                                        'cliente' => $vehiculo->client?->fullName() ?: '(sin cliente)',
                                        'tercero' => $vehiculo->propietario_nombre ?: '(sin nombre)',
                                        default => 'Empresa (flota propia)',
                                    };

                                    // Vencimientos documentarios: etiqueta corta por campo
                                    $vencimientosCortos = [
                                        'soat_vence' => 'SOAT',
                                        'revision_tecnica_vence' => 'Rev.Téc.',
                                        'habilitacion_atu_vence' => 'ATU',
                                    ];
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $vehiculos->firstItem() + $loop->index }}</td>
                                    <td class="text-center fw-bold">{{ $vehiculo->placa }}</td>
                                    <td>{{ trim("{$vehiculo->marca} {$vehiculo->modelo}") ?: '—' }}</td>
                                    <td class="text-center">{{ $vehiculo->anio ?? '—' }}</td>
                                    <td>
                                        {{ $propietario }}
                                        <span class="badge bg-light text-dark border" style="font-size:9px;">
                                            {{ \App\Models\Vehiculo::PROPIETARIO_TIPOS[$vehiculo->propietario_tipo] ?? $vehiculo->propietario_tipo }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $vehiculo->valor !== null ? number_format($vehiculo->valor, 2) : '—' }}</td>
                                    <td class="text-center">
                                        @if($vehiculo->garantias_count > 0)
                                            <span class="badge bg-info text-dark">{{ $vehiculo->garantias_count }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php $tieneVencimiento = false; @endphp
                                        @foreach($vencimientosCortos as $campo => $corto)
                                            @php $fecha = $vehiculo->{$campo}; @endphp
                                            @if($fecha)
                                                @php
                                                    $tieneVencimiento = true;
                                                    if ($fecha->lt(today())) {
                                                        // Ya venció
                                                        $claseVenc = 'bg-danger';
                                                        $estiloVenc = '';
                                                    } elseif ($fecha->lte(today()->addDays(30))) {
                                                        // Vence en 30 días o menos
                                                        $claseVenc = '';
                                                        $estiloVenc = 'background:#fd7e14;color:#fff;';
                                                    } else {
                                                        // Aún lejos
                                                        $claseVenc = 'bg-light text-muted border';
                                                        $estiloVenc = '';
                                                    }
                                                @endphp
                                                <span class="badge {{ $claseVenc }}" style="font-size:9px;{{ $estiloVenc }}"
                                                      title="{{ \App\Models\Vehiculo::VENCIMIENTOS[$campo] }}: {{ $fecha->format('d/m/Y') }}">
                                                    {{ $corto }}
                                                </span>
                                            @endif
                                        @endforeach
                                        @unless($tieneVencimiento)
                                            <span class="text-muted">—</span>
                                        @endunless
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEstado }}">
                                            {{ \App\Models\Vehiculo::ESTADOS[$vehiculo->estado] ?? $vehiculo->estado }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @can('legal.garantias')
                                            <button type="button" class="btn btn-xs btn-success"
                                                    style="padding: 2px 8px; font-size: 10px;"
                                                    wire:click="editar({{ $vehiculo->id }})">
                                                Editar
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron vehículos</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="9">TOTAL</td>
                                    <td class="text-center fw-bold">{{ $vehiculos->total() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{ $vehiculos->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal crear/editar vehículo ═══ --}}
    <div class="modal fade" id="vehiculoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:vehiculo-modal-open.window="modal.show()"
         x-on:vehiculo-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-car"></i>
                        {{ $editingId ? 'Editar vehículo — '.$placa : 'Nuevo vehículo' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        {{-- Propietario --}}
                        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>Tipo de propietario</b> <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('propietario_tipo') is-invalid @enderror"
                                            wire:model.live="propietario_tipo">
                                        @foreach(\App\Models\Vehiculo::PROPIETARIO_TIPOS as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('propietario_tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                @if($propietario_tipo === 'tercero')
                                    <div class="col-md-5">
                                        <label class="form-label mb-0 small"><b>Nombre del tercero</b> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm @error('propietario_nombre') is-invalid @enderror"
                                               wire:model="propietario_nombre" placeholder="Nombre completo del propietario">
                                        @error('propietario_nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small"><b>Documento</b></label>
                                        <input type="text" class="form-control form-control-sm @error('propietario_documento') is-invalid @enderror"
                                               wire:model="propietario_documento" maxlength="15" placeholder="DNI / RUC">
                                        @error('propietario_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                @endif

                                @if($propietario_tipo !== 'empresa')
                                    <div class="col-md-8">
                                        <label class="form-label mb-0 small">
                                            <b>Cliente</b>
                                            @if($propietario_tipo === 'cliente') <span class="text-danger">*</span> @endif
                                        </label>
                                        @if($client_id)
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-dark" style="font-size:11px;">
                                                    <i class="ti ti-user"></i> {{ $clienteNombre }}
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-danger"
                                                        style="padding: 1px 6px; font-size: 10px;"
                                                        wire:click="quitarCliente">
                                                    <i class="ti ti-x"></i> Quitar
                                                </button>
                                            </div>
                                        @else
                                            <input type="text" class="form-control form-control-sm @error('client_id') is-invalid @enderror"
                                                   autocomplete="off"
                                                   wire:model.live.debounce.400ms="clienteBusqueda"
                                                   placeholder="Busca por nombre o documento (mín. 2 caracteres)">
                                            @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                            @if($clientesEncontrados->isNotEmpty())
                                                <div class="list-group mt-1" style="max-height: 160px; overflow-y: auto;">
                                                    @foreach($clientesEncontrados as $cliente)
                                                        <button type="button"
                                                                class="list-group-item list-group-item-action py-1 px-2"
                                                                style="font-size: 11px;"
                                                                wire:click="seleccionarCliente({{ $cliente->id }})">
                                                            <b>{{ $cliente->fullName() }}</b>
                                                            <span class="text-muted">— {{ $cliente->documento }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($clienteBusqueda)) >= 2)
                                                <div class="form-text text-danger">Sin coincidencias entre los clientes activos.</div>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Datos del vehículo --}}
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Placa</b> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase @error('placa') is-invalid @enderror"
                                       wire:model="placa" maxlength="10" placeholder="ABC-123">
                                @error('placa') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Marca</b></label>
                                <input type="text" class="form-control form-control-sm @error('marca') is-invalid @enderror"
                                       wire:model="marca" maxlength="50">
                                @error('marca') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Modelo</b></label>
                                <input type="text" class="form-control form-control-sm @error('modelo') is-invalid @enderror"
                                       wire:model="modelo" maxlength="50">
                                @error('modelo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Año</b></label>
                                <input type="number" class="form-control form-control-sm @error('anio') is-invalid @enderror"
                                       wire:model="anio" min="1950" max="{{ now()->year + 1 }}" placeholder="{{ now()->year }}">
                                @error('anio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>N° Motor</b></label>
                                <input type="text" class="form-control form-control-sm @error('nro_motor') is-invalid @enderror"
                                       wire:model="nro_motor" maxlength="30">
                                @error('nro_motor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>N° Serie / Chasis</b></label>
                                <input type="text" class="form-control form-control-sm @error('nro_serie') is-invalid @enderror"
                                       wire:model="nro_serie" maxlength="30">
                                @error('nro_serie') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Categoría</b></label>
                                <input type="text" class="form-control form-control-sm @error('categoria') is-invalid @enderror"
                                       wire:model="categoria" maxlength="30" placeholder="M1, L5...">
                                @error('categoria') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Carrocería</b></label>
                                <input type="text" class="form-control form-control-sm @error('carroceria') is-invalid @enderror"
                                       wire:model="carroceria" maxlength="50" placeholder="Sedán, Mototaxi...">
                                @error('carroceria') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Color</b></label>
                                <input type="text" class="form-control form-control-sm @error('color') is-invalid @enderror"
                                       wire:model="color" maxlength="50">
                                @error('color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Combustible</b></label>
                                <input type="text" class="form-control form-control-sm @error('combustible') is-invalid @enderror"
                                       wire:model="combustible" maxlength="30" placeholder="Gasolina, GLP...">
                                @error('combustible') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Valor S/</b></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('valor') is-invalid @enderror"
                                       wire:model="valor" placeholder="0.00">
                                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Estado</b> <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('estado') is-invalid @enderror"
                                        wire:model="estado">
                                    @foreach(\App\Models\Vehiculo::ESTADOS as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Vencimientos documentarios --}}
                            <div class="col-12 mt-2">
                                <label class="form-label mb-0 small text-muted"><b>Vencimientos documentarios</b></label>
                            </div>
                            @foreach(\App\Models\Vehiculo::VENCIMIENTOS as $campo => $etiqueta)
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>{{ $etiqueta }}</b></label>
                                    <input type="date" class="form-control form-control-sm @error($campo) is-invalid @enderror"
                                           wire:model="{{ $campo }}">
                                    @error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endforeach

                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Observaciones</b></label>
                                <textarea class="form-control form-control-sm @error('observaciones') is-invalid @enderror"
                                          rows="2" wire:model="observaciones"></textarea>
                                @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">{{ $editingId ? 'Guardar cambios' : 'Registrar vehículo' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
