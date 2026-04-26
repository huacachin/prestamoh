<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">CONCEPTOS FIJOS</h4>
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
                    <a href="#" class="f-s-14">Conceptos</a>
                </li>
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
                            <div class="col-md-5">
                                <label class="form-label mb-0 small"><b>BUSCAR X</b></label>
                                <div class="d-flex gap-3 mb-1">
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="1" id="tipoCodigo">
                                        <label class="form-check-label small " for="tipoCodigo">Código</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="2" id="tipoNombre">
                                        <label class="form-check-label small " for="tipoNombre">Nombre</label>
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="compra"
                                       placeholder="Ingrese el texto a buscar">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="estados">
                                    <option value="Activo">Activo</option>
                                    <option value="Cesado">Cesado</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            <a href="#" class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                            @hasanyrole('superusuario|administrador')
                                <a href="{{ route('settings.concepts.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Nuevo Concepto
                                </a>
                            @endhasanyrole
                        </div>
                    </form>

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="40">Estado</th>
                                    <th class="text-center" width="50">N°</th>
                                    <th class="text-center" width="80">Código</th>
                                    <th>Nombre</th>
                                    <th class="text-center" width="100">Tipo</th>
                                    <th class="text-end" width="80">ING. S/</th>
                                    <th class="text-end" width="80">EGR. S/</th>
                                    <th class="text-center" width="100">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $ingo = 0;
                                $egrino = 0;
                            @endphp
                            @forelse($concepts as $concept)
                                @php
                                    $isIngreso = strtolower($concept->type) === 'ingreso';
                                    if ($isIngreso) { $ingo++; $num = $ingo; } else { $egrino++; $num = $egrino; }
                                @endphp
                                <tr onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">
                                        @if($concept->status === 'active')
                                            <i class="ti ti-circle-check f-s-16 text-success"></i>
                                        @else
                                            <i class="ti ti-circle-x f-s-16 text-danger"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span style="color: {{ $isIngreso ? 'black' : 'red' }}; font-weight: bold;">{{ $num }}</span>
                                    </td>
                                    <td class="text-center">
                                        @hasanyrole('superusuario|administrador')
                                            <a href="{{ route('settings.concepts.edit', $concept->id) }}" style="color: black;">
                                                {{ $concept->code }}
                                            </a>
                                        @else
                                            {{ $concept->code }}
                                        @endhasanyrole
                                    </td>
                                    <td>{{ $concept->name }}</td>
                                    <td class="text-center">
                                        @if($isIngreso)
                                            <b style="color: black;">INGRESO</b>
                                        @else
                                            <span style="color: red;">EGRESO</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($concept->factor_ingreso, 2) }}</td>
                                    <td class="text-end">{{ number_format($concept->factor_egreso, 2) }}</td>
                                    <td class="text-center">
                                        @hasanyrole('superusuario|administrador')
                                            <a href="{{ route('settings.concepts.edit', $concept->id) }}"
                                               class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;">
                                                Editar
                                            </a>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="7">TOTAL</td>
                                    <td class="text-center fw-bold">{{ $concepts->count() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @php
                            $ingoM = 0;
                            $egrinoM = 0;
                        @endphp
                        @forelse($concepts as $concept)
                            @php
                                $isIngreso = strtolower($concept->type) === 'ingreso';
                                if ($isIngreso) { $ingoM++; $numM = $ingoM; } else { $egrinoM++; $numM = $egrinoM; }
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $isIngreso ? 'border-success' : 'border-danger' }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            @if($isIngreso)
                                                <b style="color: black;">INGRESO #{{ $numM }}</b>
                                            @else
                                                <span style="color: red; font-weight: bold;">EGRESO #{{ $numM }}</span>
                                            @endif
                                        </h6>
                                        @if($concept->status === 'active')
                                            <i class="ti ti-circle-check f-s-20 text-success"></i>
                                        @else
                                            <i class="ti ti-circle-x f-s-20 text-danger"></i>
                                        @endif
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Código:</b> {{ $concept->code }}</div>
                                        <div class="col-12"><b>Nombre:</b> {{ $concept->name }}</div>
                                        <div class="col-6"><b>ING. S/:</b> {{ number_format($concept->factor_ingreso, 2) }}</div>
                                        <div class="col-6"><b>EGR. S/:</b> {{ number_format($concept->factor_egreso, 2) }}</div>
                                    </div>
                                    @hasanyrole('superusuario|administrador')
                                        <div class="mt-2">
                                            <a href="{{ route('settings.concepts.edit', $concept->id) }}" class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;">Editar</a>
                                        </div>
                                    @endhasanyrole
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-primary">Total: {{ $concepts->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
