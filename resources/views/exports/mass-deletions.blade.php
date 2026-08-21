@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;"';
@endphp

@section('content')
    <center><b>ELIMINAR MASIVO</b></center>
    <table width="800" cellspacing="0" border="1">
        <thead>
            <tr>
                <th {!! $hd !!} width="8%">N&ordm;</th>
                <th {!! $hd !!} width="13%">Fecha</th>
                <th {!! $hd !!} width="5%">Hora</th>
                <th {!! $hd !!}>Usuario</th>
                <th {!! $hd !!}>Asesor</th>
                <th {!! $hd !!} width="15%">Cliente</th>
                <th {!! $hd !!} width="10%">Codigo</th>
                <th {!! $hd !!} width="10%">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $i => $r)
                @php
                    $cli = $r->credit?->client;
                    $cliente = $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : '';
                @endphp
                <tr>
                    <td {!! $cell !!}><b>&nbsp;&nbsp;{{ $i + 1 }}</b></td>
                    <td {!! $cell !!}>{{ $r->date?->format('d/m/Y') }}</td>
                    <td {!! $cell !!}>{{ $r->time }}</td>
                    <td {!! $cell !!}>{{ \App\Support\Usernames::de($r->performed_by ?? $r->user) }}</td>
                    <td {!! $cell !!}>{{ \App\Support\Usernames::de($r->advisor) }}</td>
                    <td {!! $cell !!}>{{ $cliente }}</td>
                    <td {!! $cell !!}>{{ $r->credit_id }}</td>
                    <td {!! $cell !!}>{{ number_format($r->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse
        </tbody>
        <tr bgcolor="#CEE7FF">
            <td style="text-align:center;"><b>Total</b></td>
            <td></td><td></td><td></td><td></td><td></td><td></td>
            <td style="text-align:center;"><b>{{ number_format($totalSum, 2) }}</b></td>
        </tr>
    </table>
@endsection
