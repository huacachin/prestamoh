<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">Cambiar Estado</h4>
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

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        {{-- Tipo --}}
                        <div class="col-md-2">
                            <label class="form-label"><b>Tipo</b></label>
                            <select class="form-select" wire:model="tipoe">
                                <option value="Credito" selected>Credito</option>
                            </select>
                        </div>

                        {{-- Fecha de Pago --}}
                        <div class="col-md-2">
                            <label class="form-label"><b>Fecha de Pago</b></label>
                            <input type="text" name="fecha" autocomplete="off" class="form-control dates" wire:model.live.debounce.500ms="fecha">
                        </div>

                        {{-- Código - búsqueda con dropdown --}}
                        <div class="col-md-4 position-relative">
                            <label class="form-label"><b>Codigo</b></label>
                            <input type="text" name="search" class="form-control"
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Escriba ID, nombre o DNI para buscar..."
                                   autocomplete="off"
       list="hist_credits_estado" data-search-history="credits_estado">
<datalist id="hist_credits_estado" wire:ignore></datalist>

                            {{-- Dropdown de resultados --}}
                            @if($showDropdown && count($results) > 0)
                                <div class="position-absolute w-100 bg-white border shadow-lg rounded-bottom"
                                     style="z-index: 1050; max-height: 300px; overflow-y: auto; top: 100%;">
                                    @foreach($results as $credit)
                                        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center"
                                             style="cursor: pointer;"
                                             wire:click="selectCredit({{ $credit->id }})"
                                             onmouseover="this.style.backgroundColor='#e9ecef'"
                                             onmouseout="this.style.backgroundColor='white'">
                                            <div>
                                                <span class="fw-bold text-primary">{{ $credit->id }}</span>
                                                <span class="mx-1">-</span>
                                                <span>{{ $credit->client?->apellido_pat }} {{ $credit->client?->apellido_mat }} {{ $credit->client?->nombre }}</span>
                                                <small class="text-muted ms-2">({{ $credit->client?->documento }})</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold">S/ {{ number_format($credit->importe, 2) }}</span>
                                                @php
                                                    $bc = match($credit->situacion) {
                                                        'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                                        'Refinanciado' => 'bg-warning', default => 'bg-dark',
                                                    };
                                                @endphp
                                                <span class="badge {{ $bc }} ms-1">{{ $credit->situacion }}</span>
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

                        {{-- Situación (legacy estado.php solo deja "Cancelado" activo) --}}
                        <div class="col-md-2">
                            <label class="form-label"><b>Situacion</b></label>
                            <select class="form-select" disabled>
                                <option value="Cancelado" selected>Cancelado</option>
                            </select>
                        </div>

                        {{-- Botón --}}
                        <div class="col-md-2">
                            @php $bloqueado = !$selectedId || $saldoSel > 0.01; @endphp
                            <button class="btn btn-primary w-100 {{ $bloqueado ? 'disabled' : '' }}"
                                    @if($bloqueado) disabled @endif
                                    @if($saldoSel > 0.01) title="El crédito tiene saldo pendiente: no se puede cancelar" @endif
                                    wire:confirm="¿Está seguro de cambiar el estado del crédito a Cancelado?"
                                    wire:click="changeStatus">
                                <i class="ti ti-refresh f-s-14"></i> Cambiar Estado
                            </button>
                        </div>
                    </div>

                    {{-- Panel de datos del crédito seleccionado --}}
                    @if($selectedCredit)
                        <hr>
                        <div class="alert alert-info d-flex flex-wrap gap-3 align-items-center mb-0">
                            <div><b>Crédito:</b> {{ $selectedCredit->id }}</div>
                            <div><b>Cliente:</b> {{ $selectedCredit->client?->apellido_pat }} {{ $selectedCredit->client?->apellido_mat }} {{ $selectedCredit->client?->nombre }}</div>
                            <div><b>DNI:</b> {{ $selectedCredit->client?->documento }}</div>
                            <div><b>Capital:</b> S/ {{ number_format($selectedCredit->importe, 2) }}</div>
                            <div><b>Cuotas:</b> {{ $selectedCredit->cuotas }}</div>
                            <div><b>Interés:</b> {{ $selectedCredit->interes }}%</div>
                            <div><b>Fecha Préstamo:</b> {{ $selectedCredit->fecha_prestamo?->format('d/m/Y') }}</div>
                            <div>
                                <b>Situación actual:</b>
                                @php
                                    $bc = match($selectedCredit->situacion) {
                                        'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                        'Refinanciado' => 'bg-warning', default => 'bg-dark',
                                    };
                                @endphp
                                <span class="badge {{ $bc }}">{{ $selectedCredit->situacion }}</span>
                            </div>
                            <div>
                                <b>Saldo pendiente:</b>
                                <span class="{{ $saldoSel > 0.01 ? 'text-danger fw-bold' : '' }}">S/ {{ number_format($saldoSel, 2) }}</span>
                            </div>
                        </div>
                        @if($saldoSel > 0.01)
                            <div class="alert alert-danger mt-2 mb-0 py-2">
                                <i class="ti ti-alert-triangle"></i>
                                <b>No se puede cancelar:</b> el crédito tiene un saldo pendiente de
                                S/ {{ number_format($saldoSel, 2) }} — cancelarlo a mano condonaría esa deuda.
                                El crédito se cancela pagando el saldo (o por refinanciación).
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
