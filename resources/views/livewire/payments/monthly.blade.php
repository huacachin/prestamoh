<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PAGOS MENSUALES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Mensual</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="mes">
                                        <option value="1">Enero</option>
                                        <option value="2">Febrero</option>
                                        <option value="3">Marzo</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Mayo</option>
                                        <option value="6">Junio</option>
                                        <option value="7">Julio</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 100px;">
                                    <label class="form-label mb-0 small">Anio</label>
                                    <select class="form-select form-select-sm" wire:model="anio">
                                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Credito</th>
                                <th>Cuota</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Recibo</th>
                                <th>Usuario</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $payment->fecha?->format('d/m/Y') }}</td>
                                    <td>{{ $payment->credit?->client?->fullName() }}</td>
                                    <td>{{ $payment->credit?->client?->documento }}</td>
                                    <td>
                                        <a href="{{ route('credits.show', $payment->credit_id) }}">
                                            #{{ $payment->credit_id }}
                                        </a>
                                    </td>
                                    <td>{{ $payment->installment?->num_cuota }}</td>
                                    <td>
                                        @php
                                            $bc = match($payment->tipo) {
                                                'CAPITAL' => 'bg-success',
                                                'INTERES' => 'bg-info',
                                                'MORA' => 'bg-danger',
                                                default => 'bg-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $bc }}">{{ $payment->tipo }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($payment->monto, 2) }}</td>
                                    <td>{{ $payment->nro_recibo }}</td>
                                    <td>{{ $payment->user?->name }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted">No hay pagos para este mes</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="5"></td>
                                <td class="text-end"><strong>{{ number_format($totalMonto, 2) }}</strong></td>
                                <td></td>
                                <td class="num">{{ $payments->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
