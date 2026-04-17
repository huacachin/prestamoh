<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">EGRESOS</h4>
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
                    <a href="#" class="f-s-14">Egresos</a>
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
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="3" id="tipoUsuario">
                                        <label class="form-check-label small" for="tipoUsuario">Usuario</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="4" id="tipoRespons">
                                        <label class="form-check-label small" for="tipoRespons">Respons.</label>
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
                                <a href="{{ route('cash.expenses.create') }}" class="btn btn-sm btn-danger">
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
                                    <th class="text-center" width="50">Op.</th>
                                    <th class="text-center" width="50">N°</th>
                                    <th class="text-center" width="40"><i class="ti ti-camera"></i></th>
                                    <th class="text-center" width="100">Fecha</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center">A</th>
                                    <th>Motivo</th>
                                    <th class="text-end" width="100">S/.</th>
                                    <th class="text-center" width="100">T.Comp.</th>
                                    <th class="text-center">Respons.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($expenses as $expense)
                                @php
                                    $rowStyle = ($expense->modo === 'Otros') ? 'color: red;' : '';
                                    $canEdit = $isSuperUsuario || (
                                        $expense->date?->format('Y-m-d') === $hoy
                                        && $expense->user_id === $userId
                                    );
                                @endphp
                                <tr style="{{ $rowStyle }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">
                                        @if($canEdit)
                                            <a href="{{ route('cash.expenses.edit', $expense->id) }}" title="Editar">
                                                <i class="ti ti-edit f-s-16 text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">
                                        @if($expense->image_path)
                                            <a href="#" target="_blank">
                                                <i class="ti ti-camera f-s-16 text-info"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $expense->date?->format('d/m/Y') }}</td>
                                    <td>{{ $expense->user?->username ?? $expense->user?->name ?? '-' }}</td>
                                    <td class="text-center">{{ $expense->reason }}</td>
                                    <td>{{ $expense->detail }}</td>
                                    <td class="text-end fw-bold">{{ number_format($expense->total, 2) }}</td>
                                    <td class="text-center">{{ $expense->document_type }}</td>
                                    <td>{{ $expense->in_charge }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Total General:</td>
                                    <td class="text-end fw-bold">{{ number_format($totalGeneral, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end"><b>Fijos:</b></td>
                                    <td class="text-end" style="color: red;">{{ number_format($tofijo, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end" style="color: red;"><b>Otros:</b></td>
                                    <td class="text-end">{{ number_format($totros, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Diario</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumdiario, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($valor1, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Mensual</b></td>
                                    <td class="text-end fw-bold">{{ number_format($summensu, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($valor2, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>D.M</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumdm, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($sumdm/2, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Fijos Total</b></td>
                                    <td colspan="3" class="text-end fw-bold" style="color: red;">{{ number_format($valor3, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($expenses as $expense)
                            @php
                                $isOtros = $expense->modo === 'Otros';
                                $canEdit = $isSuperUsuario || (
                                    $expense->date?->format('Y-m-d') === $hoy
                                    && $expense->user_id === $userId
                                );
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $isOtros ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ $isOtros ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0">{{ $expense->reason }}</h6>
                                            <small class="text-muted">{{ $expense->date?->format('d/m/Y') }}</small>
                                        </div>
                                        <span class="badge bg-primary">S/ {{ number_format($expense->total, 2) }}</span>
                                    </div>
                                    <div class="row g-1 mt-1" style="font-size: 12px;">
                                        <div class="col-12"><b>Motivo:</b> {{ $expense->detail }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $expense->user?->username ?? '-' }}</div>
                                        <div class="col-6"><b>T.Comp.:</b> {{ $expense->document_type ?: '-' }}</div>
                                        <div class="col-12"><b>Respons.:</b> {{ $expense->in_charge ?: '-' }}</div>
                                    </div>
                                    @if($canEdit)
                                        <div class="mt-2">
                                            <a href="{{ route('cash.expenses.edit', $expense->id) }}" class="btn btn-xs btn-outline-primary" style="padding: 2px 8px; font-size: 10px;">
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
                                <div style="color: red;"><b>Fijos:</b> S/ {{ number_format($tofijo, 2) }}</div>
                                <div><b>Otros:</b> S/ {{ number_format($totros, 2) }}</div>
                                <hr class="my-1">
                                <div><b>Diario:</b> S/ {{ number_format($sumdiario, 2) }} → {{ number_format($valor1, 2) }}</div>
                                <div><b>Mensual:</b> S/ {{ number_format($summensu, 2) }} → {{ number_format($valor2, 2) }}</div>
                                <div><b>D.M:</b> S/ {{ number_format($sumdm, 2) }} → {{ number_format($sumdm/2, 2) }}</div>
                                <div style="color: red;"><b>Fijos Total:</b> S/ {{ number_format($valor3, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
