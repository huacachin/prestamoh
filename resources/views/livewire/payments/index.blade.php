<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">PAGO/CREDITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Pagos/Crédito</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>DNI</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="nombre" placeholder="DNI">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Nombre</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="nombre1" placeholder="Nombre">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Código</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="codigo1" placeholder="Código">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            <a href="#" class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                        </div>
                    </form>

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="40">N°</th>
                                    <th class="text-center" width="60">Exp.</th>
                                    <th class="text-center" width="80">Código</th>
                                    <th>Nombre</th>
                                    <th class="text-center" width="80">Moneda</th>
                                    <th class="text-end" width="100">Capital</th>
                                    <th class="text-center" width="50">%</th>
                                    <th class="text-center" width="50">C.</th>
                                    <th class="text-center" width="200">Op.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($credits as $credit)
                                <tr onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $credit->client?->expediente }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">
                                            {{ $credit->id }}
                                        </a>
                                    </td>
                                    <td>{{ trim($credit->client?->apellido_pat . ' ' . $credit->client?->apellido_mat . ' ' . $credit->client?->nombre) }}</td>
                                    <td class="text-center">{{ $credit->moneda }}</td>
                                    <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                                    <td class="text-center">{{ round($credit->interes, 0) }}</td>
                                    <td class="text-center">{{ $credit->cuotas }}</td>
                                    <td class="text-center text-nowrap">
                                        <a href="{{ route('payments.create', $credit->id) }}"
                                           class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;">
                                            Masivo
                                        </a>
                                        @if($credit->tipo_planilla == 3 && $credit->cuotas == 1)
                                            <a href="{{ route('payments.refinance', $credit->id) }}"
                                               class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;">
                                                Refinanciar
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Totales:</td>
                                    <td class="text-end fw-bold">{{ number_format($totalCapital, 2) }}</td>
                                    <td colspan="3" class="text-end">{{ $credits->count() }} créditos</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($credits as $credit)
                            <div class="card mb-2 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">
                                                #{{ $credit->id }} - {{ trim($credit->client?->apellido_pat . ' ' . $credit->client?->apellido_mat . ' ' . $credit->client?->nombre) }}
                                            </a>
                                        </h6>
                                        <span class="badge bg-primary">S/ {{ number_format($credit->importe, 2) }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Exp.:</b> {{ $credit->client?->expediente }}</div>
                                        <div class="col-6"><b>Moneda:</b> {{ $credit->moneda }}</div>
                                        <div class="col-6"><b>%:</b> {{ round($credit->interes, 0) }}</div>
                                        <div class="col-6"><b>Cuotas:</b> {{ $credit->cuotas }}</div>
                                    </div>
                                    <div class="d-flex gap-1 mt-2">
                                        <a href="{{ route('payments.create', $credit->id) }}"
                                           class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;">
                                            Masivo
                                        </a>
                                        @if($credit->tipo_planilla == 3 && $credit->cuotas == 1)
                                            <a href="{{ route('payments.refinance', $credit->id) }}"
                                               class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;">
                                                Refinanciar
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-primary">Total Capital: S/ {{ number_format($totalCapital, 2) }} | {{ $credits->count() }} créditos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
