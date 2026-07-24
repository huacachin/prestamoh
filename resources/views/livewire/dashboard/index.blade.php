{{-- =========================================================
     DASHBOARD · Corazón del sistema
     · PERÍODO (filtrable): capital prestado (héroe, con desglose
       nuevos/refinanciados), interés y mora cobrados.
     · CARTERA VIVA (snapshot de hoy): matriz = /reports/portfolio,
       mismos números al céntimo que el reporte de Cartera.
     Definiciones en App\Livewire\Dashboard\Index — no cambiarlas
     sin revisar allí (confirmadas con el negocio).
     Paleta (dataviz): acento #2a78d6 · bien #0ca30c · alerta #fab219
     · crítico #d03b3b · tinta secundaria #52514e.
     ========================================================= --}}
<div class="container-fluid">

    <style>
        .dashx-label {
            font-size: .72rem; font-weight: 600; letter-spacing: 1.6px;
            text-transform: uppercase; color: #52514e;
        }
        .dashx-value { font-weight: 700; color: #0b0b0b; line-height: 1.15; }
        .dashx-sub   { font-size: .8rem; color: #52514e; }

        .dashx-tile {
            border: 0; border-radius: 14px; background: #fff;
            box-shadow: 0 1px 4px rgba(11,11,11,.07);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .dashx-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(11,11,11,.12); }
        .dashx-ico {
            width: 44px; height: 44px; border-radius: 12px; flex: 0 0 44px;
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }

        .dashx-hero {
            border: 0; border-radius: 18px; position: relative; overflow: hidden;
            background: linear-gradient(135deg, #2a78d6 0%, #1d5cab 55%, #17498a 100%);
            color: #fff; box-shadow: 0 10px 28px rgba(42,120,214,.38);
        }
        .dashx-hero::before {
            content: ''; position: absolute; right: -70px; top: -70px;
            width: 260px; height: 260px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 70%);
        }
        .dashx-hero .ti-cash {
            position: absolute; right: 22px; bottom: 10px; font-size: 110px;
            color: rgba(255,255,255,.10); pointer-events: none;
        }
        .dashx-hero .dashx-label { color: rgba(255,255,255,.85); }
        .dashx-hero .hero-num {
            font-weight: 700; font-size: clamp(2.4rem, 6vw, 4.4rem);
            line-height: 1.05; letter-spacing: -1px;
            text-shadow: 0 2px 10px rgba(0,0,0,.18);
        }
        .dashx-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28);
            backdrop-filter: blur(2px); color: #fff;
            border-radius: 999px; padding: 5px 14px; font-size: .85rem;
        }
        .dashx-chip strong { font-weight: 700; }

        .dashx-meter { height: 16px; border-radius: 8px; overflow: hidden; display: flex; background: #eceae6; }
        .dashx-meter .seg-aldia { background: linear-gradient(180deg, #12b512, #0ca30c); }
        .dashx-meter .seg-gap   { width: 2px; background: #fff; }
        .dashx-meter .seg-mora  { background: linear-gradient(180deg, #db4848, #d03b3b); }

        .dashx-head {
            display: flex; align-items: center; gap: 8px; margin: 1.6rem 0 .7rem;
        }
        .dashx-head .rule { flex: 1; height: 1px; background: #e4e2dd; }

        .dashx-share { height: 6px; border-radius: 3px; background: #eceae6; overflow: hidden; margin-top: 6px; }
        .dashx-share > div { height: 100%; border-radius: 3px; }
    </style>

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PANEL DE CONTROL</h4>
        </div>
    </div>

    @php
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $fmt = fn ($n) => number_format((float) $n, 2);
        $fmt0 = fn ($n) => number_format((float) $n, 0);
    @endphp

    {{-- ══ FILTROS DEL PERÍODO ═══════════════════════════════ --}}
    <div class="card dashx-tile">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Año</label>
                    <select class="form-select form-select-sm" wire:model.live="year">
                        @foreach($anios as $a)
                            <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Mes</label>
                    <select class="form-select form-select-sm" wire:model.live="month">
                        @foreach($meses as $num => $nombre)
                            <option value="{{ $num }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Día</label>
                    <select class="form-select form-select-sm" wire:model.live="day">
                        <option value="">Todo el mes</option>
                        @for($d = 1; $d <= $diasDelMes; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <span class="badge bg-dark f-s-13 px-3 py-2">
                        <i class="ti ti-calendar"></i> {{ $etiqueta }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ HÉROE: CAPITAL PRESTADO ═══════════════════════════ --}}
    <div class="card dashx-hero mt-3" wire:loading.class="opacity-50">
        <i class="ti ti-cash"></i>
        <div class="card-body text-center py-4 position-relative">
            <div class="dashx-label">Capital prestado {{ $esDia ? 'en el día' : 'en el mes' }}</div>
            <div class="hero-num my-1">S/ {{ $fmt($capitalPrestado) }}</div>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
                <span class="dashx-chip">
                    <i class="ti ti-coins"></i>
                    Nuevos: <strong>{{ $fmt0($nuevos['n']) }}</strong> · S/ <strong>{{ $fmt($nuevos['total']) }}</strong>
                </span>
                <span class="dashx-chip">
                    <i class="ti ti-refresh"></i>
                    Refinanciados: <strong>{{ $fmt0($refis['n']) }}</strong> · S/ <strong>{{ $fmt($refis['total']) }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- ══ COBRADO EN EL PERÍODO ═════════════════════════════ --}}
    <div class="row g-3 mt-0">
        <div class="col-12 col-md-6">
            <div class="card dashx-tile h-100" wire:loading.class="opacity-50">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="dashx-ico" style="background:rgba(12,163,12,.12); color:#0ca30c;">
                        <i class="ti ti-percentage"></i>
                    </div>
                    <div>
                        <div class="dashx-label">Interés cobrado</div>
                        <div class="dashx-value" style="font-size: clamp(1.5rem, 3.2vw, 2.1rem);">
                            S/ {{ $fmt($interesCobrado) }}
                        </div>
                        <div class="dashx-sub">{{ $fmt0($nInteres) }} {{ $nInteres === 1 ? 'pago' : 'pagos' }} en el período</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card dashx-tile h-100" wire:loading.class="opacity-50">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="dashx-ico" style="background:rgba(208,59,59,.12); color:#d03b3b;">
                        <i class="ti ti-alert-triangle"></i>
                    </div>
                    <div>
                        <div class="dashx-label">Mora cobrada</div>
                        <div class="dashx-value" style="font-size: clamp(1.5rem, 3.2vw, 2.1rem);">
                            S/ {{ $fmt($moraCobrada) }}
                        </div>
                        <div class="dashx-sub">{{ $fmt0($nMora) }} {{ $nMora === 1 ? 'pago' : 'pagos' }} en el período</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ CARTERA VIVA (snapshot, matriz: /reports/portfolio) ══ --}}
    <div class="dashx-head">
        <h6 class="mb-0 dashx-label" style="font-size:.82rem;">Cartera viva</h6>
        <span class="dashx-sub">al {{ now()->format('d/m/Y') }} · no cambia con el filtro</span>
        <div class="rule"></div>
        <a href="{{ route('reports.portfolio') }}" class="btn btn-sm btn-outline-primary py-0">
            Ver Cartera <i class="ti ti-arrow-right"></i>
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="dashx-ico" style="background:rgba(42,120,214,.12); color:#2a78d6;">
                        <i class="ti ti-wallet"></i>
                    </div>
                    <div>
                        <div class="dashx-label">Saldo por cobrar</div>
                        <div class="dashx-value" style="font-size: clamp(1.35rem, 2.6vw, 1.85rem);">
                            S/ {{ $fmt($carteraTotals['saldo']) }}
                        </div>
                        <div class="dashx-sub">{{ $fmt0($carteraVigentes + $carteraVencidos) }} créditos en cartera</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="dashx-ico" style="background:rgba(12,163,12,.12); color:#0ca30c;">
                        <i class="ti ti-circle-check"></i>
                    </div>
                    <div>
                        <div class="dashx-label">Cartera al día</div>
                        <div class="dashx-value" style="font-size: clamp(1.35rem, 2.6vw, 1.85rem);">
                            S/ {{ $fmt($morosidad['activos_saldo']) }}
                        </div>
                        <div class="dashx-sub">{{ $fmt0($morosidad['activos_count']) }} créditos sin atraso</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="dashx-ico" style="background:rgba(208,59,59,.12); color:#d03b3b;">
                        <i class="ti ti-clock"></i>
                    </div>
                    <div>
                        <div class="dashx-label">Saldo en mora</div>
                        <div class="dashx-value" style="font-size: clamp(1.35rem, 2.6vw, 1.85rem);">
                            S/ {{ $fmt($morosidad['mora_saldo']) }}
                        </div>
                        <div class="dashx-sub">{{ $fmt0($morosidad['mora_count']) }} créditos vencidos con saldo</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Medidor de morosidad --}}
    @php
        $moraPct = (float) $morosidad['mora_pct'];
        $alDiaPct = max(0, 100 - $moraPct);
        $nivel = $moraPct < 10 ? ['#0ca30c', 'saludable'] : ($moraPct < 25 ? ['#c98500', 'en alerta'] : ['#d03b3b', 'crítica']);
    @endphp
    <div class="card dashx-tile mt-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-baseline mb-1 flex-wrap">
                <span class="dashx-label"><i class="ti ti-scale"></i> Morosidad de la cartera</span>
                <span class="dashx-value" style="font-size:1.45rem; color: {{ $nivel[0] }};">
                    {{ number_format($moraPct, 2) }}% <span class="dashx-sub">({{ $nivel[1] }})</span>
                </span>
            </div>
            <div class="dashx-meter" role="img"
                 aria-label="Al día {{ number_format($alDiaPct, 2) }}%, en mora {{ number_format($moraPct, 2) }}%">
                <div class="seg-aldia" style="width: {{ $alDiaPct }}%;"></div>
                <div class="seg-gap"></div>
                <div class="seg-mora" style="width: {{ $moraPct }}%;"></div>
            </div>
            <div class="d-flex justify-content-between mt-1 dashx-sub">
                <span><span style="color:#0ca30c;">●</span> Al día S/ {{ $fmt($morosidad['activos_saldo']) }}</span>
                <span><span style="color:#d03b3b;">●</span> En mora S/ {{ $fmt($morosidad['mora_saldo']) }}</span>
            </div>
        </div>
    </div>

    {{-- Distribución por planilla --}}
    @php
        $capTotalPlan = max(0.01, $tipoTotals['totsem'] + $tipoTotals['totmen'] + $tipoTotals['totdia']);
        $planillas = [
            ['Semanal', $tipoTotals['sempo'], $tipoTotals['totsem'], '#2a78d6'],
            ['Mensual', $tipoTotals['mempo'], $tipoTotals['totmen'], '#eb6834'],
            ['Diario',  $tipoTotals['dempo'], $tipoTotals['totdia'], '#1baf7a'],
        ];
    @endphp
    <div class="row g-3 mt-0 mb-3">
        @foreach($planillas as [$nombre, $n, $capital, $color])
            @php $share = round($capital * 100 / $capTotalPlan, 1); @endphp
            <div class="col-12 col-md-4">
                <div class="card dashx-tile h-100">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="dashx-label">
                                <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:{{ $color }}; margin-right:4px;"></span>{{ $nombre }}
                            </span>
                            <span class="dashx-sub">{{ $share }}% del capital</span>
                        </div>
                        <div class="dashx-value mt-1" style="font-size:1.3rem;">S/ {{ $fmt($capital) }}</div>
                        <div class="dashx-sub">{{ $fmt0($n) }} {{ $n === 1 ? 'crédito' : 'créditos' }}</div>
                        <div class="dashx-share"><div style="width: {{ $share }}%; background: {{ $color }};"></div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
