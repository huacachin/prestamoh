<div class="container-fluid" x-data="{
        open: false, monto: 0, meses: 0,
        openDetalle(m, n) { this.monto = parseFloat(m) || 0; this.meses = parseInt(n) || 0; this.open = true; },
        fmt(v) { return Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">
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
                                <input type="text" class="form-control"
                                       wire:model="nombre" placeholder="Nombres"
                                       style="background-color: yellow; font-weight: 600;">
                                @error('nombre') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1"><b>Capital</b></label>
                                <input type="number" step="0.01" min="1" class="form-control"
                                       wire:model="capital" placeholder="Capital"
                                       style="background-color: yellow; font-weight: 600;">
                                @error('capital') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1"><b>%</b></label>
                                <input type="number" step="0.01" min="0" class="form-control"
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
                                <div class="col-md-4"><b>Interés:</b> {{ $interes }}%</div>
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
                                                       @click.prevent="openDetalle({{ $mensual[$i]['pagar'] }}, {{ $i }})"
                                                       title="Ver detalle">{{ number_format($mensual[$i]['pagar'], 2) }}</a>
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

    {{-- Modal Detalle de Pago (réplica del popup legacy cuenta_por_cobrar_pagado.php).
         Teletransportado al <body> para que position:fixed se ancle al viewport
         (centrado real), aunque algún ancestro del layout tenga transform. --}}
    <template x-teleport="body">
    <div>
        {{-- backdrop (fade) --}}
        <div x-show="open" x-cloak x-transition.opacity.duration.200ms
             @click="open=false" @keydown.escape.window="open=false"
             style="position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:1080; background:rgba(0,0,0,.5);"></div>
        {{-- wrapper de centrado: el translate va AQUÍ (estable, SIN transición) para
             que el fade de la card no lo pise (evita el "salto"). --}}
        <div style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:1081;">
            {{-- card: solo fade de opacidad --}}
            <div x-show="open" x-cloak x-transition.opacity.duration.200ms @click.stop class="card shadow"
                 style="width:440px; max-width:95vw; margin:0;">
            <div class="card-header d-flex justify-content-between align-items-center py-2"
                 style="background:#009bdc; color:#fff;">
                <b style="font-size:14px;">Detalle de Pago</b>
                <a href="#" @click.prevent="open=false" style="color:#fff; text-decoration:none; font-size:20px; line-height:1;">&times;</a>
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
                            <td><b style="color:red;" x-text="meses"></b></td>
                            <td><b x-text="fmt(monto)"></b></td>
                            <td><b style="color:red;" x-text="4*meses"></b></td>
                            <td><b x-text="fmt(monto/4)"></b></td>
                            <td><b style="color:red;" x-text="4*meses*6"></b></td>
                            <td><b x-text="fmt(monto/4/6)"></b></td>
                        </tr>
                        <tr>
                            <td colspan="2"><b x-text="fmt(monto*meses)"></b></td>
                            <td colspan="2"><b x-text="fmt((monto/4)*(4*meses))"></b></td>
                            <td colspan="2"><b x-text="fmt((monto/4/6)*(4*meses*6))"></b></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>
    </template>
</div>

<style>
    [x-cloak] { display: none !important; }
    /* Celdas compactas (el legacy usa celdas chicas) */
    .sim-table > :not(caption) > * > * { padding: 2px 6px !important; }
    @media print {
        .breadcrumb, .btn, form { display: none !important; }
        #printme { width: 100%; }
    }
</style>
