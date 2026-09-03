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
                    {{-- Contexto compacto (no estaba en legacy, agregado para confirmar antes de revertir) --}}
                    <div class="row mb-3" style="font-size: 13px;">
                        <div class="col-md-4"><b>Crédito:</b> #{{ $record->credit_id }}</div>
                        <div class="col-md-5"><b>Cliente:</b>
                            {{ trim(($record->credit?->client?->apellido_pat ?? '') . ' ' . ($record->credit?->client?->apellido_mat ?? '') . ' ' . ($record->credit?->client?->nombre ?? '')) }}
                        </div>
                        <div class="col-md-3"><b>Fecha:</b> {{ $record->date?->format('d/m/Y') }} {{ $record->time }}</div>
                    </div>

                    {{-- Detalle (1:1 con legacy editmasivo.php) --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0" style="font-size: 13px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="60">Item</th>
                                    <th class="text-center" width="80">Cuota</th>
                                    <th class="text-end" width="140">Monto</th>
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
                                    <td class="text-end">{{ number_format($det->amount, 2) }}</td>
                                    <td class="text-center">{{ $det->fecha?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-3 text-muted text-center">Sin detalle de cuotas</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="2" class="text-end"><b>Total</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumDet, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Acciones --}}
                    <div class="d-flex gap-2 mt-3">
                        @php
                            $esDeHoy = $record->date && $record->date->format('Y-m-d') === now()->format('Y-m-d');
                        @endphp
                        @if(auth()->user()->can('registro.eliminar-masivo.revertir')
                            && (auth()->user()->can('caja.editar-historico') || $esDeHoy))
                            <button type="button"
                                    wire:click="reverse"
                                    wire:confirm="¿Está seguro de revertir esta eliminación masiva? Se restaurarán las cuotas y el crédito."
                                    class="btn btn-sm btn-danger">
                                <i class="ti ti-trash"></i> Eliminar (revertir)
                            </button>
                        @endif
                        <a href="{{ route('credits.mass-delete') }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back"></i> Regresar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
