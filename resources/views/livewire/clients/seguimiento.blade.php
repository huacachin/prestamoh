@use('App\Livewire\Clients\Seguimiento')
@use('App\Support\RutaExpediente', 'Ruta')
<div class="container-fluid">
    <style>
        .seg-estados { display: flex; flex-wrap: wrap; gap: 6px; }
        .seg-estado { border: 2px solid #d9dee8; border-radius: 16px; background: #fff; color: #1f2433; padding: 3px 12px; font-size: 12px; font-weight: 600; }
        .seg-estado.es-activo { border-color: #0a6fb0; background: #eaf4fb; color: #0a5a8f; }
        .seg-estado .seg-cuenta { font-weight: 700; }
        .seg-tabla th { font-size: 12px; white-space: nowrap; }
        .seg-tabla td { font-size: 12px; vertical-align: middle; }
        .seg-avance { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .seg-puntos { display: inline-flex; gap: 3px; }
        .seg-punto { width: 18px; height: 18px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center;
                     font-size: 10px; font-weight: 700; background: #e6e9f0; color: #3a4152; }
        .seg-punto.es-hecho { background: #1a7a34; color: #fff; }
        .seg-punto.es-sigue { background: #0a6fb0; color: #fff; }
        .seg-punto.es-no-aplica { background: #fff; color: #8a93a3; border: 1px dashed #b3bac6; }
        .seg-cuantos { font-weight: 700; color: #3a4152; }
        .seg-leyenda { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 11px; color: #5b6472; }
        .seg-leyenda > span { display: inline-flex; align-items: center; gap: 4px; }
    </style>

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">SEGUIMIENTO DE EXPEDIENTES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Clientes</span></a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Seguimiento</span></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body pb-2">
            <p class="small text-muted mb-2">
                Los préstamos que se están trabajando y en qué paso va cada uno: los activos dados en el período (las refinanciaciones
                no cuentan), más los clientes registrados en el período que todavía no tienen préstamo. Si buscas a un cliente, salen
                todos sus préstamos activos.
            </p>

            {{-- Filtros --}}
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4">
                    <label class="form-label mb-0 small" for="seg-buscar"><b>Buscar cliente</b></label>
                    <input type="search" id="seg-buscar" class="form-control form-control-sm" placeholder="Nombre, DNI/RUC o expediente"
                           wire:model.live.debounce.400ms="buscar" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small" for="seg-dias"><b>Período</b></label>
                    <select id="seg-dias" class="form-select form-select-sm" wire:model.live="dias" @disabled($conBusqueda)>
                        @foreach(Seguimiento::PERIODOS as $dias => $nombre)
                            <option value="{{ $dias }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small" for="seg-paso"><b>Paso que sigue</b></label>
                    <select id="seg-paso" class="form-select form-select-sm" wire:model.live="paso">
                        <option value="">Cualquiera</option>
                        @foreach(Ruta::PASOS as $clave => [$nombre])
                            @continue($clave === 'cliente')
                            <option value="{{ $clave }}">{{ $nombre }} ({{ $porPaso[$clave] ?? 0 }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-md-end">
                    <a href="{{ route('clients.create') }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-user-plus"></i> Nuevo cliente
                    </a>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                <div class="seg-estados" role="group" aria-label="Estado del expediente">
                    @foreach(Seguimiento::ESTADOS as $clave => $nombre)
                        <button type="button" @class(['seg-estado', 'es-activo' => $estadoActual === $clave]) wire:click="$set('estado', '{{ $clave }}')"
                                wire:key="seg-estado-{{ $clave }}" aria-pressed="{{ $estadoActual === $clave ? 'true' : 'false' }}">
                            {{ $nombre }} <span class="seg-cuenta">{{ $conteo[$clave] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="seg-leyenda" aria-hidden="true">
                    <span><span class="seg-punto es-hecho"><i class="ti ti-check"></i></span> Hecho</span>
                    <span><span class="seg-punto es-sigue">3</span> Sigue</span>
                    <span><span class="seg-punto">5</span> Pendiente</span>
                    <span><span class="seg-punto es-no-aplica">–</span> No aplica</span>
                </div>
            </div>

            @if($tope !== null)
                <div class="alert alert-warning py-2 px-3 my-2 small" role="alert">
                    <i class="ti ti-alert-triangle"></i>
                    Se muestran los {{ number_format($tope) }} más recientes. Acorta el período o busca al cliente para ver el resto.
                </div>
            @endif

            @if($filas->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-clipboard-check" style="font-size:48px; opacity:.4;"></i>
                    <p class="mt-2 mb-0">
                        @if($conBusqueda)
                            Ningún cliente activo coincide con la búsqueda.
                        @elseif($estadoActual === 'incompletos' && $conteo['todos'] > 0)
                            No hay expedientes incompletos {{ $paso !== '' ? 'esperando ese paso' : 'en el período' }}.
                        @else
                            No hay expedientes en el período.
                        @endif
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover seg-tabla mb-2">
                        <thead>
                            <tr>
                                <th>Exp.</th>
                                <th>Cliente</th>
                                <th>T. Crédito</th>
                                <th>Asesor</th>
                                <th>Préstamo</th>
                                <th>Avance</th>
                                <th class="text-end">Lo que sigue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($filas as $fila)
                                @php
                                    $client = $fila['client'];
                                    $ruta = $fila['ruta'];
                                    $siguiente = $ruta['siguiente'];
                                    // A la ficha, en los pasos de ESE préstamo y en la pantalla del paso que falta.
                                    $ficha = route('clients.edit', array_filter([
                                        'id' => $client->id, 'prestamo' => $fila['credito']?->id, 'tab' => $siguiente['tab'] ?? 'prestamos',
                                    ]));
                                @endphp
                                <tr wire:key="seg-{{ $client->id }}-{{ $fila['credito']?->id ?? 0 }}">
                                    <td>{{ $client->expediente ?? $client->id }}</td>
                                    <td>
                                        <a href="{{ $ficha }}" class="fw-bold">{{ $client->fullName() }}</a>
                                        <div class="text-muted">{{ $client->documento }}</div>
                                    </td>
                                    <td>{{ $client->zona ?: '—' }}</td>
                                    <td>{{ $client->asesor?->name ?? '—' }}</td>
                                    <td>
                                        @if($fila['credito'])
                                            #{{ $fila['credito']->id }}
                                            <div class="text-muted">{{ $fila['credito']->fecha_prestamo?->format('d/m/Y') }} · S/ {{ number_format((float) $fila['credito']->importe, 2) }}</div>
                                        @else
                                            <span class="text-muted">Sin préstamo activo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="seg-avance">
                                            <span class="seg-puntos">
                                                @foreach($ruta['pasos'] as $p)
                                                    <span @class([
                                                        'seg-punto',
                                                        'es-hecho' => $p['estado'] === Ruta::HECHO,
                                                        'es-no-aplica' => $p['estado'] === Ruta::NO_APLICA,
                                                        'es-sigue' => ($siguiente['clave'] ?? null) === $p['clave'],
                                                    ]) title="{{ $p['nombre'] }}: {{ $p['detalle'] }}">
                                                        @if($p['estado'] === Ruta::HECHO)
                                                            <i class="ti ti-check" aria-hidden="true"></i>
                                                        @elseif($p['estado'] === Ruta::NO_APLICA)
                                                            –
                                                        @else
                                                            {{ $p['num'] }}
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </span>
                                            <span class="seg-cuantos">{{ $ruta['hechos'] }} de {{ $ruta['total'] }}</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        @if($siguiente)
                                            <a href="{{ $ficha }}" class="btn btn-sm btn-primary py-1" title="Ir a la ficha: {{ $siguiente['accion'] }}">
                                                {{ $siguiente['nombre'] }} <i class="ti ti-arrow-right"></i>
                                            </a>
                                        @else
                                            <span class="badge text-bg-success"><i class="ti ti-circle-check"></i> Completo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
