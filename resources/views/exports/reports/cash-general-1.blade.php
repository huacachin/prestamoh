@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align: middle;"';
    $tcLabels = [1 => 'S', 3 => 'M', 4 => 'D'];
@endphp

@section('content')
    <center><font color="red"><b>REPORTE GENERAL CAJA 1</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="4">N&deg;</th>
                <th {!! $hd !!} colspan="8">INGRESOS</th>
                <th {!! $hd !!} rowspan="4">ASES.</th>
                <th {!! $hd !!} rowspan="4">T.C.</th>
                <th {!! $hd !!} colspan="5">EGRESOS</th>
                <th {!! $hd !!} rowspan="4">ADM.</th>
                <th {!! $hd !!} rowspan="4">ASES.</th>
                <th {!! $hd !!} rowspan="4">T.C.</th>
            </tr>
            <tr>
                <th {!! $hd !!} rowspan="3">CDG</th>
                <th {!! $hd !!} rowspan="3">CLIENTE</th>
                <th {!! $hd !!} rowspan="3">DETALLE</th>
                <th {!! $hd !!} rowspan="3">N&deg; DE CUOTAS</th>
                <th {!! $hd !!} colspan="4">CUOTAS</th>
                <th {!! $hd !!} rowspan="3">CDG</th>
                <th {!! $hd !!} rowspan="3">CLIENTE</th>
                <th {!! $hd !!} rowspan="3">MONTO</th>
                <th {!! $hd !!} colspan="2" rowspan="3">%</th>
            </tr>
            <tr>
                <th {!! $hd !!} rowspan="2">TOTAL</th>
                <th {!! $hd !!} rowspan="2">CAPITAL</th>
                <th {!! $hd !!} rowspan="2">INTERES</th>
                <th {!! $hd !!} rowspan="2">MORA</th>
            </tr>
            <tr></tr>
        </thead>
        <tbody>
            @forelse($days as $day)
                {{-- Encabezado del día --}}
                <tr>
                    <td colspan="19" align="center" style="background-color:#B0B0B0;"><b>{{ $day['date_label'] }}</b></td>
                </tr>

                @php $maxRows = max(count($day['ingresos']), count($day['egresos'])); @endphp

                @if($maxRows === 0)
                    <tr><td colspan="19"><font color="red">SIN MOVIMIENTOS</font></td></tr>
                @else
                    @for($i = 0; $i < $maxRows; $i++)
                        @php
                            $ing = $day['ingresos'][$i] ?? null;
                            $egr = $day['egresos'][$i] ?? null;
                            $pintIng = ($ing && in_array($ing['tipo_planilla'], [1, 3])) ? "style='color:red;'" : '';
                            $pintEgr = ($egr && in_array($egr['tipo_planilla'], [1, 3])) ? "style='color:red;'" : '';
                        @endphp
                        <tr>
                            <td {!! $cell !!}><b>{{ $i + 1 }}</b></td>

                            {{-- INGRESOS --}}
                            @if($ing)
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $ing['credit_id'] }}</td>
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $ing['cliente'] }}</td>
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $ing['detalle'] }}</td>
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $ing['nro_cuotas'] }}</td>
                                <td {!! $pintIng !!} {!! $cell !!}><font color="blue">{{ number_format($ing['total'], 2) }}</font></td>
                                <td {!! $pintIng !!} {!! $cell !!}><font color="blue">{{ number_format($ing['capital'], 2) }}</font></td>
                                <td {!! $pintIng !!} {!! $cell !!}><font color="blue">{{ number_format($ing['interes'], 2) }}</font></td>
                                <td {!! $pintIng !!} {!! $cell !!}><font color="blue">{{ number_format($ing['mora'], 2) }}</font></td>
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $ing['asesor'] }}</td>
                                <td {!! $pintIng !!} {!! $cell !!}>{{ $tcLabels[$ing['tipo_planilla']] ?? '' }}</td>
                            @else
                                <td {!! $cell !!} colspan="10"></td>
                            @endif

                            {{-- EGRESOS --}}
                            @if($egr)
                                <td {!! $pintEgr !!} {!! $cell !!}>{{ $egr['credit_id'] }}</td>
                                <td {!! $pintEgr !!} {!! $cell !!}>{{ $egr['cliente'] }}@if($egr['cod_rem'])<font color="red" size="1"> ({{ $egr['cod_rem'] }})</font>@endif</td>
                                <td {!! $pintEgr !!} {!! $cell !!}><font color="blue">{{ number_format($egr['monto'], 2) }}</font></td>
                                <td {!! $cell !!}><font color="red">@if((int) $egr['interes_pct'] == (float) $egr['interes_pct']){{ (int) $egr['interes_pct'] }}@else{{ $egr['interes_pct'] }}@endif</font></td>
                                <td {!! $pintEgr !!} {!! $cell !!}><font color="blue">{{ number_format($egr['interes_monto'], 2) }}</font></td>
                                <td {!! $pintEgr !!} {!! $cell !!}>{{ $egr['usuario'] }}</td>
                                <td {!! $pintEgr !!} {!! $cell !!}>{{ $egr['asesor'] }}</td>
                                <td {!! $pintEgr !!} {!! $cell !!}>{{ $tcLabels[$egr['tipo_planilla']] ?? '' }}</td>
                            @else
                                <td {!! $cell !!} colspan="8"></td>
                            @endif
                        </tr>
                    @endfor
                @endif

                {{-- SUB TOTAL del día --}}
                <tr bgcolor="#F0F0F0">
                    <td></td>
                    <td colspan="4"><b>SUB TOTAL</b></td>
                    <td><b>{{ number_format($day['sub_ingresos'], 2) }}</b></td>
                    <td><b>{{ number_format($day['sub_capital'], 2) }}</b></td>
                    <td><b>{{ number_format($day['sub_interes'], 2) }}</b></td>
                    <td><b>{{ number_format($day['sub_mora'], 2) }}</b></td>
                    <td></td><td></td><td></td><td></td>
                    <td><b>{{ number_format($day['sub_egresos'], 2) }}</b></td>
                    <td></td>
                    <td><b>{{ number_format($day['sub_egresos_interes'], 2) }}</b></td>
                    <td></td><td></td><td></td>
                </tr>
                {{-- TOTAL del día --}}
                <tr bgcolor="#CEE7FF">
                    <td></td>
                    <td colspan="5"><b>TOTAL</b></td>
                    <td></td>
                    <td style="text-align:center;"><b>{{ number_format($day['sub_ingresos'] + $day['sub_mora'], 2) }}</b></td>
                    <td colspan="11"></td>
                </tr>
            @empty
                <tr><td colspan="19" {!! $cell !!}>Sin movimientos para el periodo seleccionado</td></tr>
            @endforelse

            @if(count($days) > 0)
                {{-- Sub Total General --}}
                <tr>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}><b>Sub Total General</b></td>
                    <td {!! $cell !!}><font color="blue"><b>{{ number_format($Tcpi, 2) }}</b></font></td>
                    <td {!! $cell !!}><font color="blue"><b>{{ number_format($Tcpi2, 2) }}</b></font></td>
                    <td {!! $cell !!}><font color="blue"><b>{{ number_format($Tint, 2) }}</b></font></td>
                    <td {!! $cell !!}><font color="blue"><b>{{ number_format($Tmor4, 2) }}</b></font></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}><b>{{ number_format($toff, 2) }}</b></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}><b>{{ number_format($toff2, 2) }}</b></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                </tr>
                {{-- TOTAL GENERAL --}}
                <tr>
                    <td {!! $cell !!} colspan="5"><b><font size="2">REPORTE GENERAL </font><font color="red" size="2">CAJA 1 - </font><font size="2">TOTAL</font> <font color="red" size="2">GENERAL</font></b></td>
                    <td {!! $cell !!}><b><font color="red">{{ number_format($toff1, 2) }}</font></b></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}><font color="red"><b>{{ number_format($toff, 2) }}</b></font></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}><font color="red"><b>{{ number_format($toff2, 2) }}</b></font></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
