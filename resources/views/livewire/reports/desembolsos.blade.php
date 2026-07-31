{{-- =========================================================
     DESEMBOLSOS DEL PERÍODO — drill-down del héroe del dashboard.
     Misma fuente (DesembolsosService): los totales cuadran con los
     chips Nuevos/Refinanciados por construcción.
     ========================================================= --}}
<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DESEMBOLSOS DEL PERÍODO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('dashboard.index') }}?mes={{ $month }}&anio={{ $year }}{{ $day !== '' ? '&dia='.$day : '' }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Panel de Control</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Desembolsos</span></li>
            </ul>
        </div>
    </div>

    @php
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $fmt = fn ($n) => number_format((float) $n, 2);
        $granTotal = $resumen['nuevos']['total'] + $resumen['refis']['total'];
        $granN = $resumen['nuevos']['n'] + $resumen['refis']['n'];
    @endphp

    {{-- Filtros + tabs --}}
    <div class="card shadow-sm">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Año</label>
                    <select class="form-select form-select-sm" wire:model.live="year">
                        @foreach($anios as $a)
                            <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Mes</label>
                    <select class="form-select form-select-sm" wire:model.live="month">
                        @foreach($meses as $num => $nombre)
                            <option value="{{ $num }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Día</label>
                    <select class="form-select form-select-sm" wire:model.live="day">
                        <option value="">Todo el mes</option>
                        @for($d = 1; $d <= $diasDelMes; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <span class="badge bg-dark f-s-13 px-3 py-2">
                        <i class="ti ti-calendar"></i> {{ $etiqueta }}
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <div class="btn-group btn-group-sm" role="group" aria-label="Tipo de desembolso">
                    <button type="button" wire:click="$set('tipo', 'todos')"
                            class="btn {{ $tipo === 'todos' ? 'btn-dark' : 'btn-outline-dark' }}">
                        Todos <span class="badge bg-light text-dark ms-1">{{ $granN }}</span>
                    </button>
                    <button type="button" wire:click="$set('tipo', 'nuevos')"
                            class="btn {{ $tipo === 'nuevos' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="ti ti-coins"></i> Nuevos <span class="badge bg-light text-dark ms-1">{{ $resumen['nuevos']['n'] }}</span>
                    </button>
                    <button type="button" wire:click="$set('tipo', 'refinanciados')"
                            class="btn {{ $tipo === 'refinanciados' ? 'btn-warning' : 'btn-outline-warning' }}">
                        <i class="ti ti-refresh"></i> Refinanciados <span class="badge bg-light text-dark ms-1">{{ $resumen['refis']['n'] }}</span>
                    </button>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-3 small">
                    <span><strong>Nuevos:</strong> S/ {{ $fmt($resumen['nuevos']['total']) }}</span>
                    <span><strong>Refinanciados:</strong> S/ {{ $fmt($resumen['refis']['total']) }}</span>
                    <span class="fw-bold">Total: S/ {{ $fmt($granTotal) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Listado --}}
    <div class="card shadow-sm mt-2" wire:loading.class="opacity-50">
        <div class="card-body pb-2">
            <div class="table-responsive tableFixHead" style="max-height: 640px; overflow:auto;">
                <table class="table table-bordered table-striped table-hover table-sm align-middle">
                    <thead class="bg-primary">
                    <tr>
                        <th>#</th>
                        <th class="text-center">Exp.</th>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>DNI</th>
                        <th>F. Activación</th>
                        <th class="text-end">Capital</th>
                        <th class="text-end">INT %</th>
                        <th class="text-center">Cuotas</th>
                        <th class="text-center">Planilla</th>
                        <th>Asesor</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Situación</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($creditos as $cr)
                        @php $esRefi = ($cr->cod_rem ?? '') === 'REF'; @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-center">{{ $cr->client?->expediente ?: '—' }}</td>
                            <td>
                                <a href="{{ route('credits.show', $cr->id) }}" class="fw-semibold">{{ $cr->id }}</a>
                            </td>
                            <td>
                                @if($cr->client)
                                    <a href="{{ route('clients.show', $cr->client->id) }}">{{ $cr->client->fullName() }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $cr->client?->documento }}</td>
                            <td>{{ $cr->fecha_actualizacion?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ $fmt($cr->importe) }}</td>
                            <td class="text-end">{{ number_format($cr->interes, 0) }}%</td>
                            <td class="text-center">{{ $cr->cuotas }}</td>
                            <td class="text-center">{{ $cr->tipoPlanillaLabel() }}</td>
                            <td>{{ $cr->client?->asesor?->username ?? $cr->client?->asesor?->name ?? '—' }}</td>
                            <td class="text-center">
                                @if($esRefi)
                                    <span class="badge bg-warning text-dark" title="Refinanciado (cod_rem=REF)">REF</span>
                                @else
                                    <span class="badge bg-primary">Nuevo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $bs = match($cr->situacion) {
                                        'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                        'Refinanciado' => 'bg-warning text-dark', default => 'bg-danger',
                                    };
                                @endphp
                                <span class="badge {{ $bs }}">{{ $cr->situacion }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-4 text-muted text-center">
                                Sin desembolsos {{ $tipo !== 'todos' ? "({$tipo})" : '' }} en {{ $etiqueta }}.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if($creditos->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="6" class="text-end">Totales ({{ $creditos->count() }} créditos)</td>
                                <td class="text-end">{{ $fmt($creditos->sum('importe')) }}</td>
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="small text-muted mb-1 mt-1">
                Créditos <strong>activados</strong> en el período (fecha de activación), incluya o no su situación actual —
                por eso el conteo cuadra siempre con el Panel de Control. La columna Situación muestra el estado de hoy.
            </p>
        </div>
    </div>

</div>
