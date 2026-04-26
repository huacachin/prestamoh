<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">CLIENTES CESADOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Cliente Cesados</a></li>
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
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Expediente</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="nexpediente" placeholder="Numero Expediente">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>DNI</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="documento" placeholder="DNI">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Nombre</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="nombre" placeholder="Nombres">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Ruta</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model="ruta" placeholder="Ruta">
                            </div>
                            <div class="col">
                                <label class="form-label mb-0 small"><b>Asesor</b></label>
                                <select class="form-select form-select-sm" wire:model="ejecutivo">
                                    <option value="">Todos</option>
                                    <option value="Ninguno">Sin Asesor</option>
                                    @foreach($asesores as $asesor)
                                        <option value="{{ $asesor->id }}">{{ $asesor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            <a href="{{ route('exports.clients') }}?nexpediente={{ $nexpediente }}&documento={{ $documento }}&nombre={{ $nombre }}&ruta={{ $ruta }}&ejecutivo={{ $ejecutivo }}&status=inactive"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                        </div>
                    </form>

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center">N&deg;</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center">Exp.</th>
                                    <th class="text-center">Nombres Apellidos</th>
                                    <th class="text-center">DNI</th>
                                    <th class="text-center">Movil</th>
                                    <th class="text-center">Ruta</th>
                                    <th class="text-center">Giro</th>
                                    <th class="text-center">Asesor</th>
                                    <th class="text-center" colspan="3">Opciones</th>
                                    <th class="text-center">C.</th>
                                    <th class="text-center">N.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($clients as $client)
                                @php
                                    $hasCredit = isset($clientsWithCredit[$client->id]);
                                    $textColor = $hasCredit ? 'inherit' : '#dc3545';
                                @endphp
                                <tr style="color: {{ $textColor }};"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center" style="color: inherit;">{{ $loop->iteration }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->fecha_registro?->format('Y-m-d') }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->usuario }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->expediente }}</td>
                                    <td style="color: inherit;">
                                        <a href="{{ route('clients.edit', $client->id) }}" style="color: black; text-decoration: none;">
                                            {{ $client->apellido_pat }} {{ $client->apellido_mat }} {{ $client->nombre }}
                                        </a>
                                    </td>
                                    <td style="color: inherit;">
                                        <a href="{{ route('credits.create', $client->id) }}" style="color: inherit; text-decoration: none;">
                                            {{ $client->documento }}
                                        </a>
                                    </td>
                                    <td style="color: inherit;">{{ $client->celular1 }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->zona }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->giro }}</td>
                                    <td class="text-center" style="color: inherit;">{{ $client->asesor?->username ?? $client->asesor?->name }}</td>
                                    <td class="text-center text-nowrap">
                                        <a href="{{ route('clients.show', $client->id) }}"
                                           class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">
                                            Prestamo
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('clients.show', $client->id) }}"
                                           class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">
                                            Aval
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('clients.show', $client->id) }}"
                                           class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">
                                            Adjuntos
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        @if($client->latitud && $client->longitud)
                                            <a href="https://maps.google.com/?q={{ $client->latitud }},{{ $client->longitud }}" target="_blank">
                                                <i class="ti ti-map-pin f-s-18 text-success"></i>
                                            </a>
                                        @else
                                            <i class="ti ti-map-pin-off f-s-18 text-danger"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($client->latitud2 && $client->longitud2)
                                            <a href="https://maps.google.com/?q={{ $client->latitud2 }},{{ $client->longitud2 }}" target="_blank">
                                                <i class="ti ti-map-pin f-s-18 text-success"></i>
                                            </a>
                                        @else
                                            <i class="ti ti-map-pin-off f-s-18 text-danger"></i>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="2">TOTAL</td>
                                    <td colspan="12"></td>
                                    <td class="text-center fw-bold">{{ $clients->count() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($clients as $client)
                            @php
                                $hasCredit = isset($clientsWithCredit[$client->id]);
                            @endphp
                            <div class="card mb-2 shadow-sm {{ !$hasCredit ? 'border-danger' : 'border-warning' }}">
                                <div class="card-body p-3" style="{{ !$hasCredit ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('clients.edit', $client->id) }}" style="{{ !$hasCredit ? 'color: red;' : 'color: black;' }}">
                                                {{ $client->apellido_pat }} {{ $client->apellido_mat }} {{ $client->nombre }}
                                            </a>
                                        </h6>
                                        <span class="badge bg-secondary">#{{ $loop->iteration }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>DNI:</b>
                                            <a href="{{ route('credits.create', $client->id) }}">{{ $client->documento }}</a>
                                        </div>
                                        <div class="col-6"><b>Exp.:</b> {{ $client->expediente }}</div>
                                        <div class="col-6"><b>Movil:</b> {{ $client->celular1 }}</div>
                                        <div class="col-6"><b>Ruta:</b> {{ $client->zona }}</div>
                                        <div class="col-6"><b>Giro:</b> {{ $client->giro }}</div>
                                        <div class="col-6"><b>Asesor:</b> {{ $client->asesor?->username ?? $client->asesor?->name }}</div>
                                        <div class="col-6"><b>Fecha:</b> {{ $client->fecha_registro?->format('Y-m-d') }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $client->usuario }}</div>
                                        <div class="col-6">
                                            @if($client->latitud && $client->longitud)
                                                <a href="https://maps.google.com/?q={{ $client->latitud }},{{ $client->longitud }}" target="_blank">
                                                    <i class="ti ti-map-pin f-s-14 text-success"></i> Casa
                                                </a>
                                            @else
                                                <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Casa
                                            @endif
                                        </div>
                                        <div class="col-6">
                                            @if($client->latitud2 && $client->longitud2)
                                                <a href="https://maps.google.com/?q={{ $client->latitud2 }},{{ $client->longitud2 }}" target="_blank">
                                                    <i class="ti ti-map-pin f-s-14 text-success"></i> Trabajo
                                                </a>
                                            @else
                                                <i class="ti ti-map-pin-off f-s-14 text-danger"></i> Trabajo
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 mt-2">
                                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">Prestamo</a>
                                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">Aval</a>
                                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;">Adjuntos</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-warning text-dark">Total: {{ $clients->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
