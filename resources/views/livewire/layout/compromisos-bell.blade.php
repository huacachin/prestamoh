<li class="header-notification" wire:poll.300s>
    <div class="flex-shrink-0 app-dropdown">
        <a href="#" class="d-block head-icon position-relative"
           data-bs-toggle="dropdown"
           data-bs-auto-close="outside" aria-expanded="false"
           title="Compromisos de pago">
            <i class="ti ti-bell"></i>
            @if($rojos + $naranjas > 0)
                <span class="position-absolute badge rounded-pill {{ $rojos > 0 ? 'bg-danger' : 'bg-warning text-dark' }}"
                      style="top: 2px; right: -2px; font-size: 9px; padding: 2px 5px;">
                    {{ $rojos + $naranjas }}
                </span>
            @endif
        </a>

        <div class="dropdown-menu dropdown-menu-end bg-transparent border-0">
            <div class="card" style="min-width: 330px;">
                <div class="card-header bg-primary">
                    <h5 class="text-white">
                        Compromisos de pago
                        <span class="float-end"><i class="ti ti-bell text-white"></i></span>
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                    @if($compromisos->isEmpty())
                        <div class="hidden-massage py-4 px-3 text-center">
                            <div>
                                <h6 class="mb-0">Sin compromisos próximos</h6>
                                <p class="text-secondary mb-0">Nada por cobrar en los próximos 2 días.</p>
                            </div>
                        </div>
                    @else
                        @foreach($compromisos as $comp)
                            <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                                <span class="mt-1 rounded-circle flex-shrink-0"
                                      style="width: 10px; height: 10px; background: {{ $comp->estado === 'rojo' ? '#dc3545' : '#fd7e14' }};"></span>
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <a href="{{ route('clients.index', ['documento' => $comp->documento]) }}"
                                       class="fw-semibold d-block text-truncate" style="font-size: 12px; color: inherit;">
                                        {{ $comp->cliente }}
                                    </a>
                                    <div style="font-size: 11px;" class="{{ $comp->estado === 'rojo' ? 'text-danger fw-semibold' : 'text-warning-emphasis' }}">
                                        <i class="ti ti-calendar-event"></i>
                                        {{ \Carbon\Carbon::parse($comp->compromiso_fecha)->format('d/m/Y') }}
                                        —
                                        @if($comp->dias < 0) venció hace {{ abs($comp->dias) }} día{{ abs($comp->dias) === 1 ? '' : 's' }}
                                        @elseif($comp->dias === 0) paga HOY
                                        @else en {{ $comp->dias }} día{{ $comp->dias === 1 ? '' : 's' }}
                                        @endif
                                    </div>
                                    @if($comp->compromiso_detalle)
                                        <div class="text-secondary text-truncate" style="font-size: 11px;" title="{{ $comp->compromiso_detalle }}">
                                            {{ $comp->compromiso_detalle }}
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-success flex-shrink-0"
                                        style="padding: 1px 6px; font-size: 10px;"
                                        wire:click="marcarCumplido({{ $comp->id }})"
                                        title="Marcar compromiso cumplido">
                                    <i class="ti ti-check"></i>
                                </button>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</li>
