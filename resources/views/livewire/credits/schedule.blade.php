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
                <a href="{{ route('exports.credits.schedule', $credit->id) }}" target="_blank" class="btn btn-sm btn-success">
                    <i class="ti ti-file-spreadsheet"></i> Excel
                </a>
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">
                    <i class="ti ti-printer"></i> Imprimir
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
                    <td style="background-color:#f0f0f0;">Tasa I. %</td>
                    <td>{{ round($credit->interes, 2) }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">Tasa M. %</td>
                    <td>{{ \App\Models\Credit::TASA_MORA_PCT }}% por cuota</td>
                    <td style="background-color:#f0f0f0;"></td>
                    <td></td>
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
                    <td style="background-color:#f0f0f0;">N° Cred.</td>
                    <td>
                        <strong>{{ $credit->id }}</strong> - <b>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</b>
                    </td>
                    <td style="background-color:#f0f0f0;">Moneda</td>
                    <td>{{ $credit->moneda === 'USD' ? 'Dólares' : 'Soles' }}</td>
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
                            <th class="text-center" style="color:#ffd6d6;">Mora Exon.</th>
                            <th class="text-center">Pagado</th>
                            <th class="text-center">Fecha Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Cuotas regulares --}}
                        @foreach($rows as $row)
                            @php $st = $row['color'] ? 'color:'.$row['color'].';' : ''; @endphp
                            {{-- Amarillo: pago realizado después de la fecha de vencimiento --}}
                            <tr @if($row['tarde']) style="background-color:#fff3cd;" @endif>
                                <td style="{{ $st }}" class="text-center">{{ $row['n'] }}</td>
                                <td style="{{ $st }}" class="text-center">{{ $row['periodo'] }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['total'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                <td class="text-end" style="color:red; white-space:nowrap;">{{ number_format($row['mora_exon'], 2) }}@if($row['mora_exon_dias'] > 0) - D. {{ $row['mora_exon_dias'] }}@endif</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['pagado'], 2) }}</td>
                                <td style="{{ $st }}">
                                    {{ $row['fecha_pago'] }}
                                    @if($row['hora'])
                                        <small>{{ $row['hora'] }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        {{-- Pagos OTROS (fuera del cronograma) --}}
                        @foreach($otrosRows as $row)
                            <tr>
                                <td class="text-center"><b>{{ $row['n'] }}</b></td>
                                <td></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-end"><b>{{ number_format($row['mora'], 2) }}</b></td>
                                <td class="text-end" style="color:red; white-space:nowrap;"><b>{{ number_format($row['mora_exon'], 2) }}@if($row['mora_exon_dias'] > 0) - D. {{ $row['mora_exon_dias'] }}@endif</b></td>
                                <td class="text-end"><b>{{ number_format($row['pagado'], 2) }}</b></td>
                                <td>
                                    <b>{{ $row['fecha_pago'] }}</b>
                                    @if($row['hora'])
                                        <font color="red">{{ $row['hora'] }}</font>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if(count($rows) > 0)
                            {{-- Totales: suma literal de cada columna (cuotas + filas OTROS) --}}
                            @php
                                $moraGlobal = $totals['mora'] + $sumOtrosMora;
                                $exonGlobal = $totals['mora_exon'] + $sumOtrosExon;
                                $exonDiasGlobal = $totals['mora_exon_dias'] + $sumOtrosExonDias;
                                $pagadoGlobal = $totals['pagado'] + $sumOtros;
                            @endphp
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center"><b>Totales</b></td>
                                <td class="text-end"><b>{{ number_format($totals['capital'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($totals['interes'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($totals['capital'] + $totals['interes'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($moraGlobal, 2) }}</b></td>
                                {{-- Mora exonerada: informativa, NO suma a los demás totales --}}
                                <td class="text-end" style="color:red; white-space:nowrap;"><b>{{ number_format($exonGlobal, 2) }}@if($exonDiasGlobal > 0) - D. {{ $exonDiasGlobal }}@endif</b></td>
                                <td class="text-end"><b>{{ number_format($pagadoGlobal, 2) }}</b></td>
                                <td></td>
                            </tr>
                            {{-- Total recibido: solo aporta cuando hay mora (pagos + mora) --}}
                            @if($moraGlobal > 0)
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="5" class="text-center"><b>Total pagado + mora</b></td>
                                    <td colspan="3" class="text-center">
                                        <b>{{ number_format($totalGeneral, 2) }}</b>
                                    </td>
                                    <td></td>
                                </tr>
                            @endif
                            {{-- Saldo = capital + interés − pagado (la mora se cobra aparte) --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="5" class="text-center" style="color:red;"><b>Saldo</b></td>
                                <td colspan="3" class="text-center" style="color:red;">
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
