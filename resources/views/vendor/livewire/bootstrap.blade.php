@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

{{-- Paginación homologada al tema (estilo light-pagination del template):
     pastillas sin borde con fondo suave, activa sólida en color primario.
     OJO: wrapper <div>, nunca <nav> — customizer.js convierte todo <nav>
     en sidebar fijo y lo saca de la pantalla. --}}
<div>
    @if ($paginator->hasPages())
        <div class="lw-pager">
            <span class="lw-pager-info">
                Mostrando <b>{{ $paginator->firstItem() }}</b>–<b>{{ $paginator->lastItem() }}</b> de <b>{{ $paginator->total() }}</b> registros
            </span>

            <ul class="lw-pager-list">
                {{-- Anterior --}}
                @if ($paginator->onFirstPage())
                    <li><span class="lw-page is-nav is-disabled" aria-hidden="true"><i class="ti ti-chevron-left"></i></span></li>
                @else
                    <li>
                        <button type="button" class="lw-page is-nav" title="Anterior"
                                dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                wire:loading.attr="disabled">
                            <i class="ti ti-chevron-left"></i>
                        </button>
                    </li>
                @endif

                {{-- Páginas --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="lw-page is-dots">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" aria-current="page">
                                    <span class="lw-page is-active">{{ $page }}</span>
                                </li>
                            @else
                                <li wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}">
                                    <button type="button" class="lw-page"
                                            wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                            x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ $page }}</button>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Siguiente --}}
                @if ($paginator->hasMorePages())
                    <li>
                        <button type="button" class="lw-page is-nav" title="Siguiente"
                                dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                wire:loading.attr="disabled">
                            <i class="ti ti-chevron-right"></i>
                        </button>
                    </li>
                @else
                    <li><span class="lw-page is-nav is-disabled" aria-hidden="true"><i class="ti ti-chevron-right"></i></span></li>
                @endif
            </ul>
        </div>
    @endif
</div>
