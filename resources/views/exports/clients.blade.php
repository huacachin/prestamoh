@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;"';
@endphp

@section('content')
    @if($esCesado)
        {{-- clienteex_x.php (9 cols) --}}
        <center><font color="red"><b>CLIENTES CESADOS</b></font></center>
        <table border="1" cellspacing="0">
            <thead>
                <tr>
                    <th {!! $hd !!}>Item</th>
                    <th {!! $hd !!}>Fecha</th>
                    <th {!! $hd !!}>Usuario</th>
                    <th {!! $hd !!}>Exp.</th>
                    <th {!! $hd !!}>Nombres Apellidos</th>
                    <th {!! $hd !!}>DNI</th>
                    <th {!! $hd !!}>Movil</th>
                    <th {!! $hd !!}>Telefono</th>
                    <th {!! $hd !!}>Asesor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $i => $c)
                    @php
                        $nombre = trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? ''));
                        $fecha = $c->fecha_registro ? \Illuminate\Support\Carbon::parse($c->fecha_registro)->format('Y-m-d') : '';
                        $asesor = $c->asesor?->username ?? $c->asesor?->name ?? '';
                        $hasCredit = isset($clientsWithCredit[$c->id]);
                        $rowColor = $hasCredit ? null : '#dc3545';
                    @endphp
                    <tr @if($rowColor) style="color:{{ $rowColor }};" @endif>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $i + 1 }}</font>@else{{ $i + 1 }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $fecha }}</font>@else{{ $fecha }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->usuario }}</font>@else{{ $c->usuario }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->expediente }}</font>@else{{ $c->expediente }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $nombre }}</font>@else{{ $nombre }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $c->documento }}</font>@else{{ $c->documento }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $c->celular1 }}</font>@else{{ $c->celular1 }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $c->celular2 }}</font>@else{{ $c->celular2 }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $asesor }}</font>@else{{ $asesor }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        {{-- clienteex.php (11 cols) --}}
        <center><font color="red"><b>CLIENTES</b></font></center>
        <table cellspacing="0" border="1">
            <thead>
                <tr>
                    <th {!! $hd !!}>N&deg;</th>
                    <th {!! $hd !!}>Fecha</th>
                    <th {!! $hd !!}>Usuario</th>
                    <th {!! $hd !!}>Exp.</th>
                    <th {!! $hd !!}>Nombres Apellidos</th>
                    <th {!! $hd !!}>DNI</th>
                    <th {!! $hd !!}>Movil</th>
                    <th {!! $hd !!}>T.Credito</th>
                    <th {!! $hd !!}>Giro</th>
                    <th {!! $hd !!}>Asesor</th>
                    <th {!! $hd !!}>Direccion</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $i => $c)
                    @php
                        $nombre = trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? ''));
                        $fecha = $c->fecha_registro ? \Illuminate\Support\Carbon::parse($c->fecha_registro)->format('Y-m-d') : '';
                        $asesor = $c->asesor?->username ?? $c->asesor?->name ?? '';
                        $hasCredit = isset($clientsWithCredit[$c->id]);
                        $rowColor = $hasCredit ? null : '#dc3545';
                    @endphp
                    <tr @if($rowColor) style="color:{{ $rowColor }};" @endif>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $i + 1 }}</font>@else{{ $i + 1 }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $fecha }}</font>@else{{ $fecha }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->usuario }}</font>@else{{ $c->usuario }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->expediente }}</font>@else{{ $c->expediente }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $nombre }}</font>@else{{ $nombre }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $c->documento }}</font>@else{{ $c->documento }}@endif</td>
                        <td {!! $cell !!} class="txt">@if($rowColor)<font color="{{ $rowColor }}">{{ $c->celular1 }}</font>@else{{ $c->celular1 }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->zona }}</font>@else{{ $c->zona }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->giro }}</font>@else{{ $c->giro }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $asesor }}</font>@else{{ $asesor }}@endif</td>
                        <td {!! $cell !!}>@if($rowColor)<font color="{{ $rowColor }}">{{ $c->direccion }}</font>@else{{ $c->direccion }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
