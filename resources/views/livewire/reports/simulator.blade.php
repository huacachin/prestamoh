{{-- 10/10 (Antony): botón Copiar bajo cada columna del Detalle de Pago. Copia al portapapeles
     "24 Cuotas semanales de 379.20": la cuota redondeada hacia ARRIBA a 0.10 (379.18 → 379.20),
     el mismo redondeo de la cuota uniforme con el que se arma el cronograma real.
     OJO: dentro de x-data solo comillas simples (una doble rompe el atributo). --}}
{{-- 10/10 (Antony): los Detalles de Pago son VENTANITAS movibles y se pueden abrir varias a la
     vez (una por mes), sin fondo, para comparar sin perder la anterior. Salen en cascada, la
     que se toca pasa al frente, Esc cierra la de arriba, "Cerrar ventanas" las quita todas y
     una simulación nueva (Procesar) las cierra. El arrastre es `ventanaLibre`
     (assets/js/ventanas-flotantes.js). --}}
<div class="container-fluid" x-data="{
        ventanas: [], seq: 0, topZ: 1080, copiado: '',
        abrir(m, n) {
            const monto = parseFloat(m) || 0, meses = parseInt(n) || 0;
            const ya = this.ventanas.find(v => v.meses === meses);
            if (ya) { this.alFrente(ya); return; }
            const k = this.ventanas.length % 8;
            this.ventanas.push({ id: ++this.seq, monto: monto, meses: meses, x: 90 + k * 30, y: 90 + k * 30, z: ++this.topZ });
        },
        cerrar(id) { this.ventanas = this.ventanas.filter(v => v.id !== id); },
        cerrarTodas() { this.ventanas = []; },
        cerrarUltima() { if (!this.ventanas.length) return; const top = this.ventanas.reduce((a, b) => a.z >= b.z ? a : b); this.cerrar(top.id); },
        alFrente(v) { if (v.z !== this.topZ) v.z = ++this.topZ; },
        fmt(v) { return Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        redondear(v) { return Math.ceil(Math.round(v / 0.10 * 1000000) / 1000000) * 0.10; },
        cuotas(v, tipo) {
            const n = tipo === 'mensual' ? v.meses : (tipo === 'semanal' ? 4 * v.meses : 4 * v.meses * 6);
            const c = tipo === 'mensual' ? v.monto : (tipo === 'semanal' ? v.monto / 4 : v.monto / 4 / 6);
            const adj = tipo === 'mensual' ? (n === 1 ? 'mensual' : 'mensuales') : (tipo === 'semanal' ? (n === 1 ? 'semanal' : 'semanales') : (n === 1 ? 'diaria' : 'diarias'));
            return n + ' ' + (n === 1 ? 'Cuota' : 'Cuotas') + ' ' + adj + ' de ' + this.fmt(this.redondear(c));
        },
        async copiar(v, tipo) {
            const t = this.cuotas(v, tipo), clave = v.id + ':' + tipo;
            try { await navigator.clipboard.writeText(t); }
            catch (e) { const ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); }
            this.copiado = clave;
            setTimeout(() => { if (this.copiado === clave) this.copiado = ''; }, 1500);
        }
     }"
     x-on:keydown.escape.window="cerrarUltima()"
     x-on:simulacion-nueva.window="cerrarTodas()">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">SIMULACRO DE CREDITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Simulador</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- Form --}}
                    <form wire:submit.prevent="simulate">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label mb-1"><b>Nombre</b></label>
                                <input type="text" name="nombre" autocomplete="name" class="form-control"
                                       wire:model="nombre" placeholder="Nombres"
                                       style="background-color: yellow; font-weight: 600;">
                                @error('nombre') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1"><b>Capital</b></label>
                                <input type="number" step="0.01" min="1" name="capital" autocomplete="off" class="form-control"
                                       wire:model="capital" placeholder="Capital"
                                       style="background-color: yellow; font-weight: 600;">
                                @error('capital') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1"><b>%</b></label>
                                <input type="number" step="0.01" min="0" name="interes" autocomplete="off" class="form-control"
                                       wire:model="interes" placeholder="%"
                                       style="background-color: yellow; font-weight: 600;">
                                @error('interes') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-calculator f-s-14"></i> Procesar
                                </button>
                                @if($hasResult)
                                    <a href="{{ route('exports.reports.simulator', ['capital' => $capital, 'tasa' => $interes, 'nombre' => $nombre, 'meses' => 60]) }}"
                                       target="_blank"
                                       class="btn btn-success">
                                        <i class="ti ti-file-spreadsheet f-s-14"></i> Excel
                                    </a>
                                @endif
                                <button type="button" class="btn btn-secondary" onclick="window.print()">
                                    <i class="ti ti-printer f-s-14"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </form>

                    @if($hasResult && count($mensual) > 0)
                        <div id="printme">
                            {{-- Header info --}}
                            <div class="row mb-2 px-2">
                                <div class="col-md-4"><b>Nombre:</b> {{ $nombre ?: '-' }}</div>
                                <div class="col-md-4"><b>Importe:</b> S/ {{ number_format((float)$capital, 2) }}</div>
                                <div class="col-md-4"><b>Interés:</b> {{ $interes }}%
                                    <button type="button" class="btn btn-xs btn-outline-secondary ms-2" style="padding: 2px 8px; font-size: 10px;"
                                            x-show="ventanas.length > 0" x-cloak @click="cerrarTodas()">
                                        <i class="ti ti-x"></i> Cerrar ventanas (<span x-text="ventanas.length"></span>)
                                    </button>
                                </div>
                            </div>

                            {{-- INTERES MENSUAL --}}
                            <h5 style="color:red; text-align:center;"><b>INTERES MENSUAL</b></h5>

                            @foreach($bloques as $bloque)
                                @php [$from, $to] = $bloque; @endphp
                                <table class="table table-bordered table-sm mb-2 sim-table">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center" style="background-color:#5bc0de;">Monto</th>
                                            @for($i = $from; $i <= $to; $i++)
                                                <th colspan="2" class="text-center" style="background-color:#5bc0de;">Mes {{ $i }}</th>
                                            @endfor
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="2" rowspan="2" class="text-center align-middle fw-bold">
                                                {{ number_format((float)$capital, 2) }}
                                            </td>
                                            @for($i = $from; $i <= $to; $i++)
                                                <td class="text-center">Pagar</td>
                                                <td class="text-center" style="color:red;">Mora</td>
                                            @endfor
                                        </tr>
                                        <tr>
                                            @for($i = $from; $i <= $to; $i++)
                                                <td class="text-end">
                                                    <a href="#" class="text-primary fw-semibold text-decoration-underline"
                                                       @click.prevent="abrir({{ $mensual[$i]['pagar'] }}, {{ $i }})"
                                                       title="Ver detalle (se abre en una ventanita; puedes abrir varias)">{{ number_format($mensual[$i]['pagar'], 2) }}</a>
                                                </td>
                                                <td class="text-end" style="color:red;">{{ number_format($mensual[$i]['mora'], 2) }}</td>
                                            @endfor
                                        </tr>
                                    </tbody>
                                </table>
                            @endforeach

                            {{-- INTERES SEMANAL --}}
                            <h5 style="color:red; text-align:center; margin-top:20px;"><b>INTERES SEMANAL</b></h5>

                            @foreach($bloques as $bloque)
                                @php [$from, $to] = $bloque; @endphp
                                <table class="table table-bordered table-sm mb-2 sim-table">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center" style="background-color:#5bc0de;">Monto</th>
                                            @for($i = $from; $i <= $to; $i++)
                                                <th colspan="2" class="text-center" style="background-color:#5bc0de;">Mes {{ $i }}</th>
                                            @endfor
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="2" rowspan="2" class="text-center align-middle fw-bold">
                                                {{ number_format((float)$capital, 2) }}
                                            </td>
                                            @for($i = $from; $i <= $to; $i++)
                                                <td class="text-center">Pagar</td>
                                                <td class="text-center" style="color:red;">Mora</td>
                                            @endfor
                                        </tr>
                                        <tr>
                                            @for($i = $from; $i <= $to; $i++)
                                                <td class="text-end">{{ number_format($semanal[$i]['pagar'], 2) }}</td>
                                                <td class="text-end" style="color:red;">{{ number_format($semanal[$i]['mora'], 2) }}</td>
                                            @endfor
                                        </tr>
                                    </tbody>
                                </table>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Ventanitas de Detalle de Pago (réplica del popup legacy cuenta_por_cobrar_pagado.php),
         una por mes abierto. Teletransportadas al <body> para que position:fixed se ancle al
         viewport aunque algún ancestro del layout tenga transform. --}}
    <template x-teleport="body">
        <div class="sim-ventanas">
            <template x-for="v in ventanas" :key="v.id">
                <div x-data="ventanaLibre({ x: v.x, y: v.y, w: 440 })" class="card shadow sim-ventana"
                     :style="{ zIndex: v.z }" x-on:pointerdown="alFrente(v)">
                    <div class="card-header d-flex justify-content-between align-items-center py-2 sim-ventana-cabecera"
                         style="background:#009bdc; color:#fff;"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                        <b style="font-size:13px;">Detalle de Pago · Mes <span x-text="v.meses"></span> · S/ <span x-text="fmt(v.monto)"></span></b>
                        <a href="#" x-on:click.prevent="cerrar(v.id)" title="Cerrar (Esc cierra la de arriba)" style="color:#fff; text-decoration:none; font-size:20px; line-height:1;">&times;</a>
                    </div>
                    <div class="card-body p-2">
                        <table class="table table-bordered table-sm mb-0 text-center sim-table" style="font-size:12px;">
                            <thead>
                                <tr>
                                    <th colspan="2" style="background:#009bdc;color:#fff;">MENSUAL</th>
                                    <th colspan="2" style="background:#999191;color:#fff;">SEMANAL</th>
                                    <th colspan="2" style="background:#009bdc;color:#fff;">DIARIO</th>
                                </tr>
                                <tr>
                                    <th style="background:#009bdc;color:#fff;">Nº</th><th style="background:#009bdc;color:#fff;">S/</th>
                                    <th style="background:#999191;color:#fff;">Nº</th><th style="background:#999191;color:#fff;">S/</th>
                                    <th style="background:#009bdc;color:#fff;">Nº</th><th style="background:#009bdc;color:#fff;">S/</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b style="color:red;" x-text="v.meses"></b></td>
                                    <td><b x-text="fmt(v.monto)"></b></td>
                                    <td><b style="color:red;" x-text="4*v.meses"></b></td>
                                    <td><b x-text="fmt(v.monto/4)"></b></td>
                                    <td><b style="color:red;" x-text="4*v.meses*6"></b></td>
                                    <td><b x-text="fmt(v.monto/4/6)"></b></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><b x-text="fmt(v.monto*v.meses)"></b></td>
                                    <td colspan="2"><b x-text="fmt((v.monto/4)*(4*v.meses))"></b></td>
                                    <td colspan="2"><b x-text="fmt((v.monto/4/6)*(4*v.meses*6))"></b></td>
                                </tr>
                                {{-- Copiar "N Cuotas … de S/" al portapapeles, una por columna --}}
                                <tr>
                                    @foreach(['mensual', 'semanal', 'diario'] as $tipo)
                                        <td colspan="2">
                                            <button type="button" class="btn btn-xs btn-outline-primary copiar-cuotas" style="padding: 2px 8px; font-size: 10px;"
                                                    x-on:click="copiar(v, '{{ $tipo }}')" :title="cuotas(v, '{{ $tipo }}')">
                                                <i class="ti" :class="copiado === v.id + ':{{ $tipo }}' ? 'ti-check' : 'ti-copy'"></i>
                                                <span x-text="copiado === v.id + ':{{ $tipo }}' ? 'Copiado' : 'Copiar'"></span>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

<style>
    [x-cloak] { display: none !important; }
    /* Celdas compactas (el legacy usa celdas chicas) */
    .sim-table > :not(caption) > * > * { padding: 2px 6px !important; }
    /* Ventanitas de detalle: fijas, varias a la vez, arrastrables desde la cabecera */
    /* transition: none → el tema anima toda .card (--app-transition) y la ventana iba detrás del mouse */
    .sim-ventana { position: fixed; width: 440px; max-width: 95vw; margin: 0; transition: none !important; }
    .sim-ventana-cabecera { cursor: grab; user-select: none; -webkit-user-select: none; touch-action: none; }
    .sim-ventana-cabecera:active { cursor: grabbing; }
    @media print {
        .breadcrumb, .btn, form, .sim-ventanas { display: none !important; }
        #printme { width: 100%; }
    }
</style>
