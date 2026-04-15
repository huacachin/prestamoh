<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">APERTURA DE CAJA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Apertura</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('cash_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('cash_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('cash_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('cash_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Formulario de apertura --}}
    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Revisa los siguientes errores:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Fecha (*)</label>
                                <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                                       wire:model.defer="fecha">
                                @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="col-md-auto col-sm-12">
                            <div class="mb-3">
                                <label class="form-label">Saldo Inicial (*)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm @error('saldo_inicial') is-invalid @enderror"
                                       placeholder="0.00"
                                       wire:model.defer="saldo_inicial">
                                @error('saldo_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save">
                            <i class="ti ti-device-floppy f-s-12"></i> Registrar Apertura
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de aperturas recientes --}}
    <div class="row table-section mt-3">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    <h6 class="mb-3">Aperturas Recientes</h6>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Saldo Inicial</th>
                                <th>Saldo Final</th>
                                <th>Estado</th>
                                <th>Usuario</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($openings as $opening)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $opening->fecha->format('d/m/Y') }}</td>
                                    <td class="num">{{ number_format($opening->saldo_inicial, 2) }}</td>
                                    <td class="num">{{ number_format($opening->saldo_final, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $opening->estado === 'abierto' ? 'bg-success' : 'bg-secondary' }}">
                                            {{ ucfirst($opening->estado) }}
                                        </span>
                                    </td>
                                    <td>{{ $opening->user?->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        @if($opening->estado === 'abierto')
                                            @hasanyrole('superusuario|administrador|director')
                                            <button class="btn btn-sm btn-outline-danger" wire:click="questionClose({{ $opening->id }})" title="Cerrar Caja">
                                                <i class="ti ti-lock f-s-14"></i>
                                            </button>
                                            @endhasanyrole
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-muted">No se encontraron aperturas</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td class="text-start">TOTAL</td>
                                <td colspan="4"></td>
                                <td class="num">{{ $openings->count() }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
