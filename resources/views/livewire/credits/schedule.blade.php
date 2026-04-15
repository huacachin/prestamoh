<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CRONOGRAMA DE PAGOS</h4>
            <small class="text-muted">{{ $credit->client?->fullName() }} — {{ $credit->moneda === 'USD' ? '$' : 'S/.' }} {{ number_format($credit->importe, 2) }}</small>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('credits.show', $credit->id) }}" class="f-s-14">Detalle</a></li>
                <li class="d-flex active"><span class="f-s-14">Cronograma</span></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body pb-2">
            <div class="row g-3 mb-3">
                <div class="col-auto"><small class="text-muted">Capital:</small> <strong>{{ number_format($credit->importe, 2) }}</strong></div>
                <div class="col-auto"><small class="text-muted">Interés:</small> <strong>{{ $credit->interes }}%</strong></div>
                <div class="col-auto"><small class="text-muted">Cuotas:</small> <strong>{{ $credit->cuotas }} ({{ $credit->tipoPlanillaLabel() }})</strong></div>
                <div class="col-auto"><small class="text-muted">Fecha:</small> <strong>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</strong></div>
                <div class="col-auto"><small class="text-muted">Vencimiento:</small> <strong>{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</strong></div>
            </div>

            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>Cuota</th>
                        <th>Fecha Venc.</th>
                        <th>Capital</th>
                        <th>Interés</th>
                        <th>Total Cuota</th>
                        <th>Pagado Cap.</th>
                        <th>Pagado Int.</th>
                        <th>Saldo</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $sumCap=0; $sumInt=0; $sumPagCap=0; $sumPagInt=0; @endphp
                    @foreach($credit->installments as $inst)
                        @php
                            $total = $inst->importe_cuota + $inst->importe_interes;
                            $saldo = $inst->saldoPendiente();
                            $vencida = !$inst->pagado && $inst->fecha_vencimiento?->isPast();
                            $sumCap += $inst->importe_cuota;
                            $sumInt += $inst->importe_interes;
                            $sumPagCap += $inst->importe_aplicado;
                            $sumPagInt += $inst->interes_aplicado;
                        @endphp
                        <tr class="{{ $vencida ? 'table-danger' : '' }}">
                            <td>{{ $inst->num_cuota }}</td>
                            <td>{{ $inst->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format($inst->importe_cuota, 2) }}</td>
                            <td class="text-end">{{ number_format($inst->importe_interes, 2) }}</td>
                            <td class="text-end">{{ number_format($total, 2) }}</td>
                            <td class="text-end">{{ number_format($inst->importe_aplicado, 2) }}</td>
                            <td class="text-end">{{ number_format($inst->interes_aplicado, 2) }}</td>
                            <td class="text-end">{{ number_format($saldo, 2) }}</td>
                            <td>
                                @if($inst->pagado)
                                    <span class="badge bg-success">Pagado</span>
                                @elseif($vencida)
                                    <span class="badge bg-danger">Vencida</span>
                                @else
                                    <span class="badge bg-warning">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-primary">
                    <tr>
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td class="text-end"><strong>{{ number_format($sumCap, 2) }}</strong></td>
                        <td class="text-end"><strong>{{ number_format($sumInt, 2) }}</strong></td>
                        <td class="text-end"><strong>{{ number_format($sumCap + $sumInt, 2) }}</strong></td>
                        <td class="text-end"><strong>{{ number_format($sumPagCap, 2) }}</strong></td>
                        <td class="text-end"><strong>{{ number_format($sumPagInt, 2) }}</strong></td>
                        <td class="text-end"><strong>{{ number_format(($sumCap+$sumInt)-($sumPagCap+$sumPagInt), 2) }}</strong></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('credits.show', $credit->id) }}" class="btn btn-sm btn-secondary">Volver al crédito</a>
            </div>
        </div>
    </div>
</div>
