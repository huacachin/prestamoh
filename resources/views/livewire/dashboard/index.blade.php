{{-- =========================================================
     DASHBOARD · Corazón del sistema
     · Bloque de PERÍODO (filtrable): capital prestado (héroe),
       interés y mora cobrados.
     · Bloque de CARTERA (snapshot de hoy): matriz = /reports/portfolio,
       mismos números al céntimo que el reporte de Cartera.
     Definiciones en App\Livewire\Dashboard\Index — no cambiarlas
     sin revisar allí (confirmadas con el negocio).
     Paleta (dataviz): acento #2a78d6 · bien #0ca30c · alerta #fab219
     · crítico #d03b3b · tinta secundaria #52514e.
     ========================================================= --}}
<div class="container-fluid">

    <style>
        .dashx-label {
            font-size: .74rem; font-weight: 600; letter-spacing: 1.6px;
            text-transform: uppercase; color: #52514e;
        }
        .dashx-value { font-weight: 700; color: #0b0b0b; line-height: 1.15; }
        .dashx-sub   { font-size: .8rem; color: #52514e; }
        .dashx-tile  {
            border: 0; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(11,11,11,.08);
            position: relative; overflow: hidden; background: #fff;
        }
        .dashx-tile .tile-accent {
            position: absolute; left: 0; top: 0; bottom: 0; width: 5px;
        }
        .dashx-hero {
            border: 0; border-radius: 14px;
            background: linear-gradient(135deg, #2a78d6 0%, #1d5cab 100%);
            color: #fff; box-shadow: 0 6px 18px rgba(42,120,214,.35);
        }
        .dashx-hero .dashx-label { color: rgba(255,255,255,.85); }
        .dashx-hero .hero-num {
            font-weight: 700; font-size: clamp(2.4rem, 6vw, 4.2rem);
            line-height: 1.05; letter-spacing: -1px;
        }
        .dashx-hero .dashx-sub { color: rgba(255,255,255,.85); }
        .dashx-meter { height: 14px; border-radius: 7px; overflow: hidden; display: flex; background: #eceae6; }
        .dashx-meter .seg-aldia { background: #0ca30c; }
        .dashx-meter .seg-gap   { width: 2px; background: #fff; }
        .dashx-meter .seg-mora  { background: #d03b3b; }
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
        <div class="card-body text-center py-4">
            <div class="dashx-label">Capital prestado {{ $esDia ? 'en el día' : 'en el mes' }}</div>
            <div class="hero-num my-1">S/ {{ $fmt($capitalPrestado) }}</div>
            <div class="dashx-sub">
                {{ $nCreditos }} {{ $nCreditos === 1 ? 'crédito activado' : 'créditos activados' }}
                · no incluye refinanciados
            </div>
        </div>
    </div>

    {{-- ══ COBRADO EN EL PERÍODO ═════════════════════════════ --}}
    <div class="row g-3 mt-0">
        <div class="col-12 col-md-6">
            <div class="card dashx-tile h-100" wire:loading.class="opacity-50">
                <div class="tile-accent" style="background:#0ca30c;"></div>
                <div class="card-body py-3 ps-4">
                    <div class="dashx-label">Interés cobrado</div>
                    <div class="dashx-value" style="font-size: clamp(1.6rem, 3.5vw, 2.3rem);">
                        S/ {{ $fmt($interesCobrado) }}
                    </div>
                    <div class="dashx-sub">{{ $fmt0($nInteres) }} {{ $nInteres === 1 ? 'pago' : 'pagos' }} en el período</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card dashx-tile h-100" wire:loading.class="opacity-50">
                <div class="tile-accent" style="background:#d03b3b;"></div>
                <div class="card-body py-3 ps-4">
                    <div class="dashx-label">Mora cobrada</div>
                    <div class="dashx-value" style="font-size: clamp(1.6rem, 3.5vw, 2.3rem);">
                        S/ {{ $fmt($moraCobrada) }}
                    </div>
                    <div class="dashx-sub">{{ $fmt0($nMora) }} {{ $nMora === 1 ? 'pago' : 'pagos' }} en el período</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ CARTERA VIVA (snapshot de hoy, matriz: /reports/portfolio) ══ --}}
    <div class="d-flex align-items-center gap-2 mt-4 mb-2">
        <h6 class="mb-0 dashx-label" style="font-size:.85rem;">Cartera viva</h6>
        <span class="dashx-sub">al {{ now()->format('d/m/Y') }} · no cambia con el filtro de período</span>
        <a href="{{ route('reports.portfolio') }}" class="ms-auto btn btn-sm btn-outline-primary py-0">
            Ver reporte de Cartera <i class="ti ti-arrow-right"></i>
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="tile-accent" style="background:#2a78d6;"></div>
                <div class="card-body py-3 ps-4">
                    <div class="dashx-label">Saldo por cobrar</div>
                    <div class="dashx-value" style="font-size: clamp(1.5rem, 3vw, 2.1rem);">
                        S/ {{ $fmt($carteraTotals['saldo']) }}
                    </div>
                    <div class="dashx-sub">
                        {{ $fmt0($carteraVigentes + $carteraVencidos) }} créditos en cartera
                        · capital S/ {{ $fmt($carteraTotals['capital']) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="tile-accent" style="background:#0ca30c;"></div>
                <div class="card-body py-3 ps-4">
                    <div class="dashx-label">Cartera al día</div>
                    <div class="dashx-value" style="font-size: clamp(1.5rem, 3vw, 2.1rem);">
                        S/ {{ $fmt($morosidad['activos_saldo']) }}
                    </div>
                    <div class="dashx-sub">{{ $fmt0($morosidad['activos_count']) }} créditos sin atraso</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashx-tile h-100">
                <div class="tile-accent" style="background:#d03b3b;"></div>
                <div class="card-body py-3 ps-4">
                    <div class="dashx-label">Saldo en mora</div>
                    <div class="dashx-value" style="font-size: clamp(1.5rem, 3vw, 2.1rem);">
                        S/ {{ $fmt($morosidad['mora_saldo']) }}
                    </div>
                    <div class="dashx-sub">{{ $fmt0($morosidad['mora_count']) }} créditos vencidos con saldo</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Medidor de morosidad (composición del saldo por cobrar) --}}
    @php
        $moraPct = (float) $morosidad['mora_pct'];
        $alDiaPct = max(0, 100 - $moraPct);
        $nivel = $moraPct < 10 ? ['#0ca30c', 'saludable'] : ($moraPct < 25 ? ['#fab219', 'en alerta'] : ['#d03b3b', 'crítica']);
    @endphp
    <div class="card dashx-tile mt-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-baseline mb-1">
                <span class="dashx-label">Morosidad de la cartera</span>
                <span class="dashx-value" style="font-size:1.35rem; color: {{ $nivel[0] }};">
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
                <span><span style="color:#0ca30c;">●</span> Al día {{ number_format($alDiaPct, 2) }}%</span>
                <span><span style="color:#d03b3b;">●</span> En mora {{ number_format($moraPct, 2) }}%</span>
            </div>
        </div>
    </div>

    {{-- Distribución por planilla (capital en cartera) --}}
    <div class="row g-3 mt-0">
        @php
            $planillas = [
                ['Semanal', $tipoTotals['sempo'], $tipoTotals['totsem'], '#2a78d6'],
                ['Mensual', $tipoTotals['mempo'], $tipoTotals['totmen'], '#eb6834'],
                ['Diario',  $tipoTotals['dempo'], $tipoTotals['totdia'], '#1baf7a'],
            ];
        @endphp
        @foreach($planillas as [$nombre, $n, $capital, $color])
            <div class="col-12 col-md-4">
                <div class="card dashx-tile h-100">
                    <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                        <span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:{{ $color }};"></span>
                        <div>
                            <div class="dashx-label" style="letter-spacing:1px;">{{ $nombre }}</div>
                            <div class="dashx-value" style="font-size:1.15rem;">S/ {{ $fmt($capital) }}</div>
                            <div class="dashx-sub">{{ $fmt0($n) }} {{ $n === 1 ? 'crédito' : 'créditos' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
