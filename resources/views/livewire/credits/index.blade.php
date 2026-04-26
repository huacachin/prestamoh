<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">PRESTAMOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Registro</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Prestamo</a></li>
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
                                <label class="form-label mb-0 small"><b>Nombre</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="nombre" placeholder="Nombres">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Código</b></label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.live.debounce.300ms="codigo" placeholder="Código">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Asesor</b></label>
                                <select class="form-select form-select-sm" wire:model.live="ejecutivo">
                                    <option value="">Todos</option>
                                    @foreach($asesores as $asesor)
                                        <option value="{{ $asesor->id }}">{{ $asesor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Tipo</b></label>
                                <select class="form-select form-select-sm" wire:model.live="seletipl">
                                    <option value="0000">Todos</option>
                                    <option value="1">Semanal</option>
                                    <option value="3">Mensual</option>
                                    <option value="4">Diario</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            <a href="{{ route('credits.create') }}" class="btn btn-sm btn-danger">
                                <i class="ti ti-plus f-s-12"></i> Nuevo
                            </a>
                            <a href="{{ route('exports.credits') }}?nombre={{ $nombre }}&codigo={{ $codigo }}&ejecutivo={{ $ejecutivo }}&seletipl={{ $seletipl }}"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                        </div>
                    </form>

                    @php
                        $isSuperUsuario = auth()->user()->hasRole('superusuario');
                        $hoy = now()->format('Y-m-d');
                        $tcLabels = [1 => 'S', 3 => 'M', 4 => 'D'];
                    @endphp

                    {{-- Tabla Desktop --}}
                    <div class="table-responsive d-none d-md-block" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" width="40">N°</th>
                                    <th class="text-center" width="80">Fecha</th>
                                    <th class="text-center" width="80">Usuario</th>
                                    <th class="text-center" width="60">Código</th>
                                    <th class="text-center">Nombre</th>
                                    <th class="text-end" width="80">Capital</th>
                                    <th class="text-center" width="40">%</th>
                                    <th class="text-end" width="80">S/</th>
                                    <th class="text-center" width="40">C.</th>
                                    <th class="text-end" width="80">Total</th>
                                    <th class="text-end" width="80">Pago</th>
                                    <th class="text-end" width="80">Saldo</th>
                                    <th class="text-center" width="80">Asesor</th>
                                    <th class="text-center" width="40">T.C.</th>
                                    <th class="text-center" width="80">Op.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($credits as $credit)
                                @php
                                    $iapli  = $pagosMap[$credit->id]['iapli'] ?? 0;
                                    $aplido = $pagosMap[$credit->id]['aplido'] ?? 0;
                                    $interS = round(($credit->importe * $credit->interes) / 100, 2);
                                    $totalC = $credit->importe + $interS;
                                    $pago   = $iapli + $aplido;
                                    $saldo  = $credit->importe - $iapli - $aplido + $interS;
                                    $tcLabel = $tcLabels[$credit->tipo_planilla] ?? '?';
                                    $canDelete = ($iapli <= 0) && ($isSuperUsuario || (
                                        $credit->fecha_prestamo?->format('Y-m-d') === $hoy && !$credit->refinanciado
                                    ));
                                @endphp
                                <tr onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $credit->user?->username ?? $credit->user?->name }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">
                                            {{ $credit->id }}
                                        </a>
                                    </td>
                                    <td>{{ trim($credit->client?->apellido_pat . ' ' . $credit->client?->apellido_mat . ' ' . $credit->client?->nombre) }}</td>
                                    <td class="text-end">{{ number_format($credit->importe, 2) }}</td>
                                    <td class="text-center">
                                        @if((int)$credit->interes == (float)$credit->interes)
                                            {{ (int) $credit->interes }}
                                        @else
                                            {{ number_format($credit->interes, 2) }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($interS, 2) }}</td>
                                    <td class="text-center">{{ $credit->cuotas }}</td>
                                    <td class="text-end">{{ number_format($totalC, 2) }}</td>
                                    <td class="text-end">{{ number_format($pago, 2) }}</td>
                                    <td class="text-end">{{ number_format($saldo, 2) }}</td>
                                    <td class="text-center">{{ $credit->client?->asesor?->username ?? $credit->client?->asesor?->name ?? '-' }}</td>
                                    <td class="text-center fw-bold">{{ $tcLabel }}</td>
                                    <td class="text-center">
                                        @if($canDelete)
                                            <button class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;"
                                                    wire:confirm="¿Está seguro de eliminar este Préstamo? Este proceso no es reversible."
                                                    wire:click="delete({{ $credit->id }})">
                                                Eliminar
                                            </button>
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
                                    <td colspan="5" class="text-end fw-bold">TOTALES:</td>
                                    <td class="text-end fw-bold">{{ number_format($sumtotal, 2) }}</td>
                                    <td></td>
                                    <td class="text-end fw-bold">{{ number_format($suminter, 2) }}</td>
                                    <td></td>
                                    <td class="text-end fw-bold">{{ number_format($sumtotax, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($sumpagos, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($sumsaldo, 2) }}</td>
                                    <td colspan="3" class="text-center">{{ $credits->count() }} créditos</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($credits as $credit)
                            @php
                                $iapli  = $pagosMap[$credit->id]['iapli'] ?? 0;
                                $aplido = $pagosMap[$credit->id]['aplido'] ?? 0;
                                $interS = round(($credit->importe * $credit->interes) / 100, 2);
                                $totalC = $credit->importe + $interS;
                                $pago   = $iapli + $aplido;
                                $saldo  = $credit->importe - $iapli - $aplido + $interS;
                                $tcLabel = $tcLabels[$credit->tipo_planilla] ?? '?';
                                $canDelete = ($iapli <= 0) && ($isSuperUsuario || (
                                    $credit->fecha_prestamo?->format('Y-m-d') === $hoy && !$credit->refinanciado
                                ));
                            @endphp
                            <div class="card mb-2 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">
                                                #{{ $credit->id }} - {{ trim($credit->client?->apellido_pat . ' ' . $credit->client?->apellido_mat . ' ' . $credit->client?->nombre) }}
                                            </a>
                                        </h6>
                                        <span class="badge bg-primary">{{ $tcLabel }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Capital:</b> {{ number_format($credit->importe, 2) }}</div>
                                        <div class="col-6"><b>%:</b> {{ $credit->interes }}</div>
                                        <div class="col-6"><b>Cuotas:</b> {{ $credit->cuotas }}</div>
                                        <div class="col-6"><b>Total:</b> {{ number_format($totalC, 2) }}</div>
                                        <div class="col-6"><b>Pago:</b> {{ number_format($pago, 2) }}</div>
                                        <div class="col-6 text-danger fw-bold"><b>Saldo:</b> {{ number_format($saldo, 2) }}</div>
                                        <div class="col-6"><b>Fecha:</b> {{ $credit->fecha_prestamo?->format('d/m/Y') }}</div>
                                        <div class="col-6"><b>Asesor:</b> {{ $credit->client?->asesor?->username ?? '-' }}</div>
                                    </div>
                                    @if($canDelete)
                                        <button class="btn btn-xs btn-danger w-100 mt-2" style="font-size: 10px;"
                                                wire:confirm="¿Está seguro de eliminar este Préstamo?"
                                                wire:click="delete({{ $credit->id }})">
                                            <i class="ti ti-trash"></i> Eliminar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="text-center mt-2">
                            <span class="badge bg-primary">Total: {{ number_format($sumtotal, 2) }} | Saldo: {{ number_format($sumsaldo, 2) }} | {{ $credits->count() }} créditos</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
