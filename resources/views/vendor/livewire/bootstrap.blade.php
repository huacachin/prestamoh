@php
// 25/09: al cambiar de página se vuelve al inicio de la LISTA (el envoltorio
// marcado con data-lista) o, si el módulo no lo marcó, a la raíz del
// componente — nunca al inicio del body, que obligaba a bajar de nuevo
// pasando los filtros. El desfase por la cabecera fija lo pone el CSS
// (scroll-margin-top en [data-lista] y [wire:id]). scrollTo => false no
// desplaza; un selector propio lo respeta.
if (! isset($scrollTo)) {
    $scrollTo = '[data-lista]';
}

// Solo se desplaza si el inicio de la lista NO está a la vista (quedó por
// encima, tapado por la cabecera fija): es el caso del paginador de abajo.
// Desde el paginador de arriba la lista ya se ve y la página no se mueve
// (26/09: antes bajaba en cada clic y escondía el título y los filtros).
// Además, las tablas con scroll propio (max-height + overflow) conservan su
// scrollTop tras el morph de Livewire: se devuelven arriba para que la
// página nueva se vea desde su primera fila.
$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? sprintf(
        "let l = \$el.closest('%1\$s') || document.querySelector('%1\$s') || \$el.closest('[wire\\\\:id]') || document.body; if (l.getBoundingClientRect().top < (parseFloat(getComputedStyle(l).scrollMarginTop) || 0)) l.scrollIntoView(); l.querySelectorAll('.table-responsive, [style*=overflow]').forEach(e => e.scrollTop = 0)",
        $scrollTo
    )
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
