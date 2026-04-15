<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CARTERA ACTIVA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Cartera</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <select class="form-select form-select-sm" wire:model="filterAsesor">
                                        <option value="">Todos</option>
                                        @foreach($asesores as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Fecha Prest.</th>
                                <th>Importe</th>
                                <th>Cuotas</th>
                                <th>Inter.</th>
                                <th>Total Pagado</th>
                                <th>Saldo Pend.</th>
                                <th>Dias Mora</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($data as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->cliente }}</td>
                                    <td>{{ $row->documento }}</td>
                                    <td>{{ $row->fecha_prestamo?->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ number_format($row->importe, 2) }}</td>
                                    <td class="text-center">{{ $row->cuotas }}</td>
                                    <td class="text-center">{{ $row->interes }}%</td>
                                    <td class="text-end">{{ number_format($row->total_pagado, 2) }}</td>
                                    <td class="text-end">{{ number_format($row->saldo_pendiente, 2) }}</td>
                                    <td class="text-center">
                                        @if($row->dias_mora > 0)
                                            <span class="badge bg-danger">{{ $row->dias_mora }}</span>
                                        @else
                                            <span class="badge bg-success">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start fw-bold">TOTALES</td>
                                <td colspan="2"></td>
                                <td class="text-end fw-bold">{{ number_format($totals->importe, 2) }}</td>
                                <td colspan="2"></td>
                                <td class="text-end fw-bold">{{ number_format($totals->total_pagado, 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format($totals->saldo_pendiente, 2) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
