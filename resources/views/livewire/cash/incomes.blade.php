<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">INGRESOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Ingresos</a>
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
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="1" id="tipoA">
                                        <label class="form-check-label small" for="tipoA">A</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="2" id="tipoMotivo">
                                        <label class="form-check-label small" for="tipoMotivo">Motivo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="3" id="tipoAsesor">
                                        <label class="form-check-label small" for="tipoAsesor">Asesor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="4" id="tipoUsuario">
                                        <label class="form-check-label small" for="tipoUsuario">Usuario</label>
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="compra"
                                       placeholder="Ingrese el texto a buscar">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Inicio</b></label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="fei">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Fin</b></label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="fef">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @hasanyrole('superusuario|administrador|director|asesor')
                                <a href="{{ route('cash.incomes.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Agregar Nuevo
                                </a>
                            @endhasanyrole
                            <a href="#" class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                        </div>
                    </form>

                    @php
                        $isSuperUsuario = auth()->user()->hasRole('superusuario');
                        $userId = auth()->id();
                        $hoy = now()->format('Y-m-d');
                    @endphp

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" width="50">Op</th>
                                    <th class="text-center" width="50">N°</th>
                                    <th class="text-center" width="40"><i class="ti ti-camera"></i></th>
                                    <th class="text-center" width="100">Fecha</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center">Asesor</th>
                                    <th class="text-center">A</th>
                                    <th>Motivo</th>
                                    <th class="text-end" width="100">S/.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                @php
                                    $isOtros = $row['modo'] === 'Otros';
                                    $rowStyle = $isOtros ? 'color: red;' : '';
                                    $canEdit = $row['editable'] && ($isSuperUsuario || (
                                        $row['date']?->format('Y-m-d') === $hoy
                                        && $row['user_id'] === $userId
                                    ));
                                @endphp
                                <tr style="{{ $rowStyle }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">
                                        @if($canEdit)
                                            <a href="{{ route('cash.incomes.edit', $row['id']) }}" title="Editar">
                                                <i class="ti ti-edit f-s-16 text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">
                                        @if($row['has_image'])
                                            <a href="#" target="_blank">
                                                <i class="ti ti-camera f-s-16 text-info"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row['date']?->format('d/m/Y') }}</td>
                                    <td>{{ $row['usuario'] }}</td>
                                    <td>{{ $row['asesor'] }}</td>
                                    <td class="text-center">{{ $row['reason'] }}</td>
                                    <td>{{ $row['detail'] }}</td>
                                    <td class="text-end fw-bold">{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="5" rowspan="6" class="text-center align-middle fw-bold" style="font-size: 14px;">Total</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end fw-bold">{{ number_format($totalGeneral, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Fijos</b></td>
                                    <td class="text-end fw-bold">{{ number_format($tofijo, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center" style="color: red;"><b>Otros</b></td>
                                    <td class="text-end fw-bold" style="color: red;">{{ number_format($totros, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Capital</b></td>
                                    <td class="text-end fw-bold">{{ number_format($tocapi, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Interes</b></td>
                                    <td class="text-end fw-bold">{{ number_format($totinte, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Mora</b></td>
                                    <td class="text-end fw-bold">{{ number_format($totmora, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($rows as $row)
                            @php
                                $isOtros = $row['modo'] === 'Otros';
                                $canEdit = $row['editable'] && ($isSuperUsuario || (
                                    $row['date']?->format('Y-m-d') === $hoy
                                    && $row['user_id'] === $userId
                                ));
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $isOtros ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ $isOtros ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0">{{ $row['reason'] }}</h6>
                                            <small class="text-muted">{{ $row['date']?->format('d/m/Y') }}</small>
                                        </div>
                                        <span class="badge bg-primary">S/ {{ number_format($row['total'], 2) }}</span>
                                    </div>
                                    <div class="row g-1 mt-1" style="font-size: 12px;">
                                        <div class="col-12"><b>Motivo:</b> {{ $row['detail'] }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $row['usuario'] ?: '-' }}</div>
                                        <div class="col-6"><b>Asesor:</b> {{ $row['asesor'] ?: '-' }}</div>
                                    </div>
                                    @if($canEdit)
                                        <div class="mt-2">
                                            <a href="{{ route('cash.incomes.edit', $row['id']) }}" class="btn btn-xs btn-outline-primary" style="padding: 2px 8px; font-size: 10px;">
                                                <i class="ti ti-edit"></i> Editar
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="card mt-2">
                            <div class="card-body p-2" style="font-size: 12px;">
                                <div><b>Total General:</b> S/ {{ number_format($totalGeneral, 2) }}</div>
                                <div><b>Fijos:</b> S/ {{ number_format($tofijo, 2) }}</div>
                                <div style="color: red;"><b>Otros:</b> S/ {{ number_format($totros, 2) }}</div>
                                <div><b>Capital:</b> S/ {{ number_format($tocapi, 2) }}</div>
                                <div><b>Interés:</b> S/ {{ number_format($totinte, 2) }}</div>
                                <div><b>Mora:</b> S/ {{ number_format($totmora, 2) }}</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
