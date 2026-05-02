{{-- =========================================================
     PANEL DE CONTROL  ·  Sala de mando financiera
     ========================================================= --}}
<div class="huac-dashboard">

    {{-- Tipografías editoriales (Instrument Serif + IBM Plex Mono) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=IBM+Plex+Mono:wght@400;500;600&display=swap">

    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
        $userName = trim(explode(' ', auth()->user()->name ?? 'Operador')[0]);

        $fmt = fn($n, $d=2) => number_format((float)$n, $d, '.', ',');
    @endphp

    {{-- ════════ TIRA SUPERIOR ════════ --}}
    <div class="dash-topstrip">
        <div class="dash-topstrip__left">
            <span class="dash-eyebrow">{{ now()->translatedFormat('l, d \\d\\e F · Y') }}</span>
            <h1 class="dash-greeting">
                {{ $greeting }}, <em>{{ $userName }}</em>.
                <span class="dash-greeting__sub">Esto es lo que pasa hoy en Huacachin.</span>
            </h1>
        </div>
        <div class="dash-topstrip__right">
            <span class="dash-pulse"></span>
            <span class="dash-eyebrow dash-eyebrow--mono">act. {{ now()->format('H:i') }}</span>
        </div>
    </div>

    {{-- ════════ ROW 1 · HERO + OPERACIÓN DEL DÍA ════════ --}}
    <div class="row g-3 dash-stagger">

        {{-- HERO · Capital colocado --}}
        <div class="col-xl-8 col-lg-12 dash-anim" style="--i:1">
            <article class="dash-hero">
                <div class="dash-hero__grain"></div>
                <header class="dash-hero__head">
                    <span class="dash-eyebrow dash-eyebrow--light">Capital colocado · cartera activa</span>
                    <span class="dash-chip">
                        <span class="dash-chip__dot"></span>
                        {{ $creditosActivos }} créditos vivos
                    </span>
                </header>

                <div class="dash-hero__num">
                    <span class="dash-currency">S/</span>
                    <span class="dash-bigint">{{ $fmt($totalCartera, 0) }}</span>
                    <span class="dash-decimal">.{{ str_pad((int)(($totalCartera - floor($totalCartera))*100), 2, '0', STR_PAD_LEFT) }}</span>
                </div>

                <div class="dash-hero__foot">
                    <div class="dash-hero__sparkwrap" wire:ignore>
                        <div id="dashSpark"></div>
                        <span class="dash-hero__sparklbl">Cobranza · últimos 7 días</span>
                    </div>
                    <div class="dash-hero__split">
                        <div>
                            <span class="dash-eyebrow dash-eyebrow--light">cobranza mes</span>
                            <span class="dash-hero__val">S/ {{ $fmt($cobranzaMes) }}</span>
                        </div>
                        <div>
                            <span class="dash-eyebrow dash-eyebrow--light">monto en mora</span>
                            <span class="dash-hero__val dash-hero__val--warn">S/ {{ $fmt($montoEnMora) }}</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        {{-- OPS DEL DÍA --}}
        <div class="col-xl-4 col-lg-12 dash-anim" style="--i:2">
            <article class="dash-ops">
                <header class="dash-ops__head">
                    <span class="dash-eyebrow">Operación del día</span>
                    <span class="dash-eyebrow dash-eyebrow--mono">{{ now()->format('d.m.Y') }}</span>
                </header>

                <ul class="dash-ops__list">
                    <li class="dash-ops__row">
                        <span class="dash-ops__dot dash-ops__dot--cyan"></span>
                        <div class="dash-ops__txt">
                            <span class="dash-ops__lbl">Cobranza hoy</span>
                            <span class="dash-ops__sub">
                                @if($deltaCobranza >= 0)
                                    <i class="ti ti-arrow-up-right"></i> {{ $fmt(abs($deltaCobranza), 1) }}% vs ayer
                                @else
                                    <i class="ti ti-arrow-down-right"></i> {{ $fmt(abs($deltaCobranza), 1) }}% vs ayer
                                @endif
                            </span>
                        </div>
                        <span class="dash-ops__num">S/ {{ $fmt($cobranzaHoy) }}</span>
                    </li>
                    <li class="dash-ops__row">
                        <span class="dash-ops__dot dash-ops__dot--green"></span>
                        <div class="dash-ops__txt">
                            <span class="dash-ops__lbl">Ingresos a caja</span>
                            <span class="dash-ops__sub">+ entrada del día</span>
                        </div>
                        <span class="dash-ops__num">S/ {{ $fmt($ingresosHoy) }}</span>
                    </li>
                    <li class="dash-ops__row">
                        <span class="dash-ops__dot dash-ops__dot--red"></span>
                        <div class="dash-ops__txt">
                            <span class="dash-ops__lbl">Egresos a caja</span>
                            <span class="dash-ops__sub">– salida del día</span>
                        </div>
                        <span class="dash-ops__num">S/ {{ $fmt($egresosHoy) }}</span>
                    </li>
                    <li class="dash-ops__row dash-ops__row--total">
                        <span class="dash-ops__dot dash-ops__dot--ink"></span>
                        <div class="dash-ops__txt">
                            <span class="dash-ops__lbl">Saldo neto de caja</span>
                            <span class="dash-ops__sub">ingresos − egresos</span>
                        </div>
                        <span class="dash-ops__num dash-ops__num--big {{ $saldoCajaHoy >= 0 ? '' : 'is-neg' }}">
                            S/ {{ $fmt($saldoCajaHoy) }}
                        </span>
                    </li>
                </ul>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR EDITORIAL ════════ --}}
    <div class="dash-section">
        <span class="dash-section__num">01</span>
        <span class="dash-section__lbl">Indicadores · cartera</span>
        <span class="dash-section__rule"></span>
    </div>

    {{-- ════════ ROW 2 · KPI GRID ════════ --}}
    <div class="row g-3 dash-stagger">
        <div class="col-xl-3 col-md-6 dash-anim" style="--i:3">
            <article class="dash-kpi">
                <div class="dash-kpi__top">
                    <span class="dash-eyebrow">Créditos activos</span>
                    <i class="ti ti-credit-card dash-kpi__icon"></i>
                </div>
                <div class="dash-kpi__num">{{ $fmt($creditosActivos, 0) }}</div>
                <div class="dash-kpi__foot">
                    <span class="dash-mono">en gestión</span>
                </div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 dash-anim" style="--i:4">
            <article class="dash-kpi">
                <div class="dash-kpi__top">
                    <span class="dash-eyebrow">Cobranza mes</span>
                    <i class="ti ti-cash dash-kpi__icon"></i>
                </div>
                <div class="dash-kpi__num">
                    <small>S/</small>{{ $fmt($cobranzaMes, 0) }}
                </div>
                <div class="dash-kpi__foot">
                    <span class="dash-mono">{{ now()->translatedFormat('F') }}</span>
                </div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 dash-anim" style="--i:5">
            <article class="dash-kpi dash-kpi--warn">
                <div class="dash-kpi__top">
                    <span class="dash-eyebrow">Saldo en mora</span>
                    <i class="ti ti-alert-triangle dash-kpi__icon"></i>
                </div>
                <div class="dash-kpi__num">
                    <small>S/</small>{{ $fmt($montoEnMora, 0) }}
                </div>
                <div class="dash-kpi__foot">
                    <span class="dash-mono">{{ $morosidad }} créditos vencidos</span>
                </div>
            </article>
        </div>

        <div class="col-xl-3 col-md-6 dash-anim" style="--i:6">
            <article class="dash-kpi">
                <div class="dash-kpi__top">
                    <span class="dash-eyebrow">Tasa de morosidad</span>
                    <i class="ti ti-percentage dash-kpi__icon"></i>
                </div>
                <div class="dash-kpi__num">
                    {{ $fmt($morosidadPct, 1) }}<small>%</small>
                </div>
                <div class="dash-kpi__bar">
                    <span style="width: {{ min(100, $morosidadPct) }}%"></span>
                </div>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR EDITORIAL ════════ --}}
    <div class="dash-section">
        <span class="dash-section__num">02</span>
        <span class="dash-section__lbl">Tendencia · cobranza</span>
        <span class="dash-section__rule"></span>
    </div>

    {{-- ════════ ROW 3 · CHART + DONUT ════════ --}}
    <div class="row g-3 dash-stagger">
        <div class="col-xl-8 dash-anim" style="--i:7">
            <article class="dash-panel">
                <header class="dash-panel__head">
                    <div>
                        <span class="dash-eyebrow">Cobranza · últimos 30 días</span>
                        <h3 class="dash-panel__title">
                            S/ <em>{{ $fmt(array_sum(array_column($cobranza30, 'y'))) }}</em>
                        </h3>
                    </div>
                    <div class="dash-legend">
                        <span><i class="dash-legend__sw" style="background:var(--huac-accent)"></i> Cobranza diaria</span>
                    </div>
                </header>
                <div class="dash-panel__body" wire:ignore>
                    <div id="dashChart30" style="height:280px;"></div>
                </div>
            </article>
        </div>

        <div class="col-xl-4 dash-anim" style="--i:8">
            <article class="dash-panel dash-panel--ink">
                <header class="dash-panel__head">
                    <div>
                        <span class="dash-eyebrow dash-eyebrow--light">Cartera · por estado</span>
                        <h3 class="dash-panel__title dash-panel__title--light">
                            <em>{{ $cartera->sum('n') }}</em><small> créditos</small>
                        </h3>
                    </div>
                </header>
                <div class="dash-panel__body" wire:ignore>
                    <div id="dashDonut" style="height:200px;"></div>
                </div>
                <ul class="dash-donut__legend">
                    @foreach($cartera as $c)
                        <li>
                            <span class="dash-donut__sw dash-donut__sw--{{ \Illuminate\Support\Str::slug($c->situacion) }}"></span>
                            <span class="dash-donut__lbl">{{ $c->situacion }}</span>
                            <span class="dash-donut__val">{{ $c->n }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>
        </div>
    </div>

    {{-- ════════ DIVISOR EDITORIAL ════════ --}}
    <div class="dash-section">
        <span class="dash-section__num">03</span>
        <span class="dash-section__lbl">Atención · alertas y movimiento</span>
        <span class="dash-section__rule"></span>
    </div>

    {{-- ════════ ROW 4 · ALERTAS + ÚLTIMOS PAGOS ════════ --}}
    <div class="row g-3 dash-stagger">
        <div class="col-xl-5 dash-anim" style="--i:9">
            <article class="dash-panel dash-panel--warn">
                <header class="dash-panel__head">
                    <div>
                        <span class="dash-eyebrow dash-eyebrow--warn">Cuotas vencidas</span>
                        <h3 class="dash-panel__title">Requieren cobranza</h3>
                    </div>
                    @if($alertasVencidas->isNotEmpty())
                        <a href="{{ route('reports.delinquent') }}" class="dash-link">Ver todas
                            <i class="ti ti-arrow-narrow-right"></i></a>
                    @endif
                </header>
                <div class="dash-panel__body p-0">
                    @if($alertasVencidas->isEmpty())
                        <div class="dash-empty">
                            <i class="ti ti-circle-check"></i>
                            <p>Sin cuotas vencidas. Cartera al día.</p>
                        </div>
                    @else
                        <ul class="dash-alerts">
                            @foreach($alertasVencidas as $a)
                                <li class="dash-alerts__row">
                                    <div class="dash-alerts__main">
                                        <a href="{{ route('credits.show', $a->credito) }}" class="dash-alerts__name">
                                            {{ \Illuminate\Support\Str::limit($a->cliente, 32) }}
                                        </a>
                                        <span class="dash-alerts__meta">
                                            #{{ $a->credito }} · cuota {{ $a->cuota }}
                                        </span>
                                    </div>
                                    <div class="dash-alerts__num">
                                        <span class="dash-alerts__val">S/ {{ $fmt($a->saldo) }}</span>
                                        <span class="dash-alerts__days">{{ $a->dias }} días</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        </div>

        <div class="col-xl-7 dash-anim" style="--i:10">
            <article class="dash-panel">
                <header class="dash-panel__head">
                    <div>
                        <span class="dash-eyebrow">Movimiento reciente</span>
                        <h3 class="dash-panel__title">Últimos pagos registrados</h3>
                    </div>
                    <a href="{{ route('payments.index') }}" class="dash-link">Ver todos
                        <i class="ti ti-arrow-narrow-right"></i></a>
                </header>
                <div class="dash-panel__body p-0">
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ultimosPagos as $pago)
                                    <tr>
                                        <td class="dash-table__date">
                                            {{ $pago->fecha?->format('d M') }}
                                        </td>
                                        <td>
                                            <span class="dash-table__name">
                                                {{ $pago->credit?->client?->fullName() ?? '—' }}
                                            </span>
                                            <span class="dash-table__sub">#{{ $pago->credit_id ?? '—' }}</span>
                                        </td>
                                        <td>
                                            @php
                                                $tipoClass = match(strtoupper($pago->tipo ?? '')) {
                                                    'CAPITAL' => 'is-cap',
                                                    'INTERES' => 'is-int',
                                                    'MORA'    => 'is-mor',
                                                    default   => '',
                                                };
                                            @endphp
                                            <span class="dash-pill {{ $tipoClass }}">{{ $pago->tipo }}</span>
                                        </td>
                                        <td class="text-end dash-table__num">S/ {{ $fmt($pago->monto) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="dash-empty--row">Sin pagos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>
    </div>

    {{-- ════════ ROW 5 · CRÉDITOS RECIENTES ════════ --}}
    <div class="row g-3 mt-1 dash-stagger">
        <div class="col-12 dash-anim" style="--i:11">
            <article class="dash-panel">
                <header class="dash-panel__head">
                    <div>
                        <span class="dash-eyebrow">Originaciones</span>
                        <h3 class="dash-panel__title">Créditos recientes</h3>
                    </div>
                    <a href="{{ route('credits.index') }}" class="dash-link">Ir a créditos
                        <i class="ti ti-arrow-narrow-right"></i></a>
                </header>
                <div class="dash-panel__body p-0">
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Importe</th>
                                    <th>Situación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditosRecientes as $c)
                                    <tr>
                                        <td class="dash-table__date">{{ $c->fecha_prestamo?->format('d M') }}</td>
                                        <td>
                                            <a href="{{ route('credits.show', $c->id) }}" class="dash-link dash-link--inline">#{{ $c->id }}</a>
                                        </td>
                                        <td>
                                            <span class="dash-table__name">{{ $c->client?->fullName() ?? '—' }}</span>
                                        </td>
                                        <td class="text-end dash-table__num">S/ {{ $fmt($c->importe) }}</td>
                                        <td>
                                            @php
                                                $sitClass = match($c->situacion) {
                                                    'Activo'       => 'is-active',
                                                    'Cancelado'    => 'is-cancel',
                                                    'Refinanciado' => 'is-refin',
                                                    'Eliminado'    => 'is-elim',
                                                    default        => '',
                                                };
                                            @endphp
                                            <span class="dash-pill {{ $sitClass }}">{{ $c->situacion }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="dash-empty--row">Sin créditos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>
    </div>

</div>

{{-- ════════════════════════ ESTILOS (scoped) ════════════════════════ --}}
<style>
.huac-dashboard {
    --huac-ink:      #0B1B3D;
    --huac-ink-2:    #1B2A4E;
    --huac-paper:    #FAF7F1;
    --huac-bone:     #FFFFFF;
    --huac-rule:     #E8E2D5;
    --huac-rule-2:   #ECECF0;
    --huac-accent:   #009BDC;
    --huac-accent-2: #00B4D8;
    --huac-warn:     #C2410C;
    --huac-warn-bg:  #FFF4ED;
    --huac-ok:       #15803D;
    --huac-mute:     #6B6F7A;
    --huac-mute-2:   #9AA0AA;

    --huac-serif:    'Instrument Serif', 'Iowan Old Style', Georgia, serif;
    --huac-mono:     'IBM Plex Mono', ui-monospace, 'JetBrains Mono', monospace;

    font-feature-settings: 'tnum' 1;
    padding: 0 16px 32px;
}

/* ── tira superior ─────────────────────────────────────── */
.dash-topstrip {
    display: flex; align-items: flex-end; justify-content: space-between;
    padding: 4px 4px 18px; gap: 24px; flex-wrap: wrap;
}
.dash-topstrip__left { min-width: 0; }
.dash-greeting {
    font-family: var(--huac-serif);
    font-size: clamp(28px, 3.2vw, 42px);
    line-height: 1.05;
    color: var(--huac-ink);
    margin: 6px 0 0;
    letter-spacing: -0.01em;
    font-weight: 400;
}
.dash-greeting em {
    font-style: italic;
    color: var(--huac-accent);
    font-weight: 400;
}
.dash-greeting__sub {
    display: block;
    font-family: 'Poppins', sans-serif;
    font-style: normal;
    font-size: 13px;
    color: var(--huac-mute);
    margin-top: 4px;
    letter-spacing: 0;
    font-weight: 400;
}
.dash-topstrip__right {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 12px;
    border: 1px solid var(--huac-rule-2);
    border-radius: 999px;
    background: var(--huac-bone);
}
.dash-pulse {
    width: 8px; height: 8px; border-radius: 999px;
    background: var(--huac-ok);
    box-shadow: 0 0 0 0 rgba(21,128,61,.55);
    animation: dashPulse 2s infinite;
}
@keyframes dashPulse {
    0%   { box-shadow: 0 0 0 0 rgba(21,128,61,.55); }
    70%  { box-shadow: 0 0 0 8px rgba(21,128,61,0); }
    100% { box-shadow: 0 0 0 0 rgba(21,128,61,0); }
}

/* ── eyebrows / chips / tipografía utilitaria ─────────── */
.dash-eyebrow {
    display: inline-block;
    font-family: 'Poppins', sans-serif;
    font-size: 10px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--huac-mute);
    font-weight: 600;
}
.dash-eyebrow--light  { color: rgba(255,255,255,.62); }
.dash-eyebrow--mono   { font-family: var(--huac-mono); letter-spacing: 0.06em; }
.dash-eyebrow--warn   { color: var(--huac-warn); }
.dash-mono {
    font-family: var(--huac-mono); font-size: 11px;
    color: var(--huac-mute); letter-spacing: .02em;
}

/* ── HERO ──────────────────────────────────────────────── */
.dash-hero {
    position: relative; overflow: hidden;
    background: radial-gradient(120% 140% at 0% 0%, #14274F 0%, #0B1B3D 55%, #07122A 100%);
    color: #fff;
    border-radius: 18px;
    padding: 28px 32px 24px;
    min-height: 320px;
    display: flex; flex-direction: column;
    box-shadow: 0 18px 40px -22px rgba(11,27,61,.55);
}
.dash-hero__grain {
    position: absolute; inset: 0; pointer-events: none; opacity: .35;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.06 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
    mix-blend-mode: overlay;
}
.dash-hero__head {
    position: relative; z-index: 1;
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
}
.dash-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.16);
    color: #fff;
    font-family: var(--huac-mono); font-size: 11px;
    padding: 5px 11px; border-radius: 999px;
    letter-spacing: .02em;
}
.dash-chip__dot {
    width: 6px; height: 6px; border-radius: 999px;
    background: var(--huac-accent-2);
    box-shadow: 0 0 8px var(--huac-accent-2);
}

.dash-hero__num {
    position: relative; z-index: 1;
    display: flex; align-items: baseline; gap: 6px;
    margin: 18px 0 8px;
    font-family: var(--huac-serif);
    line-height: 1;
    color: #fff;
    flex-wrap: wrap;
}
.dash-currency {
    font-size: 28px;
    color: rgba(255,255,255,.55);
    font-style: italic;
    letter-spacing: -0.02em;
}
.dash-bigint {
    font-size: clamp(48px, 7vw, 88px);
    font-style: italic;
    letter-spacing: -0.04em;
    font-feature-settings: 'tnum' 1;
}
.dash-decimal {
    font-size: 28px;
    color: rgba(255,255,255,.55);
    font-style: italic;
    letter-spacing: -0.02em;
}

.dash-hero__foot {
    position: relative; z-index: 1;
    margin-top: auto; padding-top: 18px;
    display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: end;
    border-top: 1px solid rgba(255,255,255,.12);
}
.dash-hero__sparkwrap { display: flex; flex-direction: column; min-width: 0; }
.dash-hero__sparklbl  { font-family: var(--huac-mono); font-size: 10px; color: rgba(255,255,255,.5); margin-top: 4px; }
.dash-hero__split { display: flex; gap: 28px; flex-wrap: wrap; }
.dash-hero__split > div { display: flex; flex-direction: column; gap: 4px; }
.dash-hero__val {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 24px; color: #fff; letter-spacing: -0.01em;
}
.dash-hero__val--warn { color: #FFB57A; }

@media (max-width: 768px) {
    .dash-hero { padding: 22px 20px; min-height: auto; }
    .dash-hero__foot { grid-template-columns: 1fr; gap: 16px; }
    .dash-hero__split { flex-wrap: wrap; gap: 16px; }
}

/* ── OPS DEL DÍA ───────────────────────────────────────── */
.dash-ops {
    background: var(--huac-bone);
    border: 1px solid var(--huac-rule-2);
    border-radius: 18px;
    padding: 22px 22px 14px;
    height: 100%;
    display: flex; flex-direction: column;
}
.dash-ops__head {
    display: flex; justify-content: space-between; align-items: center;
    padding-bottom: 14px;
    border-bottom: 1px dashed var(--huac-rule);
}
.dash-ops__list { list-style: none; margin: 6px 0 0; padding: 0; flex: 1; display: flex; flex-direction: column; }
.dash-ops__row {
    display: grid; grid-template-columns: auto 1fr auto;
    align-items: center; gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--huac-rule-2);
}
.dash-ops__row:last-child { border-bottom: 0; }
.dash-ops__row--total {
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px dashed var(--huac-rule);
    border-bottom: 0;
}
.dash-ops__dot {
    width: 8px; height: 8px; border-radius: 999px;
}
.dash-ops__dot--cyan  { background: var(--huac-accent); }
.dash-ops__dot--green { background: var(--huac-ok); }
.dash-ops__dot--red   { background: #B91C1C; }
.dash-ops__dot--ink   { background: var(--huac-ink); }

.dash-ops__txt { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.dash-ops__lbl { font-size: 13px; color: var(--huac-ink); font-weight: 500; }
.dash-ops__sub { font-family: var(--huac-mono); font-size: 10px; color: var(--huac-mute-2); }
.dash-ops__num {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 18px; color: var(--huac-ink);
    letter-spacing: -0.01em;
    font-feature-settings: 'tnum' 1;
}
.dash-ops__num--big { font-size: 22px; }
.dash-ops__num.is-neg { color: var(--huac-warn); }

/* ── DIVISORES EDITORIALES ────────────────────────────── */
.dash-section {
    display: flex; align-items: center; gap: 14px;
    margin: 32px 0 16px;
}
.dash-section__num {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 22px; color: var(--huac-mute-2);
    letter-spacing: -0.01em;
}
.dash-section__lbl {
    font-family: 'Poppins', sans-serif;
    font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase;
    color: var(--huac-ink); font-weight: 600;
}
.dash-section__rule { flex: 1; height: 1px; background: var(--huac-rule); }

/* ── KPI TILES ─────────────────────────────────────────── */
.dash-kpi {
    background: var(--huac-bone);
    border: 1px solid var(--huac-rule-2);
    border-radius: 16px;
    padding: 18px 20px 16px;
    height: 100%;
    transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
}
.dash-kpi:hover {
    transform: translateY(-2px);
    border-color: var(--huac-ink);
    box-shadow: 0 12px 28px -18px rgba(11,27,61,.4);
}
.dash-kpi__top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 14px;
}
.dash-kpi__icon {
    color: var(--huac-mute-2);
    font-size: 18px;
}
.dash-kpi__num {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 42px; line-height: 1; color: var(--huac-ink);
    letter-spacing: -0.025em;
    font-feature-settings: 'tnum' 1;
}
.dash-kpi__num small {
    font-size: 18px; color: var(--huac-mute);
    margin-right: 4px; font-style: italic;
}
.dash-kpi__foot { margin-top: 10px; min-height: 16px; }
.dash-kpi--warn .dash-kpi__num { color: var(--huac-warn); }
.dash-kpi--warn .dash-kpi__icon { color: var(--huac-warn); }

.dash-kpi__bar {
    margin-top: 12px; height: 4px;
    background: var(--huac-rule-2);
    border-radius: 999px; overflow: hidden;
}
.dash-kpi__bar span {
    display: block; height: 100%;
    background: linear-gradient(90deg, var(--huac-accent), var(--huac-ink));
    border-radius: 999px;
}

/* ── PANELS (charts, tables) ──────────────────────────── */
.dash-panel {
    background: var(--huac-bone);
    border: 1px solid var(--huac-rule-2);
    border-radius: 16px;
    overflow: hidden;
    height: 100%;
    display: flex; flex-direction: column;
}
.dash-panel--ink {
    background: linear-gradient(180deg, #0B1B3D 0%, #14274F 100%);
    color: #fff; border-color: transparent;
}
.dash-panel--ink .dash-panel__title { color: #fff; }
.dash-panel--warn { border-left: 3px solid var(--huac-warn); }

.dash-panel__head {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 18px 22px 12px; gap: 16px;
}
.dash-panel__title {
    font-family: var(--huac-serif); font-style: normal;
    font-size: 22px; color: var(--huac-ink);
    margin: 4px 0 0; letter-spacing: -0.01em; font-weight: 400;
}
.dash-panel__title em {
    font-style: italic; font-weight: 400;
    font-feature-settings: 'tnum' 1;
}
.dash-panel__title small {
    font-size: 13px; color: var(--huac-mute); margin-left: 4px;
    font-family: 'Poppins', sans-serif;
}
.dash-panel__title--light { color: #fff; }
.dash-panel__title--light small { color: rgba(255,255,255,.6); }
.dash-panel__body { padding: 6px 18px 18px; }
.dash-panel__body.p-0 { padding: 0; }

.dash-link {
    font-family: 'Poppins', sans-serif;
    font-size: 11px; color: var(--huac-ink);
    text-decoration: none; font-weight: 500;
    letter-spacing: 0.04em;
    display: inline-flex; align-items: center; gap: 4px;
    border-bottom: 1px solid transparent;
    transition: border-color .2s ease, color .2s ease;
}
.dash-panel--ink .dash-link { color: rgba(255,255,255,.7); }
.dash-link:hover { color: var(--huac-accent); border-bottom-color: var(--huac-accent); }
.dash-link--inline { font-family: var(--huac-mono); font-size: 12px; }

.dash-legend { display: flex; gap: 12px; }
.dash-legend span {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: var(--huac-mono); font-size: 10px;
    color: var(--huac-mute);
}
.dash-legend__sw { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }

/* ── DONUT LEGEND ─────────────────────────────────────── */
.dash-donut__legend {
    list-style: none; margin: 0; padding: 14px 22px 18px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.dash-donut__legend li {
    display: grid; grid-template-columns: auto 1fr auto;
    align-items: center; gap: 10px;
    padding: 5px 0;
    font-size: 13px;
    color: rgba(255,255,255,.85);
}
.dash-donut__sw { width: 10px; height: 10px; border-radius: 3px; }
.dash-donut__sw--activo       { background: #00B4D8; }
.dash-donut__sw--cancelado    { background: #6B7280; }
.dash-donut__sw--refinanciado { background: #F59E0B; }
.dash-donut__sw--eliminado    { background: #DC2626; }
.dash-donut__lbl { font-family: 'Poppins', sans-serif; }
.dash-donut__val {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 16px; color: #fff;
}

/* ── ALERTAS ──────────────────────────────────────────── */
.dash-alerts { list-style: none; margin: 0; padding: 0; }
.dash-alerts__row {
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    padding: 12px 22px;
    border-top: 1px solid var(--huac-rule-2);
    transition: background .2s ease;
}
.dash-alerts__row:hover { background: var(--huac-warn-bg); }
.dash-alerts__row:first-child { border-top: 0; }
.dash-alerts__main { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.dash-alerts__name {
    font-size: 13px; color: var(--huac-ink); font-weight: 500;
    text-decoration: none;
}
.dash-alerts__name:hover { color: var(--huac-warn); }
.dash-alerts__meta { font-family: var(--huac-mono); font-size: 10px; color: var(--huac-mute-2); }
.dash-alerts__num { text-align: right; }
.dash-alerts__val {
    display: block;
    font-family: var(--huac-serif); font-style: italic;
    font-size: 18px; color: var(--huac-ink); letter-spacing: -0.01em;
    font-feature-settings: 'tnum' 1;
}
.dash-alerts__days {
    display: inline-block; margin-top: 2px;
    font-family: var(--huac-mono); font-size: 10px;
    color: var(--huac-warn); font-weight: 600;
    background: var(--huac-warn-bg);
    padding: 2px 6px; border-radius: 3px;
}

/* ── TABLAS ───────────────────────────────────────────── */
.dash-table { width: 100%; margin: 0; border-collapse: collapse; }
.dash-table thead th {
    font-family: 'Poppins', sans-serif;
    font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase;
    color: var(--huac-mute); font-weight: 600;
    padding: 12px 18px; border-bottom: 1px solid var(--huac-rule);
    background: transparent;
}
.dash-table tbody td {
    padding: 13px 18px;
    font-size: 13px;
    border-bottom: 1px solid var(--huac-rule-2);
    color: var(--huac-ink);
    vertical-align: middle;
}
.dash-table tbody tr:last-child td { border-bottom: 0; }
.dash-table tbody tr:hover { background: rgba(0,155,220,.03); }
.dash-table__date {
    font-family: var(--huac-mono); font-size: 11px;
    color: var(--huac-mute); white-space: nowrap;
    text-transform: lowercase;
}
.dash-table__name { display: block; font-weight: 500; }
.dash-table__sub  { display: block; font-family: var(--huac-mono); font-size: 10px; color: var(--huac-mute-2); }
.dash-table__num {
    font-family: var(--huac-serif); font-style: italic;
    font-size: 16px; letter-spacing: -0.01em;
    font-feature-settings: 'tnum' 1;
    white-space: nowrap;
}

.dash-pill {
    display: inline-block;
    font-family: 'Poppins', sans-serif;
    font-size: 10px; letter-spacing: 0.08em;
    padding: 3px 9px; border-radius: 3px; font-weight: 600;
    text-transform: uppercase;
    background: #ECECF0; color: #4A4F5A;
}
.dash-pill.is-cap   { background: #E0F2FE; color: #075985; }
.dash-pill.is-int   { background: #FEF3C7; color: #92400E; }
.dash-pill.is-mor   { background: #FEE2E2; color: #991B1B; }
.dash-pill.is-active   { background: #DCFCE7; color: #166534; }
.dash-pill.is-cancel   { background: #E5E7EB; color: #4B5563; }
.dash-pill.is-refin    { background: #FEF3C7; color: #92400E; }
.dash-pill.is-elim     { background: #FEE2E2; color: #991B1B; }

.dash-empty {
    text-align: center; padding: 40px 20px;
    color: var(--huac-mute); font-size: 13px;
}
.dash-empty i { font-size: 32px; color: var(--huac-ok); display: block; margin-bottom: 8px; }
.dash-empty p { margin: 0; }
.dash-empty--row {
    text-align: center !important; padding: 32px 18px !important;
    color: var(--huac-mute) !important;
}

/* ── ANIMACIÓN DE ENTRADA ─────────────────────────────── */
.dash-anim {
    opacity: 0; transform: translateY(12px);
    animation: dashRise .55s cubic-bezier(.2,.7,.2,1) forwards;
    animation-delay: calc(var(--i, 0) * 70ms);
}
@keyframes dashRise {
    to { opacity: 1; transform: translateY(0); }
}

/* ── DARK MODE ────────────────────────────────────────── */
body.dark .huac-dashboard {
    --huac-paper:   #1a1c25;
    --huac-bone:    #242631;
    --huac-rule:    #3a3d4a;
    --huac-rule-2:  #2f323d;
    --huac-ink:     #F0F2F8;
    --huac-mute:    #A0A4B0;
    --huac-mute-2:  #6B6F7A;
}
body.dark .dash-table tbody tr:hover { background: rgba(0,155,220,.06); }
body.dark .dash-pill { background: #3a3d4a; color: #cbd0db; }

/* ── responsive paddings ──────────────────────────────── */
@media (max-width: 992px) {
    .dash-greeting { font-size: 26px; }
    .huac-dashboard { padding: 0 8px 24px; }
}
</style>

{{-- ════════════════════════ APEXCHARTS ════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    const chartData = {
        spark:  @json($spark7Vals),
        serie:  @json($cobranza30),
        donut: {
            labels: @json($cartera->pluck('situacion')->values()),
            data:   @json($cartera->pluck('n')->map(fn($v) => (int)$v)->values()),
        },
    };

    const colorFor = {
        'Activo':       '#00B4D8',
        'Cancelado':    '#6B7280',
        'Refinanciado': '#F59E0B',
        'Eliminado':    '#DC2626',
    };

    function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

    function build() {
        if (typeof ApexCharts === 'undefined') { setTimeout(build, 120); return; }

        const sparkEl = document.getElementById('dashSpark');
        if (sparkEl && !sparkEl.dataset.rendered) {
            new ApexCharts(sparkEl, {
                chart: { type: 'area', height: 70, sparkline: { enabled: true }, animations: { speed: 700 } },
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0, stops: [0, 100] }
                },
                colors: ['#00B4D8'],
                series: [{ name: 'Cobranza', data: chartData.spark }],
                tooltip: { theme: 'dark', x: { show: false }, y: { formatter: v => 'S/ ' + Number(v).toLocaleString('es-PE',{minimumFractionDigits:2}) } },
            }).render();
            sparkEl.dataset.rendered = '1';
        }

        const chartEl = document.getElementById('dashChart30');
        if (chartEl && !chartEl.dataset.rendered) {
            new ApexCharts(chartEl, {
                chart: {
                    type: 'area', height: 280,
                    toolbar: { show: false },
                    fontFamily: 'Poppins, sans-serif',
                    animations: { speed: 600 },
                    zoom: { enabled: false },
                },
                series: [{ name: 'Cobranza', data: chartData.serie }],
                stroke: { curve: 'smooth', width: 2.5 },
                colors: ['#0B1B3D'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 95] },
                    colors: ['#009BDC'],
                },
                dataLabels: { enabled: false },
                grid: { borderColor: '#ECECF0', strokeDashArray: 3, padding: { left: 10, right: 10 } },
                xaxis: {
                    type: 'datetime',
                    labels: { style: { colors: '#6B6F7A', fontSize: '10px', fontFamily: 'IBM Plex Mono, monospace' }, format: 'dd MMM' },
                    axisBorder: { show: false }, axisTicks: { show: false },
                },
                yaxis: {
                    labels: {
                        style: { colors: '#9AA0AA', fontSize: '10px', fontFamily: 'IBM Plex Mono, monospace' },
                        formatter: v => 'S/ ' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v.toFixed(0)),
                    },
                },
                tooltip: {
                    theme: 'light', x: { format: 'dd MMM yyyy' },
                    y: { formatter: v => 'S/ ' + Number(v).toLocaleString('es-PE',{minimumFractionDigits:2}) },
                    marker: { show: true },
                },
                markers: { size: 0, hover: { size: 5 } },
            }).render();
            chartEl.dataset.rendered = '1';
        }

        const donutEl = document.getElementById('dashDonut');
        if (donutEl && !donutEl.dataset.rendered && chartData.donut.data.length) {
            new ApexCharts(donutEl, {
                chart: { type: 'donut', height: 200, fontFamily: 'Poppins, sans-serif', animations: { speed: 700 } },
                series: chartData.donut.data,
                labels: chartData.donut.labels,
                colors: chartData.donut.labels.map(l => colorFor[l] || '#94A3B8'),
                stroke: { width: 0 },
                dataLabels: { enabled: false },
                legend: { show: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name:  { show: true, fontSize: '10px', fontFamily: 'Poppins, sans-serif', color: 'rgba(255,255,255,.6)', offsetY: 8 },
                                value: { show: true, fontSize: '28px', fontFamily: 'Instrument Serif, serif', fontStyle: 'italic', color: '#fff', offsetY: -10, formatter: v => v },
                                total: { show: true, label: 'Total', fontSize: '10px', color: 'rgba(255,255,255,.6)', formatter: w => w.globals.seriesTotals.reduce((a,b)=>a+b,0) },
                            }
                        }
                    }
                },
                tooltip: { theme: 'dark', y: { formatter: v => v + ' créditos' } },
            }).render();
            donutEl.dataset.rendered = '1';
        }
    }

    ready(build);
    if (window.Livewire) {
        window.Livewire.hook('morph.updated', build);
    }
})();
</script>
