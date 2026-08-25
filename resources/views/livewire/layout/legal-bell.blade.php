<li class="header-notification legal-bell" wire:poll.300s>
    <div class="flex-shrink-0 app-dropdown">
        <a href="#" class="d-block head-icon position-relative"
           data-bs-toggle="dropdown"
           data-bs-auto-close="outside" aria-expanded="false"
           title="Alertas legales">
            <i class="ti ti-gavel"></i>
            @if($rojos + $naranjas > 0)
                <span class="badge rounded-pill {{ $rojos > 0 ? 'bg-danger' : 'bg-warning text-dark' }} position-absolute"
                      style="top: 1px; right: -3px; font-size: 9px; padding: 2px 5px;">
                    {{ $rojos + $naranjas }}
                </span>
            @endif
        </a>

        <div class="dropdown-menu dropdown-menu-end bg-transparent border-0">
            <div class="card mb-0">
                <div class="card-header bg-primary">
                    <h5 class="text-white">
                        Alertas legales
                        <span class="float-end"><i class="ti ti-scale text-white"></i></span>
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 360px; overflow-y: auto;">
                    @if($items->isEmpty())
                        <div class="hidden-massage py-4 px-3 text-center">
                            <div>
                                <i class="ti ti-shield-check f-s-28 text-success"></i>
                                <h6 class="mb-0 mt-1">Sin alertas legales</h6>
                                <p class="text-secondary f-s-12 mb-0">Ninguna renovación SIGM próxima ni trámite notarial varado.</p>
                            </div>
                        </div>
                    @else
                        @foreach($items as $item)
                            <a href="{{ $item->url }}"
                               class="d-flex align-items-start gap-2 px-3 py-2 border-bottom text-decoration-none legal-row legal-row--{{ $item->clase }}">
                                <i class="ti {{ $item->icono }} f-s-18 flex-shrink-0 mt-1 {{ $item->clase === 'rojo' ? 'text-danger' : 'text-warning' }}"></i>
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <span class="d-block text-truncate fw-semibold f-s-13 text-dark"
                                          title="{{ $item->titulo }}">
                                        {{ $item->titulo }}
                                    </span>
                                    <span class="d-block f-s-11 fw-semibold {{ $item->clase === 'rojo' ? 'text-danger' : 'text-warning' }}">
                                        {{ $item->detalle }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>
                @if($total > $items->count())
                    <div class="card-footer p-0">
                        <a href="{{ route('legal.notaria') }}"
                           class="d-block text-center py-2 f-s-12 fw-semibold text-decoration-none">
                            y {{ $total - $items->count() }} más… ver tablero <i class="ti ti-arrow-right"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        /* Fondo blanco: el tema tiñe el card del dropdown con su celeste y el
           contenido se pierde — se fuerza contraste blanco (solo esta campana). */
        .legal-bell .app-dropdown .card,
        .legal-bell .app-dropdown .card .card-body { background: #fff !important; }
        /* Card cuadrado como el resto del header (ver compromisos-bell) */
        .legal-bell .app-dropdown .card { overflow: hidden; }
        .legal-bell .app-dropdown .card .card-header { border-radius: 0 !important; }
        .legal-bell .app-dropdown .card .card-footer { background: #f7f8fa !important; }
        .legal-bell .app-dropdown .card .card-footer a { color: #445; }
        .legal-bell .app-dropdown .card .card-footer a:hover { color: #0d6efd; }

        /* Borde lateral de urgencia, mismo look que la campana de compromisos */
        .legal-row { border-left: 3px solid transparent; transition: background .12s ease; }
        .legal-row:hover { background: #f5f7fa; }
        .legal-row--rojo { border-left-color: #dc3545; }
        .legal-row--naranja { border-left-color: #fd7e14; }
    </style>
</li>
