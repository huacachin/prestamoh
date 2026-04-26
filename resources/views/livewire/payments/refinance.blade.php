<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REFINANCIAR CRÉDITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Refinanciar #{{ $credit->id }}</span></li>
            </ul>
        </div>
    </div>

    <form wire:submit.prevent="refinance">
        {{-- Información del Préstamo (datos originales + asesor editable) --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 style="color:red;">Información del Préstamo</h6>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold">Cliente</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $credit->id }} - {{ $credit->client?->fullName() }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">DNI</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $credit->client?->documento }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Moneda</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $credit->moneda }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Capital</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($credit->importe, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Interés %</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ round($credit->interes, 2) }}" readonly>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Mora Interés</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($credit->mora2, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Pago x día atrasado</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($credit->mora1, 2) }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold">Asesor</label>
                        <select class="form-select form-select-sm" wire:model="nomasesores"
                                style="background-color:yellow;" required>
                            <option value="">Seleccione</option>
                            @foreach($asesores as $a)
                                <option value="{{ $a->username }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cronograma del crédito original --}}
        <div class="card shadow-sm mt-2">
            <div class="card-body pb-2">
                <div class="table-responsive" style="max-height: 350px; overflow:auto;">
                    <table class="table table-bordered table-sm" style="font-size: 11px;">
                        <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th class="text-center">N° Cuota</th>
                                <th class="text-center">Periodo</th>
                                <th class="text-center">Capital</th>
                                <th class="text-center">Interés</th>
                                <th class="text-center">Pagado</th>
                                <th class="text-center">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($credit->installments as $ins)
                                @php
                                    $cap = (float) $ins->importe_cuota;
                                    $int = (float) $ins->importe_interes;
                                    $apli = (float) $ins->importe_aplicado;
                                    // Legacy: Saldo en tabla = cuota + interés - importeapli (NO resta aplicado)
                                    $saldoDisplay = ($cap + $int) - $apli;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $ins->num_cuota }}</td>
                                    <td class="text-center">{{ $ins->fecha_pago?->format('Y-m-d') }}</td>
                                    <td class="text-end">{{ number_format($cap, 2) }}</td>
                                    <td class="text-end">{{ number_format($int, 2) }}</td>
                                    <td class="text-end">{{ number_format($apli, 2) }}</td>
                                    <td class="text-end">{{ number_format($saldoDisplay, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Datos del NUEVO crédito (todos readonly excepto el asesor de arriba) --}}
        <div class="card shadow-sm mt-2">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Capital</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($impopres, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Fecha Registro</label>
                        <input type="date" class="form-control form-control-sm bg-light" wire:model="fechar" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Fecha Préstamo</label>
                        <input type="date" class="form-control form-control-sm bg-light" wire:model="fechad" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Cuotas</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $cuot }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0 small fw-semibold">Interés</label>
                        <div class="d-flex gap-1">
                            <input type="text" class="form-control form-control-sm bg-light" style="max-width:80px;"
                                   value="{{ round($inte, 2) }}%" readonly>
                            <input type="text" class="form-control form-control-sm bg-light"
                                   value="{{ number_format($intmont, 2) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Tipo</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="Mensual" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Código</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $codpre_ }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Mora Interés</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($moracc, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Pago x día atrasado</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($moraii, 2) }}" readonly>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3 justify-content-center">
                    @if($importePagadoAlgo > 0)
                        <button type="submit" class="btn btn-sm btn-dark" wire:loading.attr="disabled">
                            <i class="ti ti-piggy-bank"></i> Refinanciar
                        </button>
                    @else
                        <div class="alert alert-warning mb-0" style="font-size: 12px; padding: 6px 12px;">
                            <i class="ti ti-alert-triangle"></i>
                            No se puede refinanciar: este crédito no tiene pagos previos.
                        </div>
                    @endif
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-x"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
