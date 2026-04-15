<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">ELIMINAR MASIVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Eliminar Masivo</span></li>
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
                                {{-- Search type radio buttons --}}
                                <div class="flex-shrink-0 d-flex align-items-center gap-3 border rounded px-3 py-1">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" wire:model="searchType" value="1" id="searchCode">
                                        <label class="form-check-label small" for="searchCode">Codigo</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" wire:model="searchType" value="2" id="searchAdvisor">
                                        <label class="form-check-label small" for="searchAdvisor">Asesor</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" wire:model="searchType" value="3" id="searchUser">
                                        <label class="form-check-label small" for="searchUser">Usuario</label>
                                    </div>
                                </div>

                                {{-- Search input --}}
                                <div class="flex-shrink-0" style="width: 220px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar..." wire:model.live="search">
                                </div>

                                {{-- Date range --}}
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Inicio</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="dateFrom">
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Fin</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="dateTo">
                                </div>

                                {{-- Search button --}}
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>N&deg;</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Usuario</th>
                                <th>Asesor</th>
                                <th>Cliente</th>
                                <th>Codigo</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($records as $record)
                                <tr>
                                    <td>{{ $loop->iteration + ($records->currentPage() - 1) * $records->perPage() }}</td>
                                    <td>{{ $record->date?->format('d/m/Y') }}</td>
                                    <td>{{ $record->time }}</td>
                                    <td>{{ $record->user }}</td>
                                    <td>{{ $record->advisor }}</td>
                                    <td>{{ $record->credit?->client?->fullName() }}</td>
                                    <td>
                                        @if($record->credit_id)
                                            <a href="{{ route('credits.show', $record->credit_id) }}">
                                                #{{ $record->credit_id }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($record->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="5"></td>
                                <td class="text-end">{{ number_format($totalSum, 2) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-2">{{ $records->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
