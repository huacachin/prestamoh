<li class="header-notification" wire:poll.300s>
    <div class="flex-shrink-0 app-dropdown">
        <a href="#" class="d-block head-icon position-relative"
           data-bs-toggle="dropdown"
           data-bs-auto-close="outside" aria-expanded="false"
           title="Compromisos de pago">
            <i class="ti ti-bell"></i>
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
                        Compromisos de pago
                        <span class="float-end"><i class="ti ti-calendar-dollar text-white"></i></span>
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 360px; overflow-y: auto;">
                    @if($compromisos->isEmpty())
                        <div class="hidden-massage py-4 px-3 text-center">
                            <div>
                                <i class="ti ti-calendar-check f-s-28 text-success"></i>
                                <h6 class="mb-0 mt-1">Sin compromisos próximos</h6>
                                <p class="text-secondary f-s-12 mb-0">Nada por cobrar en los próximos 2 días.</p>
                            </div>
                        </div>
                    @else
                        @foreach($compromisos as $comp)
                            <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom comp-row comp-row--{{ $comp->estado }}">
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <a href="{{ route('clients.index', ['documento' => $comp->documento]) }}"
                                       class="d-block text-truncate fw-semibold f-s-13 text-dark text-decoration-none"
                                       title="{{ $comp->cliente }}">
                                        {{ $comp->cliente }}
                                    </a>
                                    <div class="f-s-11 fw-semibold {{ $comp->estado === 'rojo' ? 'text-danger' : 'text-warning' }}">
                                        @if($comp->dias < 0)
                                            <i class="ti ti-alert-triangle"></i> Venció el {{ \Carbon\Carbon::parse($comp->compromiso_fecha)->format('d/m') }} · hace {{ abs($comp->dias) }}d
                                        @elseif($comp->dias === 0)
                                            <i class="ti ti-cash"></i> Paga HOY
                                        @else
                                            <i class="ti ti-clock"></i> Paga el {{ \Carbon\Carbon::parse($comp->compromiso_fecha)->format('d/m') }} · en {{ $comp->dias }}d
                                        @endif
                                    </div>
                                    @if($comp->compromiso_detalle)
                                        <div class="text-secondary f-s-11 text-truncate" title="{{ $comp->compromiso_detalle }}">
                                            {{ $comp->compromiso_detalle }}
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-xs btn-light-success icon-btn b-r-round flex-shrink-0"
                                        style="width: 24px; height: 24px; padding: 0;"
                                        wire:click="marcarCumplido({{ $comp->id }})"
                                        title="Marcar compromiso cumplido">
                                    <i class="ti ti-check f-s-14"></i>
                                </button>
                            </div>
                        @endforeach
                    @endif
                </div>
                @if($compromisos->isNotEmpty())
                    <div class="card-footer p-0">
                        <a href="{{ route('clients.index', ['estado' => 'rojo']) }}"
                           class="d-block text-center py-2 f-s-12 fw-semibold text-decoration-none">
                            Ver clientes con 3+ cuotas vencidas <i class="ti ti-arrow-right"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        /* Fondo blanco: el tema tiñe el card del dropdown con su celeste y el
           contenido se pierde — se fuerza contraste blanco (solo esta campana). */
        .header-notification .app-dropdown .card,
        .header-notification .app-dropdown .card .card-body { background: #fff !important; }
        /* Recorta el header/footer al radio del card: sin esquinas blancas arriba */
        .header-notification .app-dropdown .card { overflow: hidden; }
        .header-notification .app-dropdown .card .card-footer { background: #f7f8fa !important; }
        .header-notification .app-dropdown .card .card-footer a { color: #445; }
        .header-notification .app-dropdown .card .card-footer a:hover { color: #0d6efd; }

        /* Borde lateral de urgencia, alineado al look del sistema */
        .comp-row { border-left: 3px solid transparent; transition: background .12s ease; }
        .comp-row:hover { background: #f5f7fa; }
        .comp-row--rojo { border-left-color: #dc3545; }
        .comp-row--naranja { border-left-color: #fd7e14; }
    </style>
</li>
