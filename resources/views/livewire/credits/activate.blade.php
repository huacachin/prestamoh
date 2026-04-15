<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">ACTIVAR PRESTAMOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Activar Prestamos</span></li>
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
                                <div class="flex-shrink-0" style="width: 300px;">
                                    <input type="search" class="form-control form-control-sm"
                                           placeholder="Buscar por cliente, DNI o ID..."
                                           wire:model.live.debounce.300ms="search">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>DNI</th>
                                <th>Capital</th>
                                <th>Cuotas</th>
                                <th>Tipo</th>
                                <th>Situacion</th>
                                <th>Accion</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($credits as $credit)
                                <tr>
                                    <td>{{ $loop->iteration + ($credits->currentPage() - 1) * $credits->perPage() }}</td>
                                    <td>{{ $credit->id }}</td>
                                    <td>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                                    <td>{{ $credit->client?->fullName() }}</td>
                                    <td>{{ $credit->client?->documento }}</td>
                                    <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                                    <td>{{ $credit->cuotas }}</td>
                                    <td>{{ $credit->tipoPlanillaLabel() }}</td>
                                    <td>
                                        @php
                                            $bc = match($credit->situacion) {
                                                'Cancelado' => 'bg-secondary',
                                                'Refinanciado' => 'bg-warning',
                                                default => 'bg-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $bc }}">{{ $credit->situacion }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-success"
                                                onclick="confirmActivate({{ $credit->id }}, '{{ addslashes($credit->client?->fullName()) }}')"
                                                title="Activar prestamo">
                                            <i class="ti ti-refresh f-s-14"></i> Activar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron creditos cancelados o refinanciados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="7"></td>
                                <td class="num">{{ $credits->total() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-2">{{ $credits->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    window.confirmActivate = function (id, clientName) {
        Swal.fire({
            title: 'Activar Prestamo',
            text: '¿Esta seguro de activar el prestamo de ' + clientName + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Si, activar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $wire.set('creditId', id);
                $wire.activate();
            }
        });
    };
</script>
@endscript
