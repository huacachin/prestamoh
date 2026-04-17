<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">APERTURA DE CAJA</h4>
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
                    <a href="#" class="f-s-14">Apertura</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- Encabezado: APERTURA DE CAJA FECHA - X Hora - Y --}}
                    <div class="alert alert-light border mb-3" style="background: #fff;">
                        <div class="d-flex flex-wrap align-items-center gap-2" style="color: red; font-weight: bold;">
                            <span>APERTURA DE CAJA FECHA -</span>
                            <input type="date" class="form-control form-control-sm d-inline-block" style="width: 160px;"
                                   wire:model="fechaera">
                            <span>Hora - {{ $horaActual }}</span>
                        </div>
                    </div>

                    {{-- Form principal --}}
                    <form wire:submit.prevent="save">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-auto" style="width: 70px;">
                                <h5 class="mb-0 fw-bold">S/</h5>
                            </div>
                            <div class="col-md-3">
                                @if($currentMonth && !$isSuperUsuario)
                                    <h5 class="mb-0">: {{ number_format($currentMonth->saldo_inicial, 2) }}</h5>
                                @else
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           placeholder="0.00"
                                           value="{{ $currentMonth?->saldo_inicial }}"
                                           wire:model="solesm">
                                @endif
                            </div>
                            <div class="col">
                                @if(!$currentMonth || $isSuperUsuario)
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="ti ti-device-floppy f-s-12"></i> Guardar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="clear">
                                        <i class="ti ti-eraser f-s-12"></i> Limpiar
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($currentMonth)
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-auto" style="width: 70px;">
                                    <b>Usuario</b>
                                </div>
                                <div class="col-md-3">
                                    : {{ $currentMonth->user?->username ?? $currentMonth->user?->name ?? '-' }}
                                </div>
                            </div>
                        @endif
                    </form>

                    <hr>

                    {{-- Histórico Desktop --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="50">Id</th>
                                    <th class="text-center" width="100">Fecha</th>
                                    <th class="text-center" width="80">Hora</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-end" width="150">Importe</th>
                                    <th class="text-center" width="80">Moneda</th>
                                    @if($isSuperUsuario)
                                        <th class="text-center" width="120">Opciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($history as $row)
                                <tr onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $row->fecha?->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $row->hora ?: '-' }}</td>
                                    <td>{{ $row->user?->username ?? $row->user?->name ?? '-' }}</td>
                                    <td class="text-end">
                                        @if($isSuperUsuario && $editingId === $row->id)
                                            <input type="number" step="0.01" class="form-control form-control-sm"
                                                   wire:model="editingValue"
                                                   wire:keydown.enter="updateInline({{ $row->id }})"
                                                   wire:keydown.escape="cancelEdit">
                                        @else
                                            <span class="fw-bold">{{ number_format($row->saldo_inicial, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row->moneda }}</td>
                                    @if($isSuperUsuario)
                                        <td class="text-center text-nowrap">
                                            @if($editingId === $row->id)
                                                <button class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;"
                                                        wire:click="updateInline({{ $row->id }})">
                                                    <i class="ti ti-check"></i> Guardar
                                                </button>
                                                <button class="btn btn-xs btn-secondary" style="padding: 2px 8px; font-size: 10px;"
                                                        wire:click="cancelEdit">
                                                    <i class="ti ti-x"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;"
                                                        wire:click="startEdit({{ $row->id }})"
                                                        title="Editar importe">
                                                    <i class="ti ti-edit"></i> Editar
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isSuperUsuario ? 7 : 6 }}" class="py-4 text-muted text-center">
                                        No hay aperturas registradas
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="{{ $isSuperUsuario ? 7 : 6 }}" class="text-center fw-bold">
                                        Total: {{ $history->count() }} registros
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($history as $row)
                            <div class="card mb-2 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">{{ $row->fecha?->format('d/m/Y') }} - {{ $row->hora ?: '-' }}</h6>
                                        <span class="badge bg-primary">{{ $row->moneda }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Usuario:</b> {{ $row->user?->username ?? '-' }}</div>
                                        <div class="col-6 text-end"><b>S/</b> {{ number_format($row->saldo_inicial, 2) }}</div>
                                    </div>
                                    @if($isSuperUsuario)
                                        <button class="btn btn-xs btn-primary w-100 mt-2" style="font-size: 10px;"
                                                wire:click="startEdit({{ $row->id }})">
                                            <i class="ti ti-edit"></i> Editar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No hay aperturas registradas</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
