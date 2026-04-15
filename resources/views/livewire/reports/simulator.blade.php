<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">SIMULADOR DE CREDITO</h4>
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
                <div class="card-body pb-2">
                    <div class="row my-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Capital (S/)</label>
                            <input type="number" step="0.01" min="1" class="form-control form-control-sm"
                                   wire:model="capital" placeholder="Ej: 5000">
                            @error('capital') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Interes Mensual (%)</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                   wire:model="interes_mensual" placeholder="Ej: 5">
                            @error('interes_mensual') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Nro. Cuotas</label>
                            <input type="number" min="1" max="120" class="form-control form-control-sm"
                                   wire:model="cuotas" placeholder="Ej: 12">
                            @error('cuotas') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Tipo Planilla</label>
                            <select class="form-select form-select-sm" wire:model="tipo_planilla">
                                <option value="1">Semanal</option>
                                <option value="3">Mensual</option>
                                <option value="4">Diario</option>
                            </select>
                            @error('tipo_planilla') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-sm btn-primary w-100" wire:click="simulate">
                                <i class="ti ti-calculator f-s-12"></i> Simular
                            </button>
                        </div>
                    </div>

                    @if($resumen)
                        {{-- Summary cards --}}
                        <div class="row mb-3 mt-3">
                            <div class="col-md-2">
                                <div class="card border-start border-primary border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Capital</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->capital, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card border-start border-success border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Interes Total</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->interes_total, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card border-start border-dark border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Total a Pagar</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->total_pagar, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card border-start border-info border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Cuota Capital</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->cuota_capital, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card border-start border-warning border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Cuota Interes</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->cuota_interes, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card border-start border-danger border-4 shadow-sm">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Mora Diaria</small>
                                        <h6 class="mb-0">S/ {{ number_format($resumen->mora_diaria, 2) }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Cronograma table --}}
                        <div class="table-responsive tableFixHead">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="bg-primary">
                                <tr>
                                    <th>Cuota</th>
                                    <th>Capital</th>
                                    <th>Interes</th>
                                    <th>Total Cuota</th>
                                    <th>Mora Diaria</th>
                                    <th>Saldo Capital</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($cronograma as $cuota)
                                    <tr>
                                        <td class="text-center">{{ $cuota->num }}</td>
                                        <td class="text-end">{{ number_format($cuota->cuota_capital, 2) }}</td>
                                        <td class="text-end">{{ number_format($cuota->cuota_interes, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($cuota->cuota_total, 2) }}</td>
                                        <td class="text-end">{{ number_format($cuota->mora_diaria, 2) }}</td>
                                        <td class="text-end">{{ number_format($cuota->saldo_capital, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="bg-primary">
                                <tr>
                                    <td class="fw-bold">TOTAL</td>
                                    <td class="text-end fw-bold">{{ number_format($resumen->capital, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($resumen->interes_total, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($resumen->total_pagar, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
