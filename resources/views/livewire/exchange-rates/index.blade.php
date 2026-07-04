<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">TIPO DE CAMBIO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Configuración</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Tipo Cambio</a>
                </li>
            </ul>
        </div>
    </div>

    {{-- ─── CARD 1: TC vigente + Actualizar ─────────────────────────── --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    @if ($saved)
                        <div class="alert alert-success py-2">
                            <i class="ti ti-check"></i> Se actualizó el Tipo de Cambio con éxito
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-4 align-items-center">
                        {{-- TC vigente en grande --}}
                        <div class="col-lg-5">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h5 class="mb-0 fw-bold" style="color:#2874A6;">
                                    <i class="ti ti-currency-dollar"></i> Tipo de cambio vigente
                                </h5>
                                <span class="badge bg-light text-dark border">
                                    <i class="ti ti-calendar f-s-12"></i>
                                    {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '—' }}
                                </span>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="rounded text-center py-3" style="background:#e7f4ea;">
                                        <div class="small fw-semibold" style="color:#1e7b34;">COMPRA</div>
                                        <div class="fw-bold" style="font-size:2rem; line-height:1.1; color:#1e7b34;">
                                            {{ is_numeric($compra) ? number_format((float) $compra, 4) : '—' }}
                                        </div>
                                        <div class="small text-muted">te compran US$</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="rounded text-center py-3" style="background:#fdecea;">
                                        <div class="small fw-semibold" style="color:#c0392b;">VENTA</div>
                                        <div class="fw-bold" style="font-size:2rem; line-height:1.1; color:#c0392b;">
                                            {{ is_numeric($venta) ? number_format((float) $venta, 4) : '—' }}
                                        </div>
                                        <div class="small text-muted">te venden US$</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Formulario de actualización --}}
                        <div class="col-lg-7">
                            <div class="rounded p-3" style="background:#f2f4f7;">
                                <h6 class="fw-bold mb-2" style="color:#2874A6;">
                                    <i class="ti ti-pencil"></i> Actualizar tipo de cambio
                                </h6>
                                <form wire:submit.prevent="save">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label mb-0 small fw-semibold">Fecha</label>
                                            <input type="text" name="fecha" autocomplete="off" class="form-control form-control-sm dates" wire:model="fecha">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label mb-0 small fw-semibold">Venta</label>
                                            <input type="number" step="0.0001" min="0" name="venta" autocomplete="off" class="form-control form-control-sm"
                                                   placeholder="0.0000" wire:model="venta">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label mb-0 small fw-semibold">Compra</label>
                                            <input type="number" step="0.0001" min="0" name="compra" autocomplete="off" class="form-control form-control-sm"
                                                   placeholder="0.0000" wire:model="compra">
                                        </div>
                                        <div class="col-12 d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    wire:click="cargarDeSunat" wire:loading.attr="disabled" wire:target="cargarDeSunat">
                                                <i class="ti ti-cloud-download f-s-12"></i>
                                                <span wire:loading.remove wire:target="cargarDeSunat">Traer de SUNAT</span>
                                                <span wire:loading wire:target="cargarDeSunat">Consultando…</span>
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="ti ti-device-floppy f-s-12"></i> Actualizar
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <p class="small text-muted mb-0 mt-2">
                                    <i class="ti ti-info-circle"></i>
                                    <b>Traer de SUNAT</b> consulta la API oficial y llena los campos para que revises y pulses <b>Actualizar</b>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── CARDS 2 y 3: Conversor + Calculadora ───────────────────── --}}
    <div class="row mt-3">

        {{-- CONVERSOR --}}
        <div class="col-xl-7">
            <div class="card shadow-sm h-100" x-data="tcConverter({{ (float)($compra ?: 0) }}, {{ (float)($venta ?: 0) }})">
                <div class="card-body">
                    <h5 class="mb-3 fw-bold" style="color:#2874A6;">
                        <i class="ti ti-arrows-exchange"></i> Conversor de Moneda
                    </h5>

                    <div class="btn-group btn-group-sm mb-3 w-100" role="group">
                        <input type="radio" class="btn-check" id="dir-pen-usd" value="pen_usd" x-model="direction">
                        <label class="btn btn-outline-primary" for="dir-pen-usd">
                            S/ Soles <i class="ti ti-arrow-right f-s-12"></i> US$ Dólares
                        </label>
                        <input type="radio" class="btn-check" id="dir-usd-pen" value="usd_pen" x-model="direction">
                        <label class="btn btn-outline-primary" for="dir-usd-pen">
                            US$ Dólares <i class="ti ti-arrow-right f-s-12"></i> S/ Soles
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1 small fw-semibold">
                            Monto en <span x-text="direction === 'pen_usd' ? 'Soles' : 'Dólares'"></span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text fw-bold" style="min-width:64px; justify-content:center;"
                                  x-text="direction === 'pen_usd' ? 'S/' : 'US$'"></span>
                            <input type="number" step="0.01" min="0"
                                   class="form-control text-end fw-bold"
                                   style="font-size: 1.6rem; background:#fffbe6;"
                                   x-model.number="amount"
                                   placeholder="0.00">
                            <button type="button" class="btn btn-outline-primary" title="Invertir dirección"
                                    @click="direction = direction === 'pen_usd' ? 'usd_pen' : 'pen_usd'">
                                <i class="ti ti-switch-horizontal"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="rounded p-3 text-center h-100" style="background:#e7f4ea;">
                                <div class="small fw-semibold" style="color:#1e7b34;">AL TIPO COMPRA</div>
                                <div class="fw-bold" style="font-size: 1.6rem; color:#1e7b34;"
                                     x-text="result(compra)"></div>
                                <div class="small text-muted">1 US$ = <span x-text="compra.toFixed(4)"></span></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="rounded p-3 text-center h-100" style="background:#fdecea;">
                                <div class="small fw-semibold" style="color:#c0392b;">AL TIPO VENTA</div>
                                <div class="fw-bold" style="font-size: 1.6rem; color:#c0392b;"
                                     x-text="result(venta)"></div>
                                <div class="small text-muted">1 US$ = <span x-text="venta.toFixed(4)"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill"
                                @click="amount = 0">
                            <i class="ti ti-eraser f-s-12"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- CALCULADORA (diseño iOS) --}}
        <div class="col-xl-5 mt-3 mt-xl-0">
            <div class="card shadow-sm h-100" x-data="calc()">
                <div class="card-body">
                    <h5 class="mb-2 fw-bold" style="color:#2874A6;">
                        <i class="ti ti-calculator"></i> Calculadora
                    </h5>

                    <div class="ios-calc mx-auto">
                        {{-- Expresión EDITABLE (línea gris arriba del resultado, como el
                             historial de iOS). Es <textarea> para que haga wrap y
                             auto-ajuste su alto al contenido. --}}
                        <textarea
                               class="ios-calc-expr"
                               rows="1"
                               x-model="expr"
                               x-init="$watch('expr', () => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'; }); $nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'; })"
                               placeholder="ej: 20 + 30 + 90 - 30"
                               @keydown.enter.prevent="equal()"
                               @keydown.escape="clear()"></textarea>

                        {{-- Resultado --}}
                        <div class="ios-calc-display" x-text="display"></div>

                        {{-- Teclado: AC se vuelve ⌫ cuando hay algo escrito (como iOS 18) --}}
                        <div class="ios-calc-grid">
                            <button type="button" class="ios-btn fn" :title="expr === '' ? 'Borrar todo' : 'Borrar último (Esc limpia todo)'"
                                    @click="expr === '' ? clear() : back()">
                                <span x-show="expr === ''">AC</span>
                                <i class="ti ti-backspace" x-show="expr !== ''" x-cloak></i>
                            </button>
                            <button type="button" class="ios-btn fn" @click="negate()">±</button>
                            <button type="button" class="ios-btn fn" @click="percent()">%</button>
                            <button type="button" class="ios-btn op" @click="op('/')">÷</button>

                            <button type="button" class="ios-btn" @click="push('7')">7</button>
                            <button type="button" class="ios-btn" @click="push('8')">8</button>
                            <button type="button" class="ios-btn" @click="push('9')">9</button>
                            <button type="button" class="ios-btn op" @click="op('*')">×</button>

                            <button type="button" class="ios-btn" @click="push('4')">4</button>
                            <button type="button" class="ios-btn" @click="push('5')">5</button>
                            <button type="button" class="ios-btn" @click="push('6')">6</button>
                            <button type="button" class="ios-btn op" @click="op('-')">−</button>

                            <button type="button" class="ios-btn" @click="push('1')">1</button>
                            <button type="button" class="ios-btn" @click="push('2')">2</button>
                            <button type="button" class="ios-btn" @click="push('3')">3</button>
                            <button type="button" class="ios-btn op" @click="op('+')">+</button>

                            <button type="button" class="ios-btn zero" @click="push('0')">0</button>
                            <button type="button" class="ios-btn" @click="push('.')">.</button>
                            <button type="button" class="ios-btn op" @click="equal()">=</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        .ios-calc {
            background: #000;
            border-radius: 1.75rem;
            padding: 1.25rem 1rem 1rem;
            width: 100%;
            max-width: 340px;
            font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
        }
        .ios-calc-expr {
            width: 100%;
            background: transparent;
            border: 0;
            outline: none;
            resize: none;
            overflow: hidden;
            color: #8e8e93;
            text-align: right;
            font-size: 1.05rem;
            line-height: 1.3;
            font-variant-numeric: tabular-nums;
        }
        .ios-calc-expr::placeholder { color: #48484a; }
        .ios-calc-display {
            color: #fff;
            text-align: right;
            font-size: 2.6rem;
            font-weight: 300;
            line-height: 1.15;
            min-height: 3rem;
            padding: 0 .25rem .75rem;
            word-break: break-all;
            font-variant-numeric: tabular-nums;
        }
        .ios-calc-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .65rem;
        }
        .ios-btn {
            border: 0;
            border-radius: 50%;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            font-weight: 400;
            background: #333333;
            color: #fff;
            transition: background-color .12s;
            -webkit-tap-highlight-color: transparent;
        }
        .ios-btn:active { background: #737373; }
        .ios-btn.fn { background: #a5a5a5; color: #000; font-size: 1.25rem; }
        .ios-btn.fn:active { background: #d9d9d9; }
        .ios-btn.op { background: #ff9f0a; color: #fff; font-size: 1.6rem; font-weight: 500; }
        .ios-btn.op:active { background: #fcc78d; }
        .ios-btn.zero {
            aspect-ratio: auto;
            grid-column: span 2;
            border-radius: 999px;
            justify-content: flex-start;
            padding-left: 1.7rem;
        }
    </style>

    <span id="final"></span>
</div>

@script
<script>
window.tcConverter = function (compra, venta) {
    return {
        compra: compra,
        venta:  venta,
        direction: 'pen_usd',
        amount: 0,
        result(rate) {
            if (!rate || !this.amount) return this.direction === 'pen_usd' ? 'US$ 0.00' : 'S/ 0.00';
            const v = this.direction === 'pen_usd' ? this.amount / rate : this.amount * rate;
            return (this.direction === 'pen_usd' ? 'US$ ' : 'S/ ') + v.toFixed(2);
        },
    };
};

window.calc = function () {
    return {
        expr: '',

        // Display reactivo: muestra el resultado preview en vivo si la expresión
        // es válida; sino muestra '0' (o lo que sea).
        get display() {
            if (this.expr.trim() === '') return '0';
            const r = this.evalExpr(this.expr);
            return r === null ? '…' : this.fmt(r);
        },

        push(ch) {
            // Si lo último es un operador y el char nuevo también es operador,
            // reemplazar (evita "5 + - 3").
            if (ch === '.') {
                // evita doble punto en el mismo número
                const lastNum = this.expr.split(/[+\-*/]/).pop() || '';
                if (lastNum.includes('.')) return;
            }
            this.expr += ch;
        },

        op(o) {
            const t = this.expr.trimEnd();
            // si está vacío y se aprieta operador (excepto -), ignorar
            if (t === '' && o !== '-') return;

            // si lo último ya es un operador, reemplazarlo
            const last = t.slice(-1);
            if ('+-*/'.includes(last)) {
                this.expr = t.slice(0, -1).trimEnd() + ' ' + o + ' ';
            } else {
                this.expr = t + ' ' + o + ' ';
            }
        },

        back() {
            this.expr = this.expr.replace(/(\s+|.)$/, '');
        },

        clear() {
            this.expr = '';
        },

        negate() {
            // Negar el último número de la expresión
            const m = this.expr.match(/(-?\d*\.?\d*)$/);
            if (!m || !m[0]) return;
            const num = m[0];
            const idx = this.expr.length - num.length;
            const negated = num.startsWith('-') ? num.slice(1) : '-' + num;
            this.expr = this.expr.slice(0, idx) + negated;
        },

        percent() {
            // Divide el último número por 100
            const m = this.expr.match(/(\d*\.?\d+)$/);
            if (!m) return;
            const num = parseFloat(m[0]);
            this.expr = this.expr.slice(0, -m[0].length) + (num / 100);
        },

        equal() {
            const r = this.evalExpr(this.expr);
            if (r === null) return;
            this.expr = this.fmt(r);
        },

        /** Evalúa una expresión solo con números, +-*\/ y espacios. Retorna null si inválida. */
        evalExpr(s) {
            const cleaned = String(s).replace(/×/g, '*').replace(/÷/g, '/').replace(/−/g, '-');
            if (!/^[\d\.\+\-\*\/\s]+$/.test(cleaned)) return null;
            if (/[+\-*/]\s*$/.test(cleaned.trim())) return null; // termina en operador
            try {
                // eslint-disable-next-line no-new-func
                const r = Function('return (' + cleaned + ')')();
                if (typeof r !== 'number' || !isFinite(r)) return null;
                return r;
            } catch (e) { return null; }
        },

        fmt(n) {
            // Hasta 8 decimales, sin ceros sobrantes
            return String(Math.round(n * 1e8) / 1e8);
        },
    };
};
</script>
@endscript
