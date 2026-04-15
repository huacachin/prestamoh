<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADISTICO CAJA M.A.</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Rep. Estad. Caja</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filters --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 170px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $num => $name)
                                            <option value="{{ $num }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <label class="form-label mb-0 small">Anio</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @for($y = now()->year - 5; $y <= now()->year + 2; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                                <button class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="printReport()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Main table --}}
                    <div id="printme" style="overflow-x: auto;">
                        <table class="table table-bordered table-sm text-nowrap" style="font-size: 0.75rem; min-width: 1400px;">
                            <thead class="bg-primary text-center">
                                {{-- Row 1 --}}
                                <tr>
                                    <th rowspan="5" class="align-middle" style="min-width:90px;">Fecha</th>
                                    <th rowspan="5" class="align-middle">Capital T.</th>
                                    <th colspan="14">CREDITO</th>
                                    <th colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
                                </tr>
                                {{-- Row 2 --}}
                                <tr>
                                    <th colspan="12">Ingreso - Caja</th>
                                    <th rowspan="4" class="align-middle">Egreso</th>
                                    <th rowspan="4" class="align-middle">Utilidad<br>Caja</th>
                                </tr>
                                {{-- Row 3 --}}
                                <tr>
                                    <th rowspan="3" class="align-middle">Capital</th>
                                    <th colspan="10">Interes</th>
                                    <th rowspan="3" class="align-middle">Otros</th>
                                    <th colspan="3">Ingreso</th>
                                    <th colspan="3">Egreso</th>
                                </tr>
                                {{-- Row 4 --}}
                                <tr>
                                    <th colspan="3">Mensual</th>
                                    <th colspan="3">Semanal</th>
                                    <th colspan="3">Diario</th>
                                    <th rowspan="2" class="align-middle">Total</th>
                                    <th rowspan="2" class="align-middle">Fijos</th>
                                    <th rowspan="2" class="align-middle">Otros</th>
                                    <th rowspan="2" class="align-middle">Total</th>
                                    <th rowspan="2" class="align-middle">Fijos</th>
                                    <th rowspan="2" class="align-middle">Otros</th>
                                    <th rowspan="2" class="align-middle">Total</th>
                                </tr>
                                {{-- Row 5 --}}
                                <tr>
                                    <th>N</th>
                                    <th>S/</th>
                                    <th>Mora</th>
                                    <th>N</th>
                                    <th>S/</th>
                                    <th>Mora</th>
                                    <th>N</th>
                                    <th>S/</th>
                                    <th>Mora</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr @if($row['is_sunday']) class="table-danger" @endif>
                                        <td>{{ $row['day'] }}/{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</td>
                                        <td class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['capital_cobrado'], 2) }}</td>
                                        <td class="text-center">{{ $row['mensual_n'] }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['mensual_interes'], 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['mensual_mora'], 2) }}</td>
                                        <td class="text-center">{{ $row['semanal_n'] }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['semanal_interes'], 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['semanal_mora'], 2) }}</td>
                                        <td class="text-center">{{ $row['diario_n'] }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['diario_interes'], 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['diario_mora'], 2) }}</td>
                                        <td class="text-end text-danger fw-bold">{{ number_format($row['total_credito'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['otros_ingreso'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['otros_egreso'], 2) }}</td>
                                        <td class="text-end text-danger fw-bold">{{ number_format($row['utilidad_caja'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['ingreso_fijos'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['ingreso_otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['ingreso_total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['egreso_fijos'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['egreso_otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['egreso_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-primary fw-bold">
                                <tr>
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['capital_cobrado'], 2) }}</td>
                                    <td class="text-center">{{ $totals['mensual_n'] }}</td>
                                    <td class="text-end">{{ number_format($totals['mensual_interes'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['mensual_mora'], 2) }}</td>
                                    <td class="text-center">{{ $totals['semanal_n'] }}</td>
                                    <td class="text-end">{{ number_format($totals['semanal_interes'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['semanal_mora'], 2) }}</td>
                                    <td class="text-center">{{ $totals['diario_n'] }}</td>
                                    <td class="text-end">{{ number_format($totals['diario_interes'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['diario_mora'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['total_credito'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['otros_ingreso'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['otros_egreso'], 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($totals['utilidad_caja'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['ingreso_fijos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['ingreso_otros'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['ingreso_total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['egreso_fijos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['egreso_otros'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['egreso_total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        {{-- Distribution section --}}
                        <div class="mt-4">
                            <table class="table table-bordered table-sm" style="font-size: 0.80rem; max-width: 700px;">
                                <thead class="bg-primary text-center">
                                    <tr>
                                        <th colspan="2">DETALLES</th>
                                        <th>%</th>
                                        <th colspan="2">M.S</th>
                                        <th colspan="2">D</th>
                                        <th>M.S + D</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($distribution as $dist)
                                        <tr @if($dist['label'] === 'Total') class="fw-bold" @endif>
                                            <td colspan="2">
                                                <strong>{{ $dist['label'] }}</strong>
                                            </td>
                                            <td class="text-center @if($dist['label'] === 'Utilidad' || $dist['label'] === 'Total') text-danger fw-bold @else fw-bold @endif">
                                                {{ $dist['pct'] }}
                                            </td>
                                            <td colspan="2" class="text-end @if($dist['label'] === 'Total') text-danger fw-bold @endif">
                                                {{ number_format($dist['ms'], 2) }}
                                            </td>
                                            <td colspan="2" class="text-end @if($dist['label'] === 'Total') text-danger fw-bold @endif">
                                                {{ number_format($dist['d'], 2) }}
                                            </td>
                                            <td class="text-end @if($dist['label'] === 'Utilidad' || $dist['label'] === 'Total') text-danger fw-bold @endif">
                                                {{ number_format($dist['total'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function printReport() {
        var content = document.getElementById('printme').innerHTML;
        var original = document.body.innerHTML;
        document.body.innerHTML =
            '<html><head><title>Reporte Estadistico de Caja</title>' +
            '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' +
            '<style>body{font-size:11px;} table{width:100%;} .text-danger{color:#dc3545!important;} .bg-primary{background-color:#0d6efd!important;color:#fff;} .table-danger{background-color:#f8d7da!important;}</style>' +
            '</head><body>' +
            '<center><b>Reporte Estadistico de Caja - {{ $months[$month] ?? "" }} {{ $year }}</b></center><br>' +
            content +
            '</body></html>';
        window.print();
        document.body.innerHTML = original;
        location.reload();
    }
</script>
