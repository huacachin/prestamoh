<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">CRONOGRAMA DE PAGOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Cronograma</span></li>
            </ul>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="row my-2">
        <div class="col-12">
            <div class="d-flex gap-2 py-1">
                <button class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="ti ti-file-spreadsheet"></i>
                </button>
                <a href="{{ route('clients.show', $credit->client_id) }}" class="btn btn-sm btn-secondary ms-auto">Regresar</a>
            </div>
        </div>
    </div>

    {{-- Ficha del crédito --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-bordered mb-0" style="font-size: 13px;">
                <tr>
                    <td colspan="4" class="bg-primary text-white" style="font-weight:500; padding:6px 12px;">
                        <span style="color:red;">Reporte de Pago</span>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0; width:15%;">Cliente</td>
                    <td style="width:35%;">{{ $credit->client?->fullName() }}</td>
                    <td style="background-color:#f0f0f0; width:15%;">Asesor</td>
                    <td>{{ $credit->asesor ?: $credit->client?->asesor?->name }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">DNI</td>
                    <td>{{ $credit->client?->documento }}</td>
                    <td style="background-color:#f0f0f0;">Tasa %</td>
                    <td>{{ round($credit->interes, 2) }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Expediente</td>
                    <td>
                        <a href="{{ route('clients.show', $credit->client_id) }}" target="_blank">
                            {{ $credit->client?->expediente }}
                        </a>
                    </td>
                    <td style="background-color:#f0f0f0;">Capital</td>
                    <td>{{ number_format($credit->importe, 2) }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Crédito</td>
                    <td>
                        <strong>{{ $credit->id }}</strong> - <b>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</b>
                    </td>
                    <td style="background-color:#f0f0f0;">Moneda</td>
                    <td>{{ $credit->moneda }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Tabla cronograma --}}
    <div class="card shadow-sm mt-2">
        <div class="card-body pb-2">
            <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                <table class="table table-bordered table-hover" style="font-size: 11px;">
                    <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                        <tr>
                            <th class="text-center">N° Cuota</th>
                            <th class="text-center">Periodo</th>
                            <th class="text-center">Capital</th>
                            <th class="text-center">Interés</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Mora</th>
                            <th class="text-center">Pagado</th>
                            <th class="text-center">Fecha Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Cuotas regulares --}}
                        @foreach($rows as $row)
                            @php $st = $row['color'] ? 'color:'.$row['color'].';' : ''; @endphp
                            <tr>
                                <td style="{{ $st }}" class="text-center">{{ $row['n'] }}</td>
                                <td style="{{ $st }}" class="text-center">{{ $row['periodo'] }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['total'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['pagado'], 2) }}</td>
                                <td style="{{ $st }}">
                                    {{ $row['fecha_pago'] }}
                                    @if($row['hora'])
                                        <small>{{ $row['hora'] }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if(count($rows) > 0)
                            {{-- Totales del cronograma --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" rowspan="2" class="text-center"><b>Total</b></td>
                                <td rowspan="2" class="text-end"><b>{{ number_format($totals['capital'], 2) }}</b></td>
                                <td rowspan="2" class="text-end"><b>{{ number_format($totals['interes'], 2) }}</b></td>
                                <td rowspan="2" class="text-end"><b>{{ number_format($totals['capital'] + $totals['interes'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($totals['mora'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($totals['pagado'], 2) }}</b></td>
                                <td></td>
                            </tr>
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center">
                                    <b>{{ number_format($totals['mora'] + $totals['pagado'], 2) }}</b>
                                </td>
                                <td></td>
                            </tr>
                        @endif

                        {{-- Pagos OTROS (fuera del cronograma) --}}
                        @foreach($otrosRows as $row)
                            <tr>
                                <td class="text-center"><b>{{ $row['n'] }}</b></td>
                                <td></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-end"><b>{{ number_format($row['mora'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($row['pagado'], 2) }}</b></td>
                                <td>
                                    <b>{{ $row['fecha_pago'] }}</b>
                                    @if($row['hora'])
                                        <font color="red">{{ $row['hora'] }}</font>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if(count($otrosRows) > 0 || count($rows) > 0)
                            {{-- Totales pagos OTROS --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <th colspan="5" rowspan="3" class="text-center"><b>Totales</b></th>
                                <th class="text-center"><b>{{ number_format($sumOtrosMora, 2) }}</b></th>
                                <th class="text-center"><b>{{ number_format($sumOtros, 2) }}</b></th>
                                <th></th>
                            </tr>
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center">
                                    <b>{{ number_format($sumOtros + $sumOtrosMora, 2) }}</b>
                                </td>
                                <td></td>
                            </tr>
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center">
                                    <b>{{ number_format($totalGeneral, 2) }}</b>
                                </td>
                                <td></td>
                            </tr>
                            {{-- Saldo --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="5" class="text-center" style="color:red;"><b>Saldo</b></td>
                                <td colspan="2" class="text-center" style="color:red;">
                                    <b>{{ number_format(abs($saldo), 2) }}</b>
                                </td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
