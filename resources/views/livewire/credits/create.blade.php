<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CRÉDITOS : NUEVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Créditos</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Nuevo</span></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Revisa los siguientes errores:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3">
                {{-- Buscar cliente --}}
                <div class="col-12"><div class="app-divider-v">CLIENTE</div></div>

                @if($clientName)
                    <div class="col-12">
                        <span class="badge bg-dark p-2">{{ $clientName }}</span>
                        <button class="btn btn-sm btn-outline-danger ms-2" wire:click="clearClient">
                            <i class="ti ti-x"></i> Cambiar
                        </button>
                    </div>
                @else
                    <div class="col-12 col-md-6 position-relative">
                        <label class="form-label">Buscar Cliente (*)</label>
                        <input type="search" class="form-control form-control-sm @error('client_id') is-invalid @enderror"
                               placeholder="Nombre o DNI del cliente..." wire:model.live.debounce.300ms="searchClient">
                        @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        @if(count($clients) > 0)
                            <div class="list-group position-absolute w-100 shadow" style="z-index:100; max-height:200px; overflow-y:auto;">
                                @foreach($clients as $c)
                                    <button type="button" class="list-group-item list-group-item-action py-1"
                                            wire:click="selectClient({{ $c->id }})">
                                        {{ $c->nombre }} {{ $c->apellido_pat }} {{ $c->apellido_mat }} — {{ $c->documento }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Datos del crédito --}}
                <div class="col-12"><div class="app-divider-v">DATOS DEL CRÉDITO</div></div>

                <div class="col-auto">
                    <label class="form-label">Fecha Préstamo (*)</label>
                    <input type="date" class="form-control form-control-sm" wire:model.defer="fecha_prestamo">
                </div>

                <div class="col-auto">
                    <label class="form-label">Importe (Capital) (*)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm @error('importe') is-invalid @enderror"
                           placeholder="0.00" wire:model.defer="importe">
                    @error('importe') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-auto">
                    <label class="form-label">N° Cuotas (*)</label>
                    <input type="number" min="1" max="60" class="form-control form-control-sm"
                           wire:model.defer="cuotas">
                </div>

                <div class="col-auto">
                    <label class="form-label">Tipo Planilla (*)</label>
                    <select class="form-select form-select-sm" wire:model.defer="tipo_planilla">
                        <option value="4">Diario</option>
                        <option value="1">Semanal</option>
                        <option value="3">Mensual</option>
                    </select>
                </div>

                <div class="col-auto">
                    <label class="form-label">Interés % (*)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm"
                           placeholder="0.00" wire:model.defer="interes">
                </div>

                <div class="col-auto">
                    <label class="form-label">Moneda</label>
                    <select class="form-select form-select-sm" wire:model.defer="moneda">
                        <option value="PEN">Soles (S/.)</option>
                        <option value="USD">Dólares ($)</option>
                    </select>
                </div>

                <div class="col-auto">
                    <label class="form-label">Documento</label>
                    <input type="text" class="form-control form-control-sm" placeholder="N° doc" wire:model.defer="documento">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Glosa</label>
                    <input type="text" class="form-control form-control-sm" placeholder="Observación" wire:model.defer="glosa">
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm btn-dark" wire:click="generatePreview">
                    <i class="ti ti-calculator f-s-12"></i> Vista previa
                </button>
                <button class="btn btn-sm btn-primary" wire:click="save">
                    <i class="ti ti-device-floppy f-s-12"></i> Crear Crédito
                </button>
                <a href="{{ route('credits.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>

            {{-- Preview del cronograma --}}
            @if(count($preview) > 0)
                <div class="mt-3">
                    <h6>VISTA PREVIA DEL CRONOGRAMA</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="bg-primary">
                            <tr>
                                <th>Cuota</th>
                                <th>Fecha Venc.</th>
                                <th>Capital</th>
                                <th>Interés</th>
                                <th>Total Cuota</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $sumCap = 0; $sumInt = 0; $sumTot = 0; @endphp
                            @foreach($preview as $p)
                                @php $sumCap += $p['capital']; $sumInt += $p['interes']; $sumTot += $p['total']; @endphp
                                <tr>
                                    <td>{{ $p['num'] }}</td>
                                    <td>{{ $p['fecha'] }}</td>
                                    <td class="text-end">{{ number_format($p['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($p['interes'], 2) }}</td>
                                    <td class="text-end">{{ number_format($p['total'], 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td colspan="2"><strong>TOTAL</strong></td>
                                <td class="text-end"><strong>{{ number_format($sumCap, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($sumInt, 2) }}</strong></td>
                                <td class="text-end"><strong>{{ number_format($sumTot, 2) }}</strong></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
