{{-- 30/10 (Antony): etiqueta sutil (texto chico y gris, sin rótulo aparte) al inicio del motivo; con permiso de
     créditos abre el crédito en otra pestaña. Reporte de pagos: el código del crédito ("no va el número de
     expediente, sino el código de crédito"); Caja General 1: el N° de expediente (allí el código del crédito ya
     está en la columna CDG). --}}
@if(($etiqueta ?? '') !== '')
    @if($puedeVerCredito && ! empty($creditId))
        <a href="{{ route('credits.show', $creditId) }}" target="_blank" rel="noopener" class="exp-motivo"
           title="{{ $titulo }} · ver el crédito #{{ $creditId }}">{{ $etiqueta }}</a>
    @else
        <span class="exp-motivo" title="{{ $titulo }}">{{ $etiqueta }}</span>
    @endif
    <span class="exp-motivo-sep">·</span>
@endif
@once
    <style>
        .exp-motivo { font-size: .8em; color: #5c656e; text-decoration: none; }
        a.exp-motivo:hover { color: #0d6efd; text-decoration: underline; }
        .exp-motivo-sep { color: #8a929a; }
    </style>
@endonce
