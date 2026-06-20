@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';

    // Estilo de celda diaria respetando color de fin de semana (red/green)
    $dcell = function ($color) {
        $ty = $color === 'red' ? 'color:red;' : ($color === 'green' ? 'color:green;' : '');
        return "style='border-style:dotted solid dotted solid;text-align:center;{$ty}'";
    };
@endphp

@section('content')
    <center><font color="red"><b>REPORTE DE ASESOR DE CREDITO</b></font></center>

    {{-- ─── Tabla DIARIA ─── --}}
    <table border="1" cellspacing="0" width="700px">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="2">N&deg;</th>
                <th {!! $hd !!} rowspan="2">FECHA</th>
                <th {!! $hd !!} colspan="5">CLIENTES</th>
                <th {!! $hd !!} rowspan="2">IMP. A<br>COBRAR</th>
                <th {!! $hd !!} colspan="2">COBRADOS</th>
                <th {!! $hd !!} colspan="2">NO COBRADOS</th>
            </tr>
            <tr>
                <th {!! $hd !!}>NUEVO</th>
                <th {!! $hd !!}>REN./REF.</th>
                <th {!! $hd !!}>CANC.</th>
                <th {!! $hd !!}>TOTAL</th>
                <th {!! $hd !!}>CAPITAL</th>
                <th {!! $hd !!}>CANT.</th>
                <th {!! $hd !!}>IMPORTE</th>
                <th {!! $hd !!}>CANT.</th>
                <th {!! $hd !!}>IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                @php $c = $dcell($r['color'] ?? ''); @endphp
                <tr>
                    <td {!! $c !!}>{{ $i + 1 }}</td>
                    <td {!! $c !!}>{{ \Carbon\Carbon::parse($r['fecha'])->format('d/m/Y') }}</td>
                    <td {!! $c !!}>{{ round($r['nuevo'], 0) }}</td>
                    <td {!! $c !!}>{{ round($r['renov'], 0) }}</td>
                    <td {!! $c !!}>{{ $r['canc'] }}</td>
                    <td {!! $c !!}>{{ $r['total'] }}</td>
                    <td {!! $c !!}>{{ number_format($r['capital'], 2) }}</td>
                    <td {!! $c !!}>{{ number_format($r['imp_cobrar'], 2) }}</td>
                    <td {!! $c !!}>{{ $r['cob_cnt'] }}</td>
                    <td {!! $c !!}>{{ number_format($r['cob_imp'], 2) }}</td>
                    <td {!! $c !!}>{{ number_format($r['noc_cnt'], 0) }}</td>
                    <td {!! $c !!}>{{ number_format($r['noc_imp'], 2) }}</td>
                </tr>
            @endforeach

            <tr bgcolor="#CEE7FF">
                <th colspan="2" rowspan="2" align="center">Total</th>
                <td>{{ number_format($tot['nuevo'], 2) }}</td>
                <td>{{ number_format($tot['renov'], 2) }}</td>
                <td>{{ number_format($tot['canc'], 2) }}</td>
                <td>{{ number_format($tot['total'], 2) }}</td>
                <td>{{ number_format($tot['capital'], 2) }}</td>
                <td>{{ number_format($tot['imp_cobrar'], 2) }}</td>
                <td>{{ number_format($tot['cob_cnt'], 2) }}</td>
                <td>{{ number_format($tot['cob_imp'], 2) }}</td>
                <td>{{ number_format($tot['noc_cnt'], 2) }}</td>
                <td>{{ number_format($tot['noc_imp'], 2) }}</td>
            </tr>
            <tr bgcolor="#CEE7FF">
                <td>{{ number_format($avg['nuevo'], 2) }}</td>
                <td>{{ number_format($avg['renov'], 2) }}</td>
                <td>{{ number_format($avg['canc'], 2) }}</td>
                <td>{{ number_format($avg['total'], 2) }}</td>
                <td>{{ number_format($avg['capital'], 2) }}</td>
                <td>{{ number_format($avg['imp_cobrar'], 2) }}</td>
                <td>{{ number_format($avg['cob_cnt'], 2) }}</td>
                <td>{{ number_format($avg['cob_imp'], 2) }}</td>
                <td>{{ number_format($avg['noc_cnt'], 2) }}</td>
                <td>{{ number_format($avg['noc_imp'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <br>

    {{-- ─── Tabla MENSUAL ─── --}}
    @php
        $m    = $monthlyHistory['rows'] ?? [];
        $mtot = $monthlyHistory['tot'] ?? [];
        $mavg = $monthlyHistory['avg'] ?? [];
    @endphp
    <table border="1" cellspacing="0" width="700px">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="2">N&deg;</th>
                <th {!! $hd !!} rowspan="2">MES</th>
                <th {!! $hd !!} colspan="5">CLIENTES</th>
                <th {!! $hd !!} rowspan="2">IMP. A<br>COBRAR</th>
                <th {!! $hd !!} colspan="2">COBRADOS</th>
                <th {!! $hd !!} colspan="2">NO COBRADOS</th>
            </tr>
            <tr>
                <th {!! $hd !!}>X.C.N.</th>
                <th {!! $hd !!}>X.R.C.</th>
                <th {!! $hd !!}>CANC.</th>
                <th {!! $hd !!}>TOTAL</th>
                <th {!! $hd !!}>CAPITAL</th>
                <th {!! $hd !!}>CANT.</th>
                <th {!! $hd !!}>IMPORTE</th>
                <th {!! $hd !!}>CANT.</th>
                <th {!! $hd !!}>IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($m as $row)
                <tr>
                    <td {!! $cell !!}>{{ $row['n'] }}</td>
                    <td {!! $cell !!}>{{ $row['mes'] }}</td>
                    <td {!! $cell !!}>{{ number_format($row['nuevo'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['renov'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['canc'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['total'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['capital'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['imp_cobrar'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['cob_cnt'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['cob_imp'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['noc_cnt'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($row['noc_imp'], 2) }}</td>
                </tr>
            @endforeach

            <tr bgcolor="#CEE7FF">
                <th colspan="2" rowspan="2">Totales</th>
                <td>{{ number_format($mtot['nuevo'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['renov'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['canc'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['total'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['capital'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['imp_cobrar'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['cob_cnt'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['cob_imp'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['noc_cnt'] ?? 0, 2) }}</td>
                <td>{{ number_format($mtot['noc_imp'] ?? 0, 2) }}</td>
            </tr>
            <tr bgcolor="#CEE7FF">
                <td>{{ number_format($mavg['nuevo'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['renov'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['canc'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['total'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['capital'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['imp_cobrar'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['cob_cnt'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['cob_imp'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['noc_cnt'] ?? 0, 2) }}</td>
                <td>{{ number_format($mavg['noc_imp'] ?? 0, 2) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
