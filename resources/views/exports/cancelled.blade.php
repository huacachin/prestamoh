@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;"';
@endphp

@section('content')
    <center><font color="red">CREDITO CANCELADO</font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="2" colspan="2" width="100">N&ordm;</th>
                <th {!! $hd !!} rowspan="2" width="80">Exp</th>
                <th {!! $hd !!} rowspan="2" width="90">Codigo</th>
                <th {!! $hd !!} rowspan="2" width="90">Dni</th>
                <th {!! $hd !!} rowspan="2" width="280">Nombre</th>
                <th {!! $hd !!} colspan="3" width="140">Capital</th>
                <th {!! $hd !!} colspan="4" width="40">Interes Ganado</th>
                <th {!! $hd !!} rowspan="2" width="100">Total</th>
                <th {!! $hd !!} colspan="3" width="80">Mora x Cob.</th>
                <th {!! $hd !!} rowspan="2">Fec/Cred</th>
                <th {!! $hd !!} rowspan="2" width="100">Fec/Venc</th>
                <th {!! $hd !!} rowspan="2" width="100">Fecha Cancelado</th>
                <th {!! $hd !!} rowspan="2" width="100">Estado</th>
                <th {!! $hd !!} rowspan="2" width="100">Ases.</th>
            </tr>
            <tr>
                <th {!! $hd !!} width="120">Capital</th>
                <th {!! $hd !!} width="120">R./ Capital</th>
                <th {!! $hd !!} width="120">Capital Neto</th>
                <th {!! $hd !!} width="80">Detalles</th>
                <th {!! $hd !!} width="50">%</th>
                <th {!! $hd !!} width="100">S/</th>
                <th {!! $hd !!} width="80">Mora</th>
                <th {!! $hd !!} width="80">S/</th>
                <th {!! $hd !!} width="50">MxD</th>
                <th {!! $hd !!} width="50">Dias</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                @php
                    $bg    = ($r['bg'] ?? '') !== '' ? "style='".$r['bg']."'" : '';
                    $stc   = "style='color:".($r['st_color'] ?? 'black').";'";
                @endphp
                <tr {!! $bg !!}>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;color:{{ $r['st_color'] ?? 'black' }};">{{ $r['tot2'] }}</td>
                    <td {!! $cell !!}>{{ $r['exp'] }}</td>
                    <td {!! $cell !!}>{{ $r['codigo'] }}</td>
                    <td class="txt" {!! $cell !!}>{{ $r['dni'] }}</td>
                    <td {!! $cell !!}>{{ $r['nombre'] }} <font color="red">{{ $r['cod_rem'] }}</font></td>
                    <td {!! $cell !!}>{{ number_format($r['capital'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['r_capital'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['capital_neto'], 2) }}</td>
                    <td {!! $cell !!}>{!! $r['detalles'] !!}</td>
                    <td {!! $cell !!}>{{ $r['interes_pct'] }}</td>
                    <td {!! $cell !!}>{{ $r['interes_s'] }}</td>
                    <td {!! $cell !!}>{{ round($r['mora'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['total'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['mxd'] > 0 ? $r['mxd'] : '' }}</td>
                    <td {!! $cell !!}>{{ $r['mora_s'] > 0 ? $r['mora_s'] : '' }}</td>
                    <td {!! $cell !!}>{{ $r['dias'] }}</td>
                    <td {!! $cell !!}>{{ $r['fec_cred'] }}</td>
                    <td {!! $cell !!}>{{ $r['fec_venc'] }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;color:{{ $r['st_color'] ?? 'black' }};">{{ $r['fec_cancel'] }}</td>
                    <td {!! $cell !!}>{{ $r['estado'] }}</td>
                    <td {!! $cell !!}>{{ $r['asesor'] }}</td>
                </tr>
            @empty
                <tr><td colspan="22" {!! $cell !!}>--</td></tr>
            @endforelse

            {{-- Total General (2 filas) --}}
            <tr bgcolor="#CEE7FF">
                <td colspan="6" rowspan="2" align="center" style="vertical-align:middle;">Total General</td>
                <td rowspan="2" align="center" style="vertical-align:middle;">{{ number_format($totals['cancecapi'] ?? 0, 2) }}</td>
                <td rowspan="2" align="center" style="vertical-align:middle;">{{ number_format($totals['canceinteg'] ?? 0, 2) }}</td>
                <td rowspan="2" align="center" style="vertical-align:middle;">{{ number_format($totals['todf1'] ?? 0, 2) }}</td>
                <td rowspan="2" colspan="2" style="vertical-align:middle;"></td>
                <td align="center" style="vertical-align:middle;">{{ number_format($totals['canceinte'] ?? 0, 2) }}</td>
                <td align="center" style="vertical-align:middle;">{{ number_format($totals['cancemora'] ?? 0, 2) }}</td>
                <td align="center" rowspan="2" style="vertical-align:middle;">{{ number_format($totals['totGP'] ?? 0, 2) }}</td>
                <td rowspan="2" style="vertical-align:middle;">{{ number_format($totals['montomorxdia'] ?? 0, 2) }}</td>
                <td rowspan="2" colspan="7" style="vertical-align:middle;"></td>
            </tr>
            <tr bgcolor="#CEE7FF">
                <td colspan="3" align="center" style="vertical-align:middle;">{{ number_format(($totals['canceinte'] ?? 0) + ($totals['cancemora'] ?? 0) + ($totals['total_gat'] ?? 0), 2) }}</td>
            </tr>

            {{-- Separador + tabla de distribucion --}}
            <tr><td colspan="20"></td></tr>
            <tr>
                <th {!! $hd !!} colspan="5">Detalle</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!}>S/</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!}>S/</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!} colspan="2">S/</th>
            </tr>
            @foreach($distribution ?? [] as $d)
                @if(($d['label'] ?? '') === 'Total')
                    <tr bgcolor="#CEE7FF">
                        <td colspan="5" style="text-align:center;vertical-align:middle;">Total</td>
                        <td style="text-align:center;vertical-align:middle;"><b>{{ $d['pct1'] }}</b></td>
                        <td style="text-align:center;vertical-align:middle;">{{ number_format($d['val1'], 2) }}</td>
                        <td style="text-align:center;vertical-align:middle;"><b>{{ $d['pct2'] }}</b></td>
                        <td style="text-align:center;vertical-align:middle;">{{ number_format($d['val2'], 2) }}</td>
                        <td style="text-align:center;vertical-align:middle;"><b>{{ $d['pct3'] }}</b></td>
                        <td style="text-align:center;vertical-align:middle;" colspan="2">{{ number_format($d['val3'], 2) }}</td>
                    </tr>
                @else
                    <tr>
                        <td colspan="5" {!! $cell !!}>{{ $d['label'] }}</td>
                        <td {!! $cell !!}><b>{{ $d['pct1'] }}</b></td>
                        <td {!! $cell !!}>{{ number_format($d['val1'], 2) }}</td>
                        <td {!! $cell !!}><b>{{ $d['pct2'] }}</b></td>
                        <td {!! $cell !!}>{{ number_format($d['val2'], 2) }}</td>
                        <td {!! $cell !!}><b>{{ $d['pct3'] }}</b></td>
                        <td {!! $cell !!} colspan="2">{{ number_format($d['val3'], 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endsection
