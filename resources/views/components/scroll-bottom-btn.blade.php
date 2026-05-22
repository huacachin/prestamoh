@props([
    'target' => 'final',
    'title' => 'Ir al final',
])
{{-- Réplica del botón legacy `.abajo` (caja-estadistica.php L111, etc.):
     hace scroll al elemento con id="{{ $target }}" definido al final del contenedor. --}}
<button type="button"
        onclick="document.getElementById('{{ $target }}')?.scrollIntoView({behavior:'smooth', block:'end'})"
        {{ $attributes->merge(['class' => 'btn btn-sm btn-light flex-shrink-0', 'title' => $title]) }}>
    <i class="ti ti-chevron-down f-s-12"></i>
</button>
