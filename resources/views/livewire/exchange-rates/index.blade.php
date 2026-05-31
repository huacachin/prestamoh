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

    {{-- ─── CARD 1: Actualizar TC ──────────────────────────────────── --}}
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

                    <form wire:submit.prevent="save">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Fecha</b></label>
                                <input type="text" autocomplete="off" class="form-control form-control-sm dates" wire:model="fecha">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Venta</b></label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm"
                                       placeholder="0.0000" wire:model="venta">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Compra</b></label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm"
                                       placeholder="0.0000" wire:model="compra">
                            </div>
                            <div class="col-md-auto d-flex gap-2">
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

                    <p class="small text-muted mb-0">
                        <i class="ti ti-info-circle"></i>
                        <b>Traer de SUNAT</b> consulta la API oficial (vía migo.pe) y llena los campos automáticamente para que revises y luego pulses <b>Actualizar</b>.
                    </p>
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
                            <i class="ti ti-arrow-right f-s-12"></i> Soles → Dólares
                        </label>
                        <input type="radio" class="btn-check" id="dir-usd-pen" value="usd_pen" x-model="direction">
                        <label class="btn btn-outline-primary" for="dir-usd-pen">
                            <i class="ti ti-arrow-right f-s-12"></i> Dólares → Soles
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1 small fw-semibold">
                            Monto en <span x-text="direction === 'pen_usd' ? 'Soles (S/)' : 'Dólares (US$)'"></span>
                        </label>
                        <input type="number" step="0.01" min="0"
                               class="form-control form-control-lg text-end fw-bold"
                               style="font-size: 1.4rem;"
                               x-model.number="amount"
                               placeholder="0.00">
                    </div>

                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Compra: <b x-text="compra.toFixed(4)" class="text-success"></b></span>
                        <span>Venta: <b x-text="venta.toFixed(4)" class="text-danger"></b></span>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Al tipo <b>Compra</b></div>
                                <div class="fw-bold text-success" style="font-size: 1.4rem;"
                                     x-text="result(compra)"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="small text-muted">Al tipo <b>Venta</b></div>
                                <div class="fw-bold text-danger" style="font-size: 1.4rem;"
                                     x-text="result(venta)"></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill"
                                @click="amount = 0">
                            <i class="ti ti-eraser f-s-12"></i> Limpiar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary flex-fill"
                                @click="direction = direction === 'pen_usd' ? 'usd_pen' : 'pen_usd'">
                            <i class="ti ti-switch-horizontal f-s-12"></i> Invertir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- CALCULADORA --}}
        <div class="col-xl-5 mt-3 mt-xl-0">
            <div class="card shadow-sm h-100" x-data="calc()">
                <div class="card-body">
                    <h5 class="mb-2 fw-bold" style="color:#2874A6;">
                        <i class="ti ti-calculator"></i> Calculadora
                    </h5>

                    {{-- Expresión EDITABLE con estilo display (negro/blanco, monospace).
                         Es <textarea> para que ocupe toda la línea y haga wrap si
                         se queda sin espacio. Auto-ajusta su alto al contenido. --}}
                    <label class="form-label mb-1 small text-muted">
                        Expresión <span class="text-info">(editable)</span>
                    </label>
                    <textarea
                           class="form-control text-end fw-bold mb-2 bg-dark text-white border-0"
                           style="font-family: 'Courier New', monospace; font-size: 1.05rem; resize: none; overflow: hidden; line-height: 1.3;"
                           rows="1"
                           x-model="expr"
                           x-init="$watch('expr', () => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'; }); $nextTick(() => { $el.style.height='auto'; $el.style.height=$el.scrollHeight+'px'; })"
                           placeholder="ej: 20 + 30 + 90 - 30"
                           @keydown.enter.prevent="equal()"
                           @keydown.escape="clear()"></textarea>

                    {{-- Resultado --}}
                    <div class="bg-dark text-white text-end px-3 py-3 mb-2 rounded"
                         style="font-family: 'Courier New', monospace; font-size: 1.8rem; min-height: 60px; line-height: 1;">
                        <span x-text="display"></span>
                    </div>

                    {{-- Teclado --}}
                    <div class="d-grid gap-2" style="grid-template-columns: repeat(4, 1fr); display: grid;">
                        <button type="button" class="btn btn-warning"  @click="clear()">C</button>
                        <button type="button" class="btn btn-warning"  @click="back()"><i class="ti ti-backspace"></i></button>
                        <button type="button" class="btn btn-warning"  @click="percent()">%</button>
                        <button type="button" class="btn btn-primary"  @click="op('/')">÷</button>

                        <button type="button" class="btn btn-light"    @click="push('7')">7</button>
                        <button type="button" class="btn btn-light"    @click="push('8')">8</button>
                        <button type="button" class="btn btn-light"    @click="push('9')">9</button>
                        <button type="button" class="btn btn-primary"  @click="op('*')">×</button>

                        <button type="button" class="btn btn-light"    @click="push('4')">4</button>
                        <button type="button" class="btn btn-light"    @click="push('5')">5</button>
                        <button type="button" class="btn btn-light"    @click="push('6')">6</button>
                        <button type="button" class="btn btn-primary"  @click="op('-')">−</button>

                        <button type="button" class="btn btn-light"    @click="push('1')">1</button>
                        <button type="button" class="btn btn-light"    @click="push('2')">2</button>
                        <button type="button" class="btn btn-light"    @click="push('3')">3</button>
                        <button type="button" class="btn btn-primary"  @click="op('+')">+</button>

                        <button type="button" class="btn btn-light"    @click="negate()">±</button>
                        <button type="button" class="btn btn-light"    @click="push('0')">0</button>
                        <button type="button" class="btn btn-light"    @click="push('.')">.</button>
                        <button type="button" class="btn btn-success"  @click="equal()">=</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
