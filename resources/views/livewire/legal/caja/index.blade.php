<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CAJA LEGAL</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="{{ route('legal.caja') }}" class="f-s-14">Caja</a></li>
            </ul>
        </div>
    </div>

    {{-- Tarjetas del mes: totales SIN el filtro de texto --}}
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-success border-3 h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Total ingresos del mes</div>
                    <h5 class="mb-0 text-success">S/ {{ number_format($totalIngresos, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-danger border-3 h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Total egresos del mes</div>
                    <h5 class="mb-0 text-danger">S/ {{ number_format($totalEgresos, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start {{ $neto >= 0 ? 'border-success' : 'border-danger' }} border-3 h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Neto del mes</div>
                    <h5 class="mb-0 {{ $neto >= 0 ? 'text-success' : 'text-danger' }}">
                        S/ {{ number_format($neto, 2) }}
                    </h5>
                </div>
            </div>
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
                                <label class="form-label mb-0 small"><b>Mes</b></label>
                                <select class="form-select form-select-sm" wire:model.live="mes">
                                    @foreach($meses as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="Motivo o detalle del movimiento">
                            </div>
                            <div class="col-md-4 text-md-end">
                                @can('legal.caja')
                                    <button type="button" class="btn btn-sm btn-danger"
                                            wire:click="$dispatch('abrir-movimiento-caja')">
                                        <i class="ti ti-plus f-s-12"></i> Registrar movimiento
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </form>

                    {{-- Tabla --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="90">Fecha</th>
                                    <th class="text-center" width="80">Tipo</th>
                                    <th width="200">Motivo</th>
                                    <th>Detalle</th>
                                    <th width="200">Origen</th>
                                    <th class="text-end" width="110">Monto S/</th>
                                    <th width="140">Registrado por</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($movimientos as $m)
                                <tr wire:key="mov-{{ $m['tipo'] }}-{{ $m['id'] }}">
                                    <td class="text-center">{{ $m['date']->format('d/m/Y') }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $m['tipo'] === 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                                            {{ $m['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' }}
                                        </span>
                                    </td>
                                    <td>{{ $m['reason'] !== '' ? $m['reason'] : '—' }}</td>
                                    <td>
                                        @if($m['detail'] !== '')
                                            <small class="text-muted" title="{{ $m['detail'] }}">
                                                {{ \Illuminate\Support\Str::limit($m['detail'], 70) }}
                                            </small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(($m['origen']['tipo'] ?? null) === 'aviso' && $m['origen']['garantia_id'])
                                            <a href="{{ route('legal.garantias.show', $m['origen']['garantia_id']) }}"
                                               title="Ver la garantía del aviso SIGM #{{ $m['origen']['id'] }}">
                                                <i class="ti ti-file-certificate f-s-12"></i>
                                                Aviso SIGM &rarr; garantía #{{ $m['origen']['garantia_id'] }}
                                            </a>
                                        @elseif(($m['origen']['tipo'] ?? null) === 'tramite')
                                            <a href="{{ route('legal.notaria') }}"
                                               title="Ver el tablero notarial (trámite #{{ $m['origen']['id'] }})">
                                                <i class="ti ti-writing-sign f-s-12"></i>
                                                Notaría #{{ $m['origen']['id'] }}
                                            </a>
                                        @else
                                            <span class="badge bg-light text-dark border">Manual</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold {{ $m['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' }}">
                                        {{ $m['tipo'] === 'ingreso' ? '' : '-' }}{{ number_format($m['total'], 2) }}
                                    </td>
                                    <td>{{ $m['user'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-muted text-center">
                                        Sin movimientos de la Caja Legal en el mes seleccionado
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="6">MOVIMIENTOS</td>
                                    <td class="fw-bold">{{ $movimientos->count() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="form-text mb-1" style="font-size:10px;">
                        <i class="ti ti-info-circle"></i>
                        Los asientos con origen (Aviso SIGM / Notaría) se generan y corrigen desde su documento;
                        aquí solo se registran movimientos manuales del área.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal de movimiento manual (componente hijo) ═══ --}}
    <livewire:legal.caja.movimiento-modal />
</div>
