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
                    <td style="background-color:#f0f0f0;">INT. %</td>
                    <td>{{ round($credit->interes, 2) }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Expediente</td>
                    <td>
                        <a href="{{ route('clients.show', $credit->client_id) }}" target="_blank">
                            {{ $credit->client?->expediente }}
                        </a>
                    </td>
                    <td style="background-color:#f0f0f0;">MOR. %</td>
                    <td>
                        @php
                            $cuotaRef = count($rows) ? $rows[0]['total'] : 0.0;
                            $tp = (int) $credit->tipo_planilla;
                            $divisorMora = $tp === 1 ? 7 : ($tp === 3 ? 30 : null);
                        @endphp
                        @if($divisorMora && $cuotaRef > 0)
                            {{ \App\Models\Credit::TASA_MORA_PCT }}% × {{ number_format($cuotaRef, 2) }} ÷ {{ $divisorMora }} =
                            <b>{{ number_format($credit->moraDiaria($cuotaRef), 2) }}</b> x día
                        @else
                            {{ number_format($credit->moraDiaria($cuotaRef), 2) }} x día (histórico)
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Cred.</td>
                    <td>
                        <strong>{{ $credit->id }}</strong> - <b>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</b>
                    </td>
                    <td style="background-color:#f0f0f0;">Capital</td>
                    <td>{{ number_format($credit->importe, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Tabla cronograma --}}
    <div class="card shadow-sm mt-2">
        <div class="card-body pb-2">
            <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                @php $tieneExc = ($totals['excedente'] ?? 0) > 0; @endphp
                <table class="table table-bordered table-hover" style="font-size: 11px;">
                    <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                        {{-- Columnas homologadas con /payments/create --}}
                        <tr>
                            <th class="text-center">Cuota</th>
                            <th class="text-center">Fecha Venc.</th>
                            <th class="text-center">Capital</th>
                            <th class="text-center">Interés</th>
                            @if($tieneExc)
                                <th class="text-center">Excedente</th>
                            @endif
                            <th class="text-center">Pagado Cap.</th>
                            <th class="text-center">Pagado Int.</th>
                            <th class="text-center">Pagado</th>
                            <th class="text-center">Saldo</th>
                            <th class="text-center">Mora</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Fecha Pago</th>
                            <th class="text-center">Rec.</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Cuotas regulares --}}
                        @php $nVencida = 0; $nPendiente = 0; $hoy = now()->format('Y-m-d'); @endphp
                        @foreach($rows as $row)
                            @php
                                $st = $row['color'] ? 'color:'.$row['color'].';' : '';
                                // Vencidas y pendientes con contador legacy delante del
                                // número (1-16, 2-17, ...) como /payments/create
                                $vencida = ! $row['flag_pagado'] && $row['periodo'] !== '' && $row['periodo'] < $hoy;
                                $pendiente = ! $row['flag_pagado'] && ! $vencida;
                                if ($vencida) $nVencida++;
                                if ($pendiente) $nPendiente++;
                            @endphp
                            {{-- Roja: vencida impaga (gana). Amarillo: pagada tarde. --}}
                            <tr @if($vencida) class="table-danger" @elseif($row['tarde']) style="background-color:#fff3cd;" @endif>
                                <td style="{{ $st }}" class="text-center">@if($vencida){{ $nVencida }}-@elseif($pendiente){{ $nPendiente }}-@endif{{ $row['n'] }}</td>
                                <td style="{{ $st }}" class="text-center">{{ $row['periodo'] }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                @if($tieneExc)
                                    <td style="{{ $st }}" class="text-end">{{ number_format($row['excedente'], 2) }}</td>
                                @endif
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['pagado_cap'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['pagado_int'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['pagado_cap'] + $row['pagado_int'] + $row['pagado_exc'], 2) }}</td>
                                <td style="{{ $st }}" class="text-end">{{ number_format($row['saldo'], 2) }}</td>
                                {{-- Mora unificada (homologada con /payments/create):
                                     pagada en negro, exonerada en rojo, con tooltips --}}
                                @php
                                    $mpc = $moraPagadaCuotas[$row['installment_id']]
                                        ?? [['num' => $row['n'], 'monto' => $row['mora'], 'dias' => null]];
                                    $tipMoraPag = 'Mora pagada de '.count($mpc).' cuota(s):<br>'
                                        .collect($mpc)->take(15)->map(fn ($it) =>
                                            'Cuota '.$it['num'].': '.number_format($it['monto'], 2)
                                            .($it['dias'] ? ' - D. '.$it['dias'] : ''))->implode('<br>')
                                        .(count($mpc) > 15 ? '<br>…' : '')
                                        .'<br>Total: '.number_format(collect($mpc)->sum('monto'), 2)
                                        .(collect($mpc)->sum('dias') ? ' - D. '.collect($mpc)->sum('dias') : '');
                                @endphp
                                <td class="text-end" style="white-space:nowrap;">
                                    @if($row['mora'] > 0)
                                        <span data-bs-toggle="tooltip" data-bs-html="true" title="{{ $tipMoraPag }}" style="cursor:help;">{{ number_format($row['mora'], 2) }}</span>
                                    @endif
                                    @if($row['mora_exon'] > 0)
                                        @if($row['mora'] > 0)<br>@endif
                                        <span class="text-danger" data-bs-toggle="tooltip" title="Mora exonerada" style="cursor:help;">{{ number_format($row['mora_exon'], 2) }} - D. {{ $row['mora_exon_dias'] }}</span>
                                    @endif
                                    @if($row['mora'] <= 0 && $row['mora_exon'] <= 0)
                                        0.00
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($row['flag_pagado'])
                                        <span class="badge bg-success">Pagado</span>
                                    @elseif($vencida)
                                        <span class="badge bg-danger">Vencida</span>
                                    @else
                                        <span class="badge bg-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td style="{{ $st }}">
                                    {{ $row['fecha_pago'] }}
                                    @if($row['hora'])
                                        <small>{{ $row['hora'] }}</small>
                                    @endif
                                </td>
                                <td class="text-center" style="white-space:nowrap;">
                                    @php $rec = $recibos[$row['installment_id']] ?? null; @endphp
                                    @if($rec)
                                        <button type="button" class="btn btn-sm btn-secondary py-0 px-1"
                                                title="Ver recibo" onclick="abrirRecibo(@js($rec['ver']))">
                                            <i class="ti ti-eye"></i>
                                        </button>
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
                                @if($tieneExc)
                                    <td class="text-center"><b>0.00</b></td>
                                @endif
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-center"><b>0.00</b></td>
                                <td class="text-end"><b>{{ number_format($row['pagado'], 2) }}</b></td>
                                <td></td>
                                <td class="text-end" style="white-space:nowrap;">
                                    @if($row['mora'] > 0)
                                        <b><span data-bs-toggle="tooltip" title="Mora pagada" style="cursor:help;">{{ number_format($row['mora'], 2) }}</span></b>
                                    @else
                                        <b>0.00</b>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge bg-secondary">Otros</span></td>
                                <td>
                                    <b>{{ $row['fecha_pago'] }}</b>
                                    @if($row['hora'])
                                        <font color="red">{{ $row['hora'] }}</font>
                                    @endif
                                </td>
                                <td></td>
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
                            @php
                                $tPagCap = collect($rows)->sum('pagado_cap');
                                $tPagInt = collect($rows)->sum('pagado_int');
                                $tPagExc = collect($rows)->sum('pagado_exc');
                                $tSaldoCol = collect($rows)->sum('saldo');
                            @endphp
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="2" class="text-center"><b>Totales</b></td>
                                <td class="text-end"><b>{{ number_format($totals['capital'], 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($totals['interes'], 2) }}</b></td>
                                @if($tieneExc)
                                    <td class="text-end"><b>{{ number_format($totals['excedente'], 2) }}</b></td>
                                @endif
                                <td class="text-end"><b>{{ number_format($tPagCap, 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($tPagInt, 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($tPagCap + $tPagInt + $tPagExc + $sumOtros, 2) }}</b></td>
                                <td class="text-end"><b>{{ number_format($tSaldoCol, 2) }}</b></td>
                                {{-- Total Mora: pagada (negro) y exonerada (rojo) juntas; la
                                     exonerada es informativa, NO suma a los demás totales --}}
                                @php
                                    $itemsMoraTotal = collect($rows)->where('mora', '>', 0)
                                        ->flatMap(fn ($r) => $moraPagadaCuotas[$r['installment_id']]
                                            ?? [['num' => $r['n'], 'monto' => $r['mora'], 'dias' => null]])
                                        ->groupBy('num')
                                        ->map(fn ($g, $num) => ['num' => (int) $num, 'monto' => $g->sum('monto'), 'dias' => $g->sum('dias') ?: null])
                                        ->sortBy('num')->values();
                                    $tipMoraPagTotal = 'Mora pagada de '.$itemsMoraTotal->count().' cuota(s):<br>'
                                        .$itemsMoraTotal->take(15)->map(fn ($it) =>
                                            'Cuota '.$it['num'].': '.number_format($it['monto'], 2)
                                            .($it['dias'] ? ' - D. '.$it['dias'] : ''))->implode('<br>')
                                        .($itemsMoraTotal->count() > 15 ? '<br>…' : '')
                                        .($sumOtrosMora > 0 ? '<br>Sin cuota: '.number_format($sumOtrosMora, 2) : '')
                                        .'<br>Total: '.number_format($moraGlobal, 2)
                                        .($itemsMoraTotal->sum('dias') ? ' - D. '.$itemsMoraTotal->sum('dias') : '');
                                @endphp
                                <td class="text-end" style="white-space:nowrap;">
                                    @if($moraGlobal > 0)
                                        <b><span data-bs-toggle="tooltip" data-bs-html="true" title="{{ $tipMoraPagTotal }}" style="cursor:help;">{{ number_format($moraGlobal, 2) }}</span></b>
                                    @endif
                                    @if($exonGlobal > 0)
                                        @if($moraGlobal > 0)<br>@endif
                                        <b><span class="text-danger" data-bs-toggle="tooltip" title="Mora exonerada" style="cursor:help;">{{ number_format($exonGlobal, 2) }}@if($exonDiasGlobal > 0) - D. {{ $exonDiasGlobal }}@endif</span></b>
                                    @endif
                                    @if($moraGlobal <= 0 && $exonGlobal <= 0)
                                        <b>0.00</b>
                                    @endif
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Retraso (homologado con /payments/create): lo impago SOLO
                                 de las cuotas vencidas, alineado bajo la columna Saldo --}}
                            @php
                                $vencidasRetraso = collect($rows)
                                    ->filter(fn ($r) => ! $r['flag_pagado'] && $r['periodo'] !== '' && $r['periodo'] < $hoy);
                                $tRetraso = $vencidasRetraso->sum('saldo');
                                $nVencRetraso = $vencidasRetraso->count();
                                $montoCuotaVenc = (float) ($vencidasRetraso->first()['total'] ?? 0);
                                $tipRetraso = abs($montoCuotaVenc * $nVencRetraso - $tRetraso) < 0.01
                                    ? 'Cuota S/ '.number_format($montoCuotaVenc, 2).' × '.$nVencRetraso.' cuota(s) vencida(s) = S/ '.number_format($tRetraso, 2)
                                    : $nVencRetraso.' cuota(s) vencida(s) con saldo pendiente = S/ '.number_format($tRetraso, 2);
                            @endphp
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-end text-danger"
                                    data-bs-toggle="tooltip" title="{{ $tipRetraso }}" style="cursor:help;"><b>Retraso</b></td>
                                <td class="text-end text-danger" data-bs-toggle="tooltip" title="{{ $tipRetraso }}" style="cursor:help;"><b>{{ number_format($tRetraso, 2) }}</b></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Total recibido: solo aporta cuando hay mora (pagos + mora) --}}
                            @if($moraGlobal > 0)
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-center"><b>Total pagado + mora</b></td>
                                    <td colspan="2" class="text-center">
                                        <b>{{ number_format($totalGeneral, 2) }}</b>
                                    </td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endif
                            {{-- Saldo = capital + interés − pagado (la mora se cobra aparte) --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-center" style="color:red;"><b>Saldo</b></td>
                                <td colspan="2" class="text-center" style="color:red;">
                                    <b>{{ number_format(abs($saldo), 2) }}</b>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Capital pendiente total (misma fórmula que /payments/create) --}}
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-center" style="color:red;"><b>Capital pendiente total</b></td>
                                <td colspan="2" class="text-center" style="color:red;">
                                    <b>{{ number_format($capPendienteTotal, 2) }}</b>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            {{-- Interés pendiente total: interés del cronograma − pagado --}}
                            @php
                                $intPendienteTotal = round($totals['interes'] - $tPagInt, 2);
                                $tipIntPend = 'Interés '.number_format($totals['interes'], 2).' − pagado '.number_format($tPagInt, 2).' = '.number_format($intPendienteTotal, 2);
                            @endphp
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="{{ $tieneExc ? 8 : 7 }}" class="text-center" style="color:red;"
                                    data-bs-toggle="tooltip" title="{{ $tipIntPend }}"><b style="cursor:help;">Interés pendiente total</b></td>
                                <td colspan="2" class="text-center" style="color:red;"
                                    data-bs-toggle="tooltip" title="{{ $tipIntPend }}">
                                    <b style="cursor:help;">{{ number_format($intPendienteTotal, 2) }}</b>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal del recibo (homologado con /payments/create): iframe del recibo
         público en esta misma ventana. allow=clipboard-write: sin eso el botón
         "Copiar imagen" del recibo no puede escribir al portapapeles. --}}
    <div class="modal fade" id="modal-recibo" tabindex="-1" wire:ignore>
        <div class="modal-dialog modal-dialog-centered" style="max-width:430px;">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Recibo</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="iframe-recibo" src="about:blank" allow="clipboard-write"
                            style="width:100%; height:75vh; border:0;"></iframe>
                </div>
            </div>
        </div>
    </div>
    <script>
        function abrirRecibo(url) {
            document.getElementById('iframe-recibo').src = url;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-recibo')).show();
        }
        // Al cerrar se descarga el iframe: no queda el recibo cargado de fondo
        document.getElementById('modal-recibo').addEventListener('hidden.bs.modal', function () {
            document.getElementById('iframe-recibo').src = 'about:blank';
        });
    </script>
</div>
