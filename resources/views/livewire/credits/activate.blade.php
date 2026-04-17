<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">Activar Prestamo</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Activar Prestamo</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        {{-- Tipo --}}
                        <div class="col-md-2">
                            <label class="form-label"><b>Tipo</b></label>
                            <select class="form-select" wire:model="tipoe">
                                <option value="Pago-Credito" selected>Prestamo</option>
                            </select>
                        </div>

                        {{-- Búsqueda con dropdown --}}
                        <div class="col-md-6 position-relative">
                            <label class="form-label"><b>Prestamo</b></label>
                            <input type="text" class="form-control"
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Escriba ID, nombre o DNI para buscar..."
                                   autocomplete="off">

                            {{-- Dropdown de resultados --}}
                            @if($showDropdown && count($results) > 0)
                                <div class="position-absolute w-100 bg-white border shadow-lg rounded-bottom"
                                     style="z-index: 1050; max-height: 300px; overflow-y: auto; top: 100%;">
                                    @foreach($results as $credit)
                                        <div class="px-3 py-2 border-bottom cursor-pointer d-flex justify-content-between align-items-center"
                                             style="cursor: pointer;"
                                             wire:click="selectCredit({{ $credit->id }})"
                                             onmouseover="this.style.backgroundColor='#e9ecef'"
                                             onmouseout="this.style.backgroundColor='white'">
                                            <div>
                                                <span class="fw-bold text-primary">{{ $credit->id }}</span>
                                                <span class="mx-1">-</span>
                                                <span>{{ $credit->client?->nombre }} {{ $credit->client?->apellido_pat }} {{ $credit->client?->apellido_mat }}</span>
                                                <small class="text-muted ms-2">({{ $credit->client?->documento }})</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold">S/ {{ number_format($credit->importe, 2) }}</span>
                                                <span class="badge bg-secondary ms-1">{{ $credit->situacion }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($showDropdown && strlen(trim($search)) >= 1 && count($results) === 0)
                                <div class="position-absolute w-100 bg-white border shadow rounded-bottom px-3 py-3 text-muted text-center"
                                     style="z-index: 1050; top: 100%;">
                                    No se encontraron resultados
                                </div>
                            @endif
                        </div>

                        {{-- Botón --}}
                        <div class="col-md-4">
                            <button class="btn btn-primary {{ !$selectedId ? 'disabled' : '' }}"
                                    @if(!$selectedId) disabled @endif
                                    wire:confirm="¿Está seguro de Re-Activar este Préstamo?"
                                    wire:click="activate">
                                <i class="ti ti-refresh f-s-14"></i> Confirmar Re-Activar
                            </button>
                        </div>
                    </div>

                    {{-- Panel de datos del crédito seleccionado --}}
                    @if($selectedCredit)
                        <hr>
                        <div class="alert alert-info d-flex flex-wrap gap-3 align-items-center mb-0">
                            <div><b>Crédito:</b> {{ $selectedCredit->id }}</div>
                            <div><b>Cliente:</b> {{ $selectedCredit->client?->nombre }} {{ $selectedCredit->client?->apellido_pat }} {{ $selectedCredit->client?->apellido_mat }}</div>
                            <div><b>DNI:</b> {{ $selectedCredit->client?->documento }}</div>
                            <div><b>Capital:</b> S/ {{ number_format($selectedCredit->importe, 2) }}</div>
                            <div><b>Cuotas:</b> {{ $selectedCredit->cuotas }}</div>
                            <div><b>Interés:</b> {{ $selectedCredit->interes }}%</div>
                            <div><b>Fecha Préstamo:</b> {{ $selectedCredit->fecha_prestamo?->format('d/m/Y') }}</div>
                            <div>
                                <b>Situación:</b>
                                <span class="badge {{ $selectedCredit->situacion === 'Cancelado' ? 'bg-secondary' : 'bg-warning' }}">
                                    {{ $selectedCredit->situacion }}
                                </span>
                            </div>
                            @if($selectedCredit->fecha_cancelacion)
                                <div><b>Fecha Cancelación:</b> {{ $selectedCredit->fecha_cancelacion->format('d/m/Y') }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
