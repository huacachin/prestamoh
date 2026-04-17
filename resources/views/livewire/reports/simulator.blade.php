<div class="container-fluid">
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
                                <table class="table table-bordered table-sm mb-2" style="font-size: 11px;">
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
                                                <td class="text-end">{{ number_format($mensual[$i]['pagar'], 2) }}</td>
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
                                <table class="table table-bordered table-sm mb-2" style="font-size: 11px;">
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
</div>

<style>
    @media print {
        .breadcrumb, .btn, form { display: none !important; }
        #printme { width: 100%; }
    }
</style>
