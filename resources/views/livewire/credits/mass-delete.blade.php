<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">ELIMINAR MASIVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Eliminar Masivo</span></li>
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
                                        <label class="form-check-label small" for="tipoCodigo">Código</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="2" id="tipoAsesor">
                                        <label class="form-check-label small" for="tipoAsesor">Asesor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.live="tipo" value="3" id="tipoUsuario">
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
                            <button type="button" wire:click="exportExcel" class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </button>
                        </div>
                    </form>

                    @php
                        $hoy = now()->format('Y-m-d');
                        $isSuperUsuario = auth()->user()->hasRole('superusuario');
                    @endphp

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="50">Op</th>
                                    <th class="text-center" width="50">N°</th>
                                    <th class="text-center" width="100">Fecha</th>
                                    <th class="text-center" width="80">Hora</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center">Asesor</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center" width="100">Código</th>
                                    <th class="text-end" width="100">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($records as $record)
                                @php
                                    $canEdit = $isSuperUsuario || ($record->date && $record->date->format('Y-m-d') === $hoy);
                                @endphp
                                <tr onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">
                                        @if($canEdit)
                                            <a href="{{ route('credits.mass-delete.edit', $record->id) }}" title="Ver detalle y modificar">
                                                <i class="ti ti-edit f-s-16 text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $record->date?->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $record->time }}</td>
                                    <td>{{ $record->performed_by ?? $record->user }}</td>
                                    <td>{{ $record->advisor }}</td>
                                    <td>{{ trim($record->credit?->client?->apellido_pat . ' ' . $record->credit?->client?->apellido_mat . ' ' . $record->credit?->client?->nombre) }}</td>
                                    <td class="text-center">
                                        @if($record->credit_id)
                                            <a href="{{ route('credits.show', $record->credit_id) }}">
                                                #{{ $record->credit_id }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($record->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="8" class="text-end fw-bold">TOTAL:</td>
                                    <td class="text-end fw-bold">{{ number_format($totalSum, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($records as $record)
                            @php
                                $canEdit = $isSuperUsuario || ($record->date && $record->date->format('Y-m-d') === $hoy);
                            @endphp
                            <div class="card mb-2 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            {{ trim($record->credit?->client?->apellido_pat . ' ' . $record->credit?->client?->apellido_mat . ' ' . $record->credit?->client?->nombre) }}
                                        </h6>
                                        <span class="badge bg-primary">S/ {{ number_format($record->amount, 2) }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Código:</b>
                                            @if($record->credit_id)
                                                <a href="{{ route('credits.show', $record->credit_id) }}">#{{ $record->credit_id }}</a>
                                            @endif
                                        </div>
                                        <div class="col-6"><b>Fecha:</b> {{ $record->date?->format('d/m/Y') }}</div>
                                        <div class="col-6"><b>Hora:</b> {{ $record->time }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $record->performed_by ?? $record->user }}</div>
                                        <div class="col-12"><b>Asesor:</b> {{ $record->advisor }}</div>
                                    </div>
                                    @if($canEdit)
                                        <div class="mt-2">
                                            <a href="{{ route('credits.mass-delete.edit', $record->id) }}" class="btn btn-xs btn-outline-primary" style="padding: 2px 8px; font-size: 10px;">
                                                <i class="ti ti-edit"></i> Editar
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-primary">Total: S/ {{ number_format($totalSum, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
