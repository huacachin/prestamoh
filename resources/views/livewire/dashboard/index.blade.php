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
            text-decoration: none; transition: background .15s ease;
        }
        a.dashx-chip:hover { background: rgba(255,255,255,.30); color: #fff; }
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

        /* ── Curva del mes (barras apiladas nuevos/refi) ── */
        .chart-swatch {
            display:inline-block; width:10px; height:10px; border-radius:3px;
            vertical-align:baseline; margin-right:3px;
        }
        .dashx-chart { position: relative; height: 230px; padding-left: 42px; }
        .dashx-chart .grid-line {
            position: absolute; left: 42px; right: 0; height: 1px;
            background: #eceae6; z-index: 0;
        }
        .dashx-chart .grid-line span {
            position: absolute; left: -42px; top: -7px; width: 36px;
            text-align: right; font-size: .68rem; color: #8b8983;
            font-variant-numeric: tabular-nums;
        }
        .dashx-chart .bars {
            position: absolute; inset: 0 0 0 42px;
            display: flex; align-items: flex-end; gap: 3px; z-index: 1;
        }
        .day-col {
            flex: 1 1 0; min-width: 0; height: 100%;
            display: flex; flex-direction: column; justify-content: flex-end;
            cursor: default; border-radius: 4px;
        }
        .day-col:hover { background: rgba(42,120,214,.06); }
        .day-col.is-selected { background: rgba(42,120,214,.10); outline: 1px solid #2a78d6; }
        .day-col.is-dimmed .seg { opacity: .35; }
        .day-stack {
            height: calc(100% - 26px);
            display: flex; flex-direction: column; justify-content: flex-end;
        }
        .day-stack .seg { border-radius: 4px 4px 0 0; min-height: 2px; }
        .day-stack .seg + .seg { border-radius: 0; }
        .day-stack .seg-space { height: 2px; }
        .day-label {
            height: 26px; line-height: 26px; text-align: center;
            font-size: .66rem; color: #8b8983; font-variant-numeric: tabular-nums;
            overflow: hidden;
        }
        .chart-tip {
            position: absolute; z-index: 5; pointer-events: none;
            background: #0b0b0b; color: #fff; border-radius: 8px;
            padding: 7px 10px; font-size: .76rem; line-height: 1.5;
            box-shadow: 0 4px 14px rgba(0,0,0,.25); white-space: nowrap;
        }
        .chart-tip .tt-title { font-weight: 700; }
        .chart-tip .tt-dot { display:inline-block; width:8px; height:8px; border-radius:2px; margin-right:4px; }
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
                {{-- select2 con búsqueda, SIN texto libre (solo elegir del catálogo) --}}
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Año</label>
                    <select class="form-select form-select-sm select2-simple" wire:model.live="year">
                        @foreach($anios as $a)
                            <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Mes</label>
                    <select class="form-select form-select-sm select2-simple" wire:model.live="month">
                        @foreach($meses as $num => $nombre)
                            <option value="{{ $num }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- wire:key: las opciones del día dependen del mes — nodo nuevo
                     al cambiar para que select2 se reinicie limpio tras el morph --}}
                <div class="col-6 col-md-2" wire:key="dash-dia-{{ $year }}-{{ $month }}">
                    <label class="form-label mb-0 small fw-semibold">Día</label>
                    <select class="form-select form-select-sm select2-simple" wire:model.live="day">
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
            @php
                $qsPeriodo = 'mes='.$month.'&anio='.$year.($day !== '' && $day !== null ? '&dia='.$day : '');
            @endphp
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
                <a href="{{ route('reports.desembolsos') }}?{{ $qsPeriodo }}&tipo=nuevos"
                   class="dashx-chip" title="Ver los créditos nuevos del período">
                    <i class="ti ti-coins"></i>
                    Nuevos: <strong>{{ $fmt0($nuevos['n']) }}</strong> · S/ <strong>{{ $fmt($nuevos['total']) }}</strong>
                    <i class="ti ti-arrow-right" style="opacity:.7;"></i>
                </a>
                <a href="{{ route('reports.desembolsos') }}?{{ $qsPeriodo }}&tipo=refinanciados"
                   class="dashx-chip" title="Ver los refinanciados del período">
                    <i class="ti ti-refresh"></i>
                    Refinanciados: <strong>{{ $fmt0($refis['n']) }}</strong> · S/ <strong>{{ $fmt($refis['total']) }}</strong>
                    <i class="ti ti-arrow-right" style="opacity:.7;"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- ══ CURVA DEL MES: capital prestado día a día ═════════ --}}
    <div class="card dashx-tile mt-3" wire:loading.class="opacity-50">
        <div class="card-body py-3">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
                <span class="dashx-label">Día a día — {{ $meses[(int) $month] }} {{ $year }}</span>
                <div class="d-flex gap-3 ms-auto dashx-sub">
                    <span><span class="chart-swatch" style="background:#2a78d6;"></span> Nuevos</span>
                    <span><span class="chart-swatch" style="background:#eb6834;"></span> Refinanciados</span>
                </div>
            </div>

            @if($serieMax <= 0)
                <div class="text-center dashx-sub py-4">Sin desembolsos en {{ $meses[(int) $month] }} {{ $year }}.</div>
            @else
                @php
                    // Escala: tope redondeado hacia arriba a un número "amable"
                    $mag = pow(10, floor(log10($serieMax)));
                    $tope = ceil($serieMax / $mag) * $mag;
                    $fmtC = function ($n) {
                        if ($n >= 1000000) return number_format($n / 1000000, 1).'M';
                        if ($n >= 1000) return number_format($n / 1000, 0).'K';
                        return number_format($n, 0);
                    };
                @endphp
                <div class="dashx-chart" id="curva-mes">
                    {{-- Gridlines + etiquetas Y (recesivas) --}}
                    @foreach([1, .5, 0] as $g)
                        <div class="grid-line" style="bottom: calc({{ $g * 100 }}% * .82 + 26px);">
                            <span>{{ $fmtC($tope * $g) }}</span>
                        </div>
                    @endforeach

                    <div class="bars">
                        @foreach($serie as $p)
                            @php
                                $hN = $tope > 0 ? round($p['nuevo'] * 100 / $tope, 2) : 0;
                                $hR = $tope > 0 ? round($p['refi'] * 100 / $tope, 2) : 0;
                                $esFiltrado = $diaFiltrado && (int) $diaFiltrado === $p['d'];
                            @endphp
                            @php
                                $tt = "<div class='tt-title'>{$p['d']} de {$meses[(int) $month]}</div>"
                                    ."<div><span class='tt-dot' style='background:#2a78d6'></span>Nuevos: S/ ".number_format($p['nuevo'], 2).'</div>'
                                    ."<div><span class='tt-dot' style='background:#eb6834'></span>Refinanciados: S/ ".number_format($p['refi'], 2).'</div>'
                                    ."<div class='tt-title'>Total: S/ ".number_format($p['total'], 2).'</div>';
                            @endphp
                            <div class="day-col tt-col {{ $esFiltrado ? 'is-selected' : '' }} {{ $diaFiltrado && ! $esFiltrado ? 'is-dimmed' : '' }}"
                                 data-tt="{{ $tt }}">
                                <div class="day-stack">
                                    @if($hR > 0)
                                        <div class="seg" style="height: {{ $hR }}%; background:#eb6834;"></div>
                                    @endif
                                    @if($hN > 0 && $hR > 0)
                                        <div class="seg-space"></div>
                                    @endif
                                    @if($hN > 0)
                                        <div class="seg" style="height: {{ $hN }}%; background:#2a78d6;"></div>
                                    @endif
                                </div>
                                <div class="day-label">{{ $p['d'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="chart-tip" hidden></div>
                </div>
            @endif
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
            <div class="d-flex justify-content-between mt-1 dashx-sub flex-wrap">
                <span><span style="color:#0ca30c;">●</span> Al día S/ {{ $fmt($morosidad['activos_saldo']) }}</span>
                <span class="fw-semibold" style="color:#0b0b0b;">
                    Total S/ {{ $fmt($morosidad['total_saldo']) }}
                </span>
                <span><span style="color:#d03b3b;">●</span> En mora S/ {{ $fmt($morosidad['mora_saldo']) }}</span>
            </div>
        </div>
    </div>

    {{-- ══ MOROSIDAD: aging + evolución ══════════════════════ --}}
    <div class="dashx-head">
        <h6 class="mb-0 dashx-label" style="font-size:.82rem;">Morosidad</h6>
        <span class="dashx-sub">antigüedad de hoy · evolución de {{ $meses[(int) $month] }} {{ $year }}</span>
        <div class="rule"></div>
        <a href="{{ route('reports.delinquent') }}" class="btn btn-sm btn-outline-primary py-0">
            Pendientes por cobrar <i class="ti ti-arrow-right"></i>
        </a>
    </div>

    <div class="row g-3">
        {{-- Aging: rampa secuencial de un solo tono (severidad ordenada) --}}
        <div class="col-12 col-lg-5">
            <div class="card dashx-tile h-100">
                <div class="card-body py-3">
                    <div class="dashx-label mb-2">Antigüedad de la mora <span style="text-transform:none; letter-spacing:0;">(días desde el vencimiento)</span></div>
                    @php
                        $rampa = ['#f5c4c4', '#efa3a3', '#e68181', '#da5f5f', '#c94141', '#a82626'];
                        $agingTope = max(0.01, $agingMax);
                        $agingTotal = max(0.01, array_sum(array_column($agingBuckets, 'saldo')));
                    @endphp
                    @foreach($agingBuckets as $i => $b)
                        @php
                            $w = round($b['saldo'] * 100 / $agingTope, 1);
                            $pctB = round($b['saldo'] * 100 / $agingTotal, 1);
                        @endphp
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="dashx-sub text-end" style="width:44px; font-variant-numeric:tabular-nums;">{{ $b['bucket'] }}</span>
                            <div class="flex-grow-1" style="background:#f4f2ef; border-radius:5px; height:22px; overflow:hidden;">
                                <div style="width:{{ max($w, $b['saldo'] > 0 ? 1.5 : 0) }}%; height:100%; background:{{ $rampa[$i] }}; border-radius:5px;"></div>
                            </div>
                            <span class="dashx-sub text-end" style="width:158px; font-variant-numeric:tabular-nums; white-space:nowrap;">
                                S/ {{ $fmt($b['saldo']) }}
                                <strong style="color:#0b0b0b;">{{ $pctB }}%</strong>
                                <span style="color:#a8a49c;">({{ $b['n'] }})</span>
                            </span>
                        </div>
                    @endforeach
                    <div class="dashx-sub mt-1">
                        Total: <strong style="color:#0b0b0b;">S/ {{ $fmt(array_sum(array_column($agingBuckets, 'saldo'))) }}</strong>
                        en {{ $fmt0(array_sum(array_column($agingBuckets, 'n'))) }} créditos — cuadra con el medidor.
                    </div>
                </div>
            </div>
        </div>

        {{-- Evolución: % de morosidad día a día del mes filtrado --}}
        <div class="col-12 col-lg-7">
            <div class="card dashx-tile h-100" wire:loading.class="opacity-50">
                <div class="card-body py-3">
                    <div class="dashx-label mb-2">Evolución del mes <span style="text-transform:none; letter-spacing:0;">(% del saldo por cobrar en mora)</span></div>
                    @if(count($serieMoro) < 2)
                        <div class="text-center dashx-sub py-4">Sin datos de morosidad para este mes (el histórico llega hasta 12 meses atrás).</div>
                    @else
                        @php
                            $pcts = array_column($serieMoro, 'pct');
                            $topPct = max(5, ceil(max($pcts) * 1.15 / 5) * 5);
                            $nP = count($serieMoro);
                            $pts = [];      // línea
                            foreach ($serieMoro as $i => $p) {
                                $x = round($i * 100 / max(1, $nP - 1), 2);
                                $y = round(42 - ($p['pct'] * 38 / $topPct) - 2, 2);
                                $pts[] = $x.','.$y;
                            }
                            $linea = implode(' ', $pts);
                            $area = '0,42 '.$linea.' 100,42';
                        @endphp
                        <div class="dashx-chart" style="height:210px;">
                            @foreach([1, .5, 0] as $g)
                                <div class="grid-line" style="bottom: calc({{ $g * 100 }}% * .81 + 26px);">
                                    <span>{{ number_format($topPct * $g, 0) }}%</span>
                                </div>
                            @endforeach

                            <svg viewBox="0 0 100 42" preserveAspectRatio="none"
                                 style="position:absolute; inset:0 0 26px 42px; width:calc(100% - 42px); height:calc(100% - 26px);">
                                <polygon points="{{ $area }}" fill="rgba(208,59,59,.10)"/>
                                <polyline points="{{ $linea }}" fill="none" stroke="#d03b3b"
                                          stroke-width="2" vector-effect="non-scaling-stroke"
                                          stroke-linejoin="round" stroke-linecap="round"/>
                            </svg>

                            <div class="bars">
                                @foreach($serieMoro as $p)
                                    @php
                                        $esFiltrado = $diaFiltrado && (int) $diaFiltrado === $p['d'];
                                        $tt = "<div class='tt-title'>{$p['d']} de {$meses[(int) $month]}</div>"
                                            ."<div>Morosidad: <strong>".number_format($p['pct'], 2).'%</strong></div>'
                                            .'<div>En mora: S/ '.number_format($p['saldo'], 2).'</div>'
                                            .'<div>'.$p['n'].' créditos</div>';
                                    @endphp
                                    <div class="day-col tt-col {{ $esFiltrado ? 'is-selected' : '' }}" data-tt="{{ $tt }}">
                                        <div class="day-stack"></div>
                                        <div class="day-label">{{ $p['d'] }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="chart-tip" hidden></div>
                        </div>
                    @endif
                </div>
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

    @script
    <script>
        // Tooltip genérico de los charts del dashboard: cualquier .tt-col
        // con data-tt dentro de un .dashx-chart. Delegación en document:
        // sobrevive los re-renders de Livewire al cambiar los filtros.
        const moverTip = (e) => {
            const col = e.target.closest?.('.tt-col');
            const chart = col?.closest('.dashx-chart');

            document.querySelectorAll('.chart-tip').forEach(t => {
                if (! chart || t.parentElement !== chart) t.hidden = true;
            });
            if (! col || ! chart) return;

            const tip = chart.querySelector('.chart-tip');
            if (! tip) return;

            tip.innerHTML = col.dataset.tt || '';
            tip.hidden = false;

            const r = chart.getBoundingClientRect();
            const x = Math.min(e.clientX - r.left + 14, r.width - tip.offsetWidth - 4);
            const y = Math.max(0, e.clientY - r.top - tip.offsetHeight - 10);
            tip.style.left = x + 'px';
            tip.style.top = y + 'px';
        };
        document.addEventListener('mousemove', moverTip);
        // Livewire desmonta el componente al navegar: soltar el listener
        $wire.$hook?.('component.destroy', () => document.removeEventListener('mousemove', moverTip));
    </script>
    @endscript

</div>
