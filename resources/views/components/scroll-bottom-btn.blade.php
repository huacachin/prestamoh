@props([
    'target' => 'final',
    'scrollable' => null,
    'title' => null,
    // 18/09: 'toggle' (el de siempre: 1er clic baja, 2do sube), 'down' o 'up'.
    // Con dirección explícita el botón hace SIEMPRE lo mismo y su ícono no
    // cambia — es lo que pidió Antony para /cash/incomes: dos botones, uno
    // para bajar y otro para subir, en vez de uno que alterna y no se entiende.
    'dir' => 'toggle',
])
{{-- Réplica del botón legacy `.abajo`.
     - Sin `scrollable` → scroll del DOCUMENTO al elemento id="{{ $target }}".
     - Con `scrollable` (selector CSS) → scroll INTERNO del contenedor.
     El handler vive en layout/script.blade.php (event delegado por data-attr). --}}
@php
    $selector = $scrollable ?: ('#'.$target);
    $isContainer = $scrollable ? '1' : '0';
    $icono = $dir === 'up' ? 'ti-chevron-up' : 'ti-chevron-down';
    $rotulo = $title ?? match ($dir) {
        'up' => 'Ir al inicio de la tabla',
        'down' => 'Ir al final de la tabla',
        default => 'Ir al final',
    };
@endphp
<button type="button"
        data-scroll-sel="{{ $selector }}"
        data-scroll-cont="{{ $isContainer }}"
        @if($dir !== 'toggle') data-scroll-dir="{{ $dir }}" @endif
        {{ $attributes->merge(['class' => 'btn btn-sm btn-light', 'title' => $rotulo]) }}>
    <i class="ti {{ $icono }} f-s-12"></i>
</button>
