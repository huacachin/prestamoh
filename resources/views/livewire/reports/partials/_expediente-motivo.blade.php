{{-- 30/10 (Antony): N° de expediente al inicio del motivo, sutil (texto chico y gris, sin etiqueta);
     con permiso de créditos abre el crédito en otra pestaña. Reporte de pagos y Caja General 1. --}}
@if(($expediente ?? '') !== '')
    @if($puedeVerCredito && ! empty($creditId))
        <a href="{{ route('credits.show', $creditId) }}" target="_blank" rel="noopener" class="exp-motivo"
           title="N° de expediente del cliente · ver el crédito #{{ $creditId }}">Exp. {{ $expediente }}</a>
    @else
        <span class="exp-motivo" title="N° de expediente del cliente">Exp. {{ $expediente }}</span>
    @endif
    <span class="exp-motivo-sep">·</span>
@endif
@once
    <style>
        .exp-motivo { font-size: .8em; color: #8a929a; text-decoration: none; }
        a.exp-motivo:hover { color: #0d6efd; text-decoration: underline; }
        .exp-motivo-sep { color: #b0b6bc; }
    </style>
@endonce
