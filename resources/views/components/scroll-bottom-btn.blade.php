@props([
    'target' => 'final',
    'scrollable' => null,
    'title' => 'Ir al final',
])
{{-- Réplica del botón legacy `.abajo`.
     - Sin `scrollable` → scroll del DOCUMENTO al elemento id="{{ $target }}".
     - Con `scrollable` (selector CSS) → scroll INTERNO del contenedor a su fondo.
     El handler vive en layout/script.blade.php (event delegado por data-attr). --}}
@php
    $selector = $scrollable ?: ('#' . $target);
    $isContainer = $scrollable ? '1' : '0';
@endphp
<button type="button"
        data-scroll-sel="{{ $selector }}"
        data-scroll-cont="{{ $isContainer }}"
        {{ $attributes->merge(['class' => 'btn btn-sm btn-light', 'title' => $title]) }}>
    <i class="ti ti-chevron-down f-s-12"></i>
</button>
