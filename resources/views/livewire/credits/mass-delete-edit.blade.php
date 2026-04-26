<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">ELIMINAR : MASIVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-file-text f-s-16"></i>
                    <a href="{{ route('credits.mass-delete') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Eliminar Masivo</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Detalle #{{ $record->id }}</span></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- Cabecera --}}
                    <div class="row mb-3" style="font-size: 13px;">
                        <div class="col-md-3"><b>Fecha:</b> {{ $record->date?->format('d/m/Y') }} {{ $record->time }}</div>
                        <div class="col-md-3"><b>Crédito:</b> #{{ $record->credit_id }}</div>
                        <div class="col-md-3"><b>Asesor:</b> {{ $record->advisor }}</div>
                        <div class="col-md-3"><b>Usuario:</b> {{ $record->performed_by ?? $record->user }}</div>
                        <div class="col-md-12 mt-1"><b>Cliente:</b>
                            {{ trim($record->credit?->client?->apellido_pat . ' ' . $record->credit?->client?->apellido_mat . ' ' . $record->credit?->client?->nombre) }}
                        </div>
                    </div>

                    {{-- Detalle --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" style="font-size: 12px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="60">Item</th>
                                    <th class="text-center" width="80">Cuota</th>
                                    <th class="text-center" width="80">Tipo</th>
                                    <th class="text-end" width="120">Monto</th>
                                    <th class="text-center">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php $sumDet = 0; @endphp
                            @forelse($record->details as $det)
                                @php $sumDet += (float) $det->amount; @endphp
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $det->installment?->num_cuota ?? '—' }}</td>
                                    <td class="text-center">
                                        @switch($det->tipo)
                                            @case('C') @case('C1') @case('C3')
                                                <span class="badge bg-primary">CAPITAL</span>
                                                @break
                                            @case('I') @case('I1')
                                                <span class="badge bg-info">INTERES</span>
                                                @break
                                            @case('M')
                                                <span class="badge bg-warning text-dark">MORA</span>
                                                @break
                                            @default
                                                {{ $det->tipo }}
                                        @endswitch
                                    </td>
                                    <td class="text-end">{{ number_format($det->amount, 2) }}</td>
                                    <td class="text-center">{{ $det->fecha?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-3 text-muted text-center">Sin detalle de cuotas</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="3" class="text-end"><b>Total</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumDet, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Acciones --}}
                    <div class="d-flex gap-2 mt-3">
                        @hasanyrole('superusuario|administrador|director|gerente')
                            <button type="button"
                                    wire:click="reverse"
                                    wire:confirm="¿Está seguro de revertir esta eliminación masiva? Se restaurarán las cuotas y el crédito."
                                    class="btn btn-sm btn-danger">
                                <i class="ti ti-trash"></i> Eliminar (revertir)
                            </button>
                        @endhasanyrole
                        <a href="{{ route('credits.mass-delete') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
