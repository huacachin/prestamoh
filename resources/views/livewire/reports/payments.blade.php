{{-- 22/10 (Antony): la raíz lleva el visor flotante de las fotos del depósito (partial _lightbox,
     contrato open / idx / items / close / next / prev, la misma cámara de ingresos/egresos con la
     fila 'congelada' mientras se miran las fotos) y las ventanitas de recibos. --}}
<div class="container-fluid"
     x-data="{
        open: false, idx: 0, items: [], selectedId: null,
        verVouchers(items, rowId = null) {
            if (! items || ! items.length) return;
            this.items = items; this.idx = 0; this.open = true;
            if (rowId !== null) this.selectedId = rowId;
        },
        deselect(e) {
            if (this.selectedId === null) return;
            if (e.target.closest('.huac-lb') || e.target.closest('[data-foto-fila]')) return;
            this.selectedId = null;
        },
        close() { this.open = false; },
        next() { this.idx = (this.idx + 1) % this.items.length; },
        prev() { this.idx = (this.idx - 1 + this.items.length) % this.items.length; },
        // Recibos del sistema: varias ventanitas a la vez (como las del simulador), en cascada;
        // la tocada pasa al frente, Esc cierra la de arriba. Mientras haya recibos abiertos de
        // un cobro, su fila va en amarillo (conRecibo).
        recibos: [], seqRecibo: 0, topZ: 1080,
        verRecibo(lote, url, titulo, n) {
            const ya = this.recibos.find(v => v.lote === lote);
            if (ya) { this.alFrente(ya); return; }
            const k = this.recibos.length % 8;
            this.recibos.push({ id: ++this.seqRecibo, lote: lote, url: url, titulo: titulo, n: n, x: 120 + k * 30, y: 70 + k * 30, z: ++this.topZ });
        },
        cerrarRecibo(id) { this.recibos = this.recibos.filter(v => v.id !== id); },
        cerrarRecibos() { this.recibos = []; },
        cerrarUltimoRecibo() {
            if (this.open || ! this.recibos.length) return;
            const top = this.recibos.reduce((a, b) => a.z >= b.z ? a : b);
            this.cerrarRecibo(top.id);
        },
        alFrente(v) { if (v.z !== this.topZ) v.z = ++this.topZ; },
        conRecibo(n) { return this.recibos.some(v => v.n === n); },
     }"
     @click.window="deselect($event)"
     x-on:keydown.escape.window="cerrarUltimoRecibo()">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE DE PAGO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Pagos</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros: Buscar por --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-3 py-1">
                                <div class="d-flex flex-column">
                                    <label class="form-label mb-1 small fw-semibold">BUSCAR X</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="1" id="tipo1">
                                            <label class="form-check-label" for="tipo1">A/</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="2" id="tipo2">
                                            <label class="form-check-label" for="tipo2">Motivo</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="3" id="tipo3">
                                            <label class="form-check-label" for="tipo3">Asesor</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="4" id="tipo4">
                                            <label class="form-check-label" for="tipo4">Usuario</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros: texto + fechas --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 py-1">
                                <div class="flex-grow-1" style="min-width: 250px; max-width:400px;">
                                    <label class="form-label mb-0 small">Texto</label>
                                    <input type="text" name="compra" autocomplete="off" class="form-control form-control-sm"
                                           wire:model.live.debounce.500ms="compra"
                                           placeholder="Ingrese el texto a buscar"
       list="hist_reporte_pagos" data-search-history="reporte_pagos">
<datalist id="hist_reporte_pagos" wire:ignore></datalist>
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Inicio</label>
                                    <input type="text" name="fei" autocomplete="off" class="form-control form-control-sm dates" wire:model.live.debounce.500ms="fei">
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Fin</label>
                                    <input type="text" name="fef" autocomplete="off" class="form-control form-control-sm dates" wire:model.live.debounce.500ms="fef">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <a href="{{ route('exports.reports.payments', ['tipo' => $tipo, 'compra' => $compra, 'fei' => $fei, 'fef' => $fef, 'cliente' => $clienteId, 'credito' => $creditoId]) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-success flex-shrink-0">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-dark flex-shrink-0"
                                        x-show="recibos.length > 0" x-cloak x-on:click="cerrarRecibos()">
                                    <i class="ti ti-x"></i> Cerrar recibos (<span x-text="recibos.length"></span>)
                                </button>
                                <x-scroll-bottom-btn scrollable="#tabla-pagos-rep" />
                            </div>
                            {{-- Filtro por crédito o cliente (26/09, viene del cronograma / ficha):
                                 en su propia línea, alineado con los filtros. Con crédito, el reporte
                                 trae SOLO los pagos de ese crédito. --}}
                            @if($creditoFiltrado || $clienteFiltrado)
                                <div class="d-flex align-items-center gap-2 py-1 small flex-wrap">
                                    @if($creditoFiltrado)
                                        <span class="text-muted">Crédito:</span>
                                        <a href="{{ route('credits.show', $creditoFiltrado->id) }}" class="badge bg-dark text-decoration-none" style="font-size:11px;" title="Ir al crédito">
                                            <i class="ti ti-file-invoice"></i> #{{ $creditoFiltrado->id }}
                                        </a>
                                    @endif
                                    @if($clienteFiltrado)
                                        <span class="text-muted">Cliente:</span>
                                        <span class="badge bg-dark" style="font-size:11px;">
                                            <i class="ti ti-user"></i> {{ $clienteFiltrado->fullName() }}
                                        </span>
                                    @endif
                                    <a href="#" class="text-danger text-decoration-none" wire:click.prevent="quitarFiltro"
                                       title="Quitar el filtro (vuelve al reporte de hoy)">
                                        <i class="ti ti-x"></i> quitar filtro
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tabla --}}
                    <style>
                        /* Fila con las fotos abiertas (mismo verde que ingresos/egresos); sin la cebra
                           ni el hover de Bootstrap encima (box-shadow interior) para que el color sea exacto. */
                        #tabla-pagos-rep tbody tr.fila-sel > td {
                            background-color: #CCFF66 !important;
                            --bs-table-bg-type: transparent; --bs-table-accent-bg: transparent; --bs-table-bg-state: transparent; box-shadow: none;
                        }
                    </style>
                    <div id="tabla-pagos-rep" class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover table-autofit">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center">Nº</th>
                                    {{-- 23/10 (Antony): la foto del depósito va como SEGUNDA columna, tras el Nº
                                         (cámara de ingresos/egresos, sin miniatura ni contador; abre la ventana movible). --}}
                                    <th class="text-center" style="background:#949696;" title="Foto del depósito"><i class="ti ti-camera"></i></th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center" style="background:#949696;">Hora</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center" style="background:#949696;">Asesor</th>
                                    <th>A</th>
                                    <th style="background:#949696;">Motivo</th>
                                    <th class="text-center">S/.</th>
                                    {{-- 22/10 (Antony): "Voucher" = el recibo que genera el sistema por cada
                                         cobro (uno por lote), en la ventana flotante del recibo con "Copiar imagen". --}}
                                    <th class="text-center" style="background:#949696;">Voucher</th>
                                    @if($isAdmin)
                                        {{-- Como en el legacy, solo para los niveles altos: el lugar desde donde
                                             se registró el pago (22/10: ubicación obligatoria al cobrar). --}}
                                        <th class="text-center">Map</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr :class="{ 'fila-sel': selectedId === {{ $row['n'] }}, 'fila-recibo': conRecibo({{ $row['n'] }}) }">
                                        <td class="text-center">{{ $row['n'] }}</td>
                                        <td class="text-center" style="white-space:nowrap;">
                                            @if($row['vouchers'])
                                                {{-- Misma cámara que ingresos/egresos, con el número al costado (Antony,
                                                     23/10). Los items van inline para que Livewire los regenere en cada
                                                     filtro; la fila queda 'congelada' mientras se miran las fotos. --}}
                                                @php $nV = count($row['vouchers']); @endphp
                                                <a href="#" data-foto-fila style="cursor: zoom-in;"
                                                   title="Ver foto del depósito ({{ $nV }})"
                                                   x-on:click.prevent="verVouchers(@js($row['vouchers']), {{ $row['n'] }})">
                                                    <i class="ti ti-camera f-s-16 text-info"></i>
                                                    <span class="badge bg-info" style="font-size:9px; padding:1px 4px;">{{ $nV }}</span>
                                                </a>
                                            @else
                                                {{-- Sin foto: cámara tachada en gris, para que se note cuál no tiene. --}}
                                                <i class="ti ti-camera-off f-s-16 text-muted" title="Sin foto del depósito"></i>
                                            @endif
                                        </td>
                                        <td>{{ $row['fecha'] }}</td>
                                        <td>{{ $row['hora'] }}</td>
                                        <td>{{ $row['usuario'] }}</td>
                                        <td>{{ $row['asesor'] }}</td>
                                        <td>{{ $row['cliente'] }}</td>
                                        <td>{{ $row['detalle'] }}</td>
                                        <td class="text-end">{{ number_format($row['monto'], 2) }}</td>
                                        <td class="text-center" style="white-space:nowrap;">
                                            {{-- Un recibo por lote del día; con varios, cada ícono lleva su hora.
                                                 Cada clic abre una ventanita más (pueden verse varios a la vez). --}}
                                            @foreach($row['recibos'] as $rec)
                                                <a href="#" class="{{ $loop->last ? '' : 'me-2' }}"
                                                   title="Ver recibo{{ count($row['recibos']) > 1 ? ' de las '.$rec['hora'] : '' }} (S/ {{ number_format($rec['monto'], 2) }})"
                                                   x-on:click.prevent="verRecibo({{ $rec['lote'] }}, @js($rec['url']), @js('Recibo #'.str_pad((string) $rec['lote'], 6, '0', STR_PAD_LEFT).' · '.$rec['hora'].' · S/ '.number_format($rec['monto'], 2)), {{ $row['n'] }})">
                                                    <i class="ti ti-receipt f-s-16 text-primary"></i>
                                                </a>
                                            @endforeach
                                        </td>
                                        @if($isAdmin)
                                            <td class="text-center" style="white-space:nowrap;">
                                                {{-- 22/10 (Antony): Map = SOLO desde dónde se registró el pago (latitud/longitud
                                                     del navegador de quien cobró, obligatoria desde hoy), como en el legacy. --}}
                                                @if($row['latitud'] && $row['longitud'])
                                                    <a target="_blank" rel="noopener"
                                                       href="https://maps.google.com/?q={{ $row['latitud'] }},{{ $row['longitud'] }}"
                                                       title="Lugar del pago (ubicación de quien cobró)">
                                                        <i class="ti ti-world f-s-16 text-primary"></i>
                                                    </a>
                                                @else
                                                    <i class="ti ti-world-off f-s-16 text-muted" title="Pago sin ubicación (cobro anterior a la validación)"></i>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isAdmin ? 11 : 10 }}" class="text-center py-4 text-muted">
                                            No se encontraron pagos en el rango seleccionado
                                        </td>
                                    </tr>
                                @endforelse

                                {{-- Totales --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="6" rowspan="{{ $totals['excedente'] > 0 ? 7 : 6 }}" class="text-center">Total</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end">{{ number_format($totals['total'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Fijos</td>
                                    <td class="text-end">{{ number_format($totals['fijos'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center" style="color:red;">Otros</td>
                                    <td class="text-end">{{ number_format($totals['otros'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Capital</td>
                                    <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Interes</td>
                                    <td class="text-end">{{ number_format($totals['interes'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                @if($totals['excedente'] > 0)
                                    <tr style="background-color:#f0f0f0; font-weight:500;">
                                        <td class="text-center">Excedente</td>
                                        <td class="text-end">{{ number_format($totals['excedente'], 2) }}</td>
                                        <td></td>
                                        <td></td>
                                        @if($isAdmin)<td></td>@endif
                                    </tr>
                                @endif
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Mora</td>
                                    <td class="text-end">{{ number_format($totals['mora'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visor flotante de las fotos del depósito: el mismo partial que caja, galerías y auditoría. --}}
    @include('livewire.cash.partials._lightbox')
    {{-- Ventanitas de recibos (Antony, 22/10: "debo poder abrir varios recibos a la vez"): una por
         recibo abierto, en cascada, arrastrables (ventanaLibre), la tocada al frente. Teletransportadas
         al <body> para que position:fixed se ancle al viewport. Cada una es un iframe del recibo público
         (allow=clipboard-write para su botón "Copiar imagen"). --}}
    <template x-teleport="body">
        <div class="recibos-ventanas">
            <template x-for="v in recibos" :key="v.id">
                <div x-data="ventanaLibre({ x: v.x, y: v.y, w: 430 })" class="card shadow recibo-ventana"
                     :style="{ zIndex: v.z }" x-on:pointerdown="alFrente(v)">
                    <div class="card-header d-flex justify-content-between align-items-center py-2 recibo-ventana-cabecera"
                         style="background:#009bdc; color:#fff;"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                        <b style="font-size:13px;" x-text="v.titulo"></b>
                        <a href="#" x-on:click.prevent="cerrarRecibo(v.id)" title="Cerrar (Esc cierra la de arriba)"
                           style="color:#fff; text-decoration:none; font-size:20px; line-height:1;">&times;</a>
                    </div>
                    <div class="card-body p-0">
                        <iframe :src="v.url" allow="clipboard-write" style="width:100%; height:62vh; border:0; display:block;"></iframe>
                    </div>
                </div>
            </template>
        </div>
    </template>
    <style>
        /* Ventanitas de recibos: fijas, varias a la vez, arrastrables desde la cabecera.
           transition: none → el tema anima toda .card y la ventana iba detrás del mouse. */
        .recibo-ventana { position: fixed; width: 430px; max-width: 95vw; margin: 0; transition: none !important; }
        .recibo-ventana-cabecera { cursor: grab; user-select: none; -webkit-user-select: none; touch-action: none; }
        .recibo-ventana-cabecera:active { cursor: grabbing; }
        /* Fila con recibos abiertos: amarillo Huacachin (el mismo de las cuotas vencidas en Morosos).
           Bootstrap pinta la cebra y el hover con un box-shadow interior sobre la celda, que tiñe el
           amarillo; se apaga igual que en Morosos (.fila-vencida). */
        #tabla-pagos-rep tbody tr.fila-recibo > td {
            background-color: #ffff00 !important;
            --bs-table-bg-type: transparent; --bs-table-accent-bg: transparent; --bs-table-bg-state: transparent; box-shadow: none;
        }
        @media print { .recibos-ventanas { display: none !important; } }
    </style>
<span id="final"></span>
</div>
