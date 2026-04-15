<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CAMBIAR ESTADO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Cambiar Estado</span></li>
            </ul>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Form section --}}
                    <div class="row my-2">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="form-label fw-bold f-s-13">Fecha</label>
                            <input type="date" class="form-control form-control-sm" wire:model="fecha">
                            @error('fecha') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="form-label fw-bold f-s-13">Nueva Situaci&oacute;n</label>
                            <select class="form-select form-select-sm" wire:model="newSituacion">
                                <option value="">-- Seleccionar --</option>
                                @foreach($situaciones as $sit)
                                    <option value="{{ $sit }}">{{ $sit }}</option>
                                @endforeach
                            </select>
                            @error('newSituacion') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-4 col-sm-8 mb-2">
                            <label class="form-label fw-bold f-s-13">Buscar cr&eacute;dito</label>
                            <input type="search" class="form-control form-control-sm"
                                   placeholder="Nombre, DNI o ID del cr&eacute;dito..." wire:model.live.debounce.300ms="search">
                        </div>
                        <div class="col-md-2 col-sm-4 mb-2 d-flex align-items-end">
                            <button class="btn btn-sm btn-primary w-100"
                                    wire:click="changeStatus"
                                    @if(!$creditId || !$newSituacion) disabled @endif
                                    onclick="return confirm('&iquest;Est&aacute; seguro de cambiar el estado del cr&eacute;dito?')">
                                <i class="ti ti-refresh f-s-12"></i> Cambiar Estado
                            </button>
                        </div>
                    </div>

                    @error('creditId') <div class="alert alert-danger py-1 f-s-13">{{ $message }}</div> @enderror

                    @if($creditId)
                        <div class="alert alert-info py-1 f-s-13 mb-2">
                            <i class="ti ti-info-circle"></i>
                            Cr&eacute;dito seleccionado: <strong>#{{ $creditId }}</strong>
                        </div>
                    @endif

                    {{-- Credits table --}}
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>DNI</th>
                                <th>Capital</th>
                                <th>Situaci&oacute;n</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($credits as $credit)
                                <tr class="{{ $creditId == $credit->id ? 'table-warning' : '' }}">
                                    <td>{{ $credit->id }}</td>
                                    <td>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                                    <td>{{ $credit->client?->fullName() }}</td>
                                    <td>{{ $credit->client?->documento }}</td>
                                    <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                                    <td>
                                        @php
                                            $bc = match($credit->situacion) {
                                                'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                                'Refinanciado' => 'bg-warning', 'Eliminado' => 'bg-danger', default => 'bg-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $bc }}">{{ $credit->situacion }}</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm {{ $creditId == $credit->id ? 'btn-warning' : 'btn-outline-primary' }}"
                                                wire:click="selectCredit({{ $credit->id }})">
                                            <i class="ti ti-check f-s-12"></i>
                                            {{ $creditId == $credit->id ? 'Seleccionado' : 'Seleccionar' }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-muted text-center">No se encontraron cr&eacute;ditos activos</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
