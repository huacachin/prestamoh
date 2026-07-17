<li class="header-notification" wire:poll.300s>
    <div class="flex-shrink-0 app-dropdown">
        <a href="#" class="d-block head-icon position-relative"
           data-bs-toggle="dropdown"
           data-bs-auto-close="outside" aria-expanded="false"
           title="Compromisos de pago">
            <i class="ti ti-bell"></i>
            @if($rojos + $naranjas > 0)
                <span class="comp-badge {{ $rojos > 0 ? 'comp-badge--rojo' : 'comp-badge--naranja' }}">
                    {{ $rojos + $naranjas }}
                </span>
            @endif
        </a>

        <div class="dropdown-menu dropdown-menu-end bg-transparent border-0 p-0">
            <div class="comp-panel">
                {{-- Cabecera --}}
                <div class="comp-head">
                    <div>
                        <div class="comp-head-title"><i class="ti ti-calendar-dollar"></i> Compromisos de pago</div>
                        <div class="comp-head-sub">Clientes por cobrar estos días</div>
                    </div>
                    <div class="comp-head-chips">
                        @if($rojos > 0)<span class="comp-chip comp-chip--rojo">{{ $rojos }} hoy/vencidos</span>@endif
                        @if($naranjas > 0)<span class="comp-chip comp-chip--naranja">{{ $naranjas }} próximos</span>@endif
                    </div>
                </div>

                {{-- Lista --}}
                <div class="comp-list">
                    @if($compromisos->isEmpty())
                        <div class="comp-empty">
                            <i class="ti ti-calendar-check"></i>
                            <div class="fw-semibold">Sin compromisos próximos</div>
                            <div class="text-secondary" style="font-size: 12px;">Nada por cobrar en los próximos 2 días.</div>
                        </div>
                    @else
                        @foreach($compromisos as $comp)
                            <div class="comp-item comp-item--{{ $comp->estado }}">
                                <div class="comp-avatar comp-avatar--{{ $comp->estado }}">
                                    {{ mb_strtoupper(mb_substr($comp->apellido_pat ?: $comp->nombre, 0, 1).mb_substr($comp->nombre, 0, 1)) }}
                                </div>
                                <div class="comp-info">
                                    <a href="{{ route('clients.index', ['documento' => $comp->documento]) }}" class="comp-nombre" title="{{ $comp->cliente }}">
                                        {{ $comp->cliente }}
                                    </a>
                                    <div class="comp-fecha comp-fecha--{{ $comp->estado }}">
                                        @if($comp->dias < 0)
                                            <i class="ti ti-alert-triangle"></i> Venció el {{ \Carbon\Carbon::parse($comp->compromiso_fecha)->format('d/m') }}
                                            · hace {{ abs($comp->dias) }} día{{ abs($comp->dias) === 1 ? '' : 's' }}
                                        @elseif($comp->dias === 0)
                                            <i class="ti ti-cash"></i> Paga HOY
                                        @else
                                            <i class="ti ti-clock"></i> Paga el {{ \Carbon\Carbon::parse($comp->compromiso_fecha)->format('d/m') }}
                                            · en {{ $comp->dias }} día{{ $comp->dias === 1 ? '' : 's' }}
                                        @endif
                                    </div>
                                    @if($comp->compromiso_detalle)
                                        <div class="comp-detalle" title="{{ $comp->compromiso_detalle }}">{{ $comp->compromiso_detalle }}</div>
                                    @endif
                                </div>
                                <button type="button" class="comp-check"
                                        wire:click="marcarCumplido({{ $comp->id }})"
                                        title="Marcar compromiso cumplido">
                                    <i class="ti ti-check"></i>
                                </button>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Pie --}}
                <a href="{{ route('clients.index', ['estado' => 'rojo']) }}" class="comp-foot">
                    Ver clientes con 3+ cuotas vencidas <i class="ti ti-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <style>
        /* Badge contador sobre la campana */
        .comp-badge {
            position: absolute; top: 0; right: -4px;
            min-width: 17px; height: 17px; padding: 0 4px;
            border-radius: 9px; font-size: 10px; font-weight: 700; line-height: 17px;
            text-align: center; color: #fff; box-shadow: 0 0 0 2px #fff;
        }
        .comp-badge--rojo { background: #dc3545; }
        .comp-badge--naranja { background: #fd7e14; }

        .comp-panel {
            width: 360px; max-width: 92vw;
            background: #fff; border-radius: 12px; overflow: hidden;
            box-shadow: 0 12px 32px rgba(16, 24, 40, .18);
        }

        .comp-head {
            display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
            padding: 12px 14px; background: linear-gradient(135deg, #2c3644, #3a4757); color: #fff;
        }
        .comp-head-title { font-size: 13px; font-weight: 700; }
        .comp-head-sub { font-size: 11px; color: #b7c0cd; }
        .comp-head-chips { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; }
        .comp-chip {
            font-size: 10px; font-weight: 700; padding: 1px 8px; border-radius: 10px; white-space: nowrap;
        }
        .comp-chip--rojo { background: #dc3545; color: #fff; }
        .comp-chip--naranja { background: #fd7e14; color: #fff; }

        .comp-list { max-height: 380px; overflow-y: auto; }

        .comp-empty { text-align: center; padding: 28px 16px; }
        .comp-empty .ti { font-size: 34px; color: #2eb85c; display: block; margin-bottom: 6px; }

        .comp-item {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 10px 14px; border-bottom: 1px solid #f0f2f5;
            border-left: 3px solid transparent; transition: background .12s ease;
        }
        .comp-item:hover { background: #f7f9fb; }
        .comp-item--rojo { border-left-color: #dc3545; }
        .comp-item--naranja { border-left-color: #fd7e14; }

        .comp-avatar {
            width: 32px; height: 32px; border-radius: 50%; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #fff;
        }
        .comp-avatar--rojo { background: #dc3545; }
        .comp-avatar--naranja { background: #fd7e14; }

        .comp-info { flex: 1 1 auto; min-width: 0; }
        .comp-nombre {
            display: block; font-size: 12.5px; font-weight: 600; color: #212529;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none;
        }
        .comp-nombre:hover { color: #0d6efd; }
        .comp-fecha { font-size: 11px; font-weight: 600; margin-top: 1px; }
        .comp-fecha--rojo { color: #dc3545; }
        .comp-fecha--naranja { color: #b35a00; }
        .comp-detalle {
            font-size: 11px; color: #6c757d; margin-top: 1px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .comp-check {
            flex: 0 0 auto; width: 26px; height: 26px; margin-top: 3px;
            border: 1px solid #d6dbe1; border-radius: 50%; background: #fff; color: #98a2b3;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
            cursor: pointer; transition: all .12s ease;
        }
        .comp-check:hover { background: #2eb85c; border-color: #2eb85c; color: #fff; transform: scale(1.08); }

        .comp-foot {
            display: block; text-align: center; padding: 9px 12px;
            font-size: 11.5px; font-weight: 600; color: #445; text-decoration: none;
            background: #f7f8fa; border-top: 1px solid #eceff3;
        }
        .comp-foot:hover { background: #eef1f5; color: #0d6efd; }
    </style>
</li>
