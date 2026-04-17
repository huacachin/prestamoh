@props(['paginator'])

@php
    $current = $paginator->currentPage();
    $last    = $paginator->lastPage();
    $delta   = 2; // páginas a mostrar a cada lado del actual

    $pages = [];
    $rangeStart = max(2, $current - $delta);
    $rangeEnd   = min($last - 1, $current + $delta);

    // Siempre mostrar primera página
    $pages[] = 1;

    // Gap inicial
    if ($rangeStart > 2) {
        $pages[] = '...';
    }

    // Páginas alrededor del actual
    for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
        $pages[] = $i;
    }

    // Gap final
    if ($rangeEnd < $last - 1) {
        $pages[] = '...';
    }

    // Última página (si hay más de 1)
    if ($last > 1) {
        $pages[] = $last;
    }
@endphp

<div class="mt-2 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <small class="text-muted">
        Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }} de {{ $paginator->total() }} registros |
        Página {{ $current }} de {{ $last }}
    </small>

    @if($last > 1)
        <div class="btn-group btn-group-sm flex-wrap">
            <button class="btn btn-outline-primary {{ $paginator->onFirstPage() ? 'disabled' : '' }}"
                    wire:click="previousPage" {{ $paginator->onFirstPage() ? 'disabled' : '' }}>
                « Anterior
            </button>

            @foreach($pages as $page)
                @if($page === '...')
                    <button class="btn btn-outline-secondary disabled" disabled>...</button>
                @else
                    <button class="btn {{ $page == $current ? 'btn-primary' : 'btn-outline-primary' }}"
                            wire:click="gotoPage({{ $page }})">{{ $page }}</button>
                @endif
            @endforeach

            <button class="btn btn-outline-primary {{ !$paginator->hasMorePages() ? 'disabled' : '' }}"
                    wire:click="nextPage" {{ !$paginator->hasMorePages() ? 'disabled' : '' }}>
                Siguiente »
            </button>
        </div>
    @endif
</div>
