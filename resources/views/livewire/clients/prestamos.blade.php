<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Préstamos <span class="text-muted small fw-normal">({{ $conteo }})</span>
        </h6>
        @if($puedeCrear && ! $nuevo)
            <button type="button" class="btn btn-sm btn-success" wire:click="abrirNuevo">
                <i class="ti ti-plus"></i> Nuevo préstamo
            </button>
        @endif
    </div>

    {{-- ════════ Alta de crédito, aquí mismo (el formulario de /credits/create/{id}) ════════ --}}
    @if($nuevo)
        <div class="border rounded p-2 mb-3 alta-prestamo" style="background:#fffdf5;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold small" style="color:red;"><i class="ti ti-plus"></i> Nuevo préstamo para {{ $client->fullName() }}</span>
            </div>
            <livewire:credits.create :clientId="$clientId" :embebido="true" :key="'alta-'.$clientId" />
        </div>
    @endif

    {{-- ════════ Tabla ════════ --}}
    @if($credits->isEmpty())
        <p class="text-muted small mb-0">Este cliente aún no tiene préstamos.</p>
    @else
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
            <table class="table table-bordered table-striped table-hover table-sm mb-0 tabla-prestamos" style="font-size: 11px;">
                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                    <tr>
                        <th class="text-center">N°</th>
                        <th class="text-center">Código</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-center">Vence</th>
                        <th class="text-end">Capital</th>
                        <th class="text-center">%</th>
                        <th class="text-center">C.</th>
                        <th class="text-center">T.C.</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-center">Situación</th>
                        <th class="text-center">Asesor</th>
                        <th class="text-center">Usuario</th>
                        <th class="text-center">Op.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($credits as $credit)
                        @php
                            $interes = round((float) $credit->importe * (float) $credit->interes / 100, 2);
                            if ((int) $credit->tipo_planilla === 3) {
                                $interes = round($interes * max(1, (int) $credit->cuotas), 2);
                            }
                            $total = (float) $credit->importe + $interes;
                            $pagado = $resumen[$credit->id]['pagado'] ?? 0.0;
                            // Solo un crédito activo debe algo: el cancelado o refinanciado
                            // puede dejar cuotas sin aplicar en el cronograma (refi legacy), pero su deuda ya no está aquí.
                            $saldo = $credit->situacion === 'Activo' ? ($resumen[$credit->id]['saldo'] ?? max(0.0, $total - $pagado)) : 0.0;
                            $badge = match ($credit->situacion) {
                                'Activo' => 'bg-success',
                                'Refinanciado' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            $esNuevo = $recienCreado === $credit->id;
                        @endphp
                        <tr @class(['table-success fila-nueva' => $esNuevo]) wire:key="prestamo-{{ $credit->id }}">
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="text-center fw-bold">
                                @if($puedeVerCreditos)
                                    <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">{{ $credit->id }}</a>
                                @else
                                    {{ $credit->id }}
                                @endif
                                @if($esNuevo)<span class="badge bg-success ms-1" style="font-size:9px;">nuevo</span>@endif
                            </td>
                            <td class="text-center">{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format((float) $credit->importe, 2) }}</td>
                            <td class="text-center">{{ (int) $credit->interes == (float) $credit->interes ? (int) $credit->interes : number_format((float) $credit->interes, 2) }}</td>
                            <td class="text-center">{{ $credit->cuotas }}</td>
                            <td class="text-center">{{ $credit->tipoPlanillaLabel() }}</td>
                            <td class="text-end">{{ number_format($total, 2) }}</td>
                            <td class="text-end">{{ number_format($pagado, 2) }}</td>
                            <td class="text-end {{ $saldo > 0 ? 'fw-bold text-danger' : '' }}">{{ number_format($saldo, 2) }}</td>
                            <td class="text-center"><span class="badge {{ $badge }}" style="font-size:9px;">{{ $credit->situacion }}</span></td>
                            <td class="text-center">{{ $credit->asesor ?: '-' }}</td>
                            <td class="text-center">{{ $credit->user?->username ?? $credit->usuario ?? '-' }}</td>
                            <td class="text-center text-nowrap">
                                @if($puedeVerCreditos)
                                    <a href="{{ route('credits.schedule', $credit->id) }}" class="btn btn-xs btn-outline-primary" style="padding: 1px 6px; font-size: 10px;" title="Cronograma">
                                        <i class="ti ti-calendar"></i>
                                    </a>
                                @endif
                                @if($credit->situacion === 'Activo')
                                    @can('pagos')
                                        <a href="{{ route('payments.create', $credit->id) }}" class="btn btn-xs btn-outline-success" style="padding: 1px 6px; font-size: 10px;" title="Cobrar">
                                            <i class="ti ti-currency-dollar"></i>
                                        </a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
