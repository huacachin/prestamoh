<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE DE MOROSIDAD</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Morosidad</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Importe</th>
                                <th>Cuotas Vencidas</th>
                                <th>Dias Mora</th>
                                <th>Mora Acumulada</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($data as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->cliente }}</td>
                                    <td>{{ $row->documento }}</td>
                                    <td class="text-end">{{ number_format($row->importe, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">{{ $row->cuotas_vencidas }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">{{ $row->dias_mora }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($row->monto_mora_acum, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-muted text-center">No hay creditos morosos</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start fw-bold">TOTALES</td>
                                <td></td>
                                <td class="text-end fw-bold">{{ number_format($totals->importe, 2) }}</td>
                                <td colspan="2"></td>
                                <td class="text-end fw-bold">{{ number_format($totals->monto_mora_acum, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
