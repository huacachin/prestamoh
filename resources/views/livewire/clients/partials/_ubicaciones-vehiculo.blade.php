{{-- Ubicaciones GPS de UNA placa (pestaña GPS del cliente, 10/10/2026): tabla
     del último registro hacia abajo, o el aviso de que aún no hay. Lo usa
     gps-vehiculos.blade.php por cada vehículo. $ubicaciones, $placa, $puedeEditar.
     En el celular se apila (tabla-apilable, CSS en gps.blade.php). --}}
@if($ubicaciones->isEmpty())
    <div class="text-muted small fst-italic">Sin ubicaciones todavía.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover tabla-ubicaciones tabla-apilable">
            <thead class="table-secondary">
                <tr>
                    <th class="text-center" width="130">Fecha de registro</th>
                    <th class="text-center" width="210">Coordenadas</th>
                    <th>Descripción</th>
                    <th class="text-center" width="110">Registró</th>
                    @if($puedeEditar)<th width="40"></th>@endif
                </tr>
            </thead>
            <tbody>
            @foreach($ubicaciones as $r)
                <tr wire:key="ubicacion-{{ $r->id }}">
                    <td class="text-center" style="white-space:nowrap;" data-label="Fecha">{{ $r->fecha->format('d/m/Y') }} <span class="text-muted">{{ $r->fecha->format('H:i') }}</span></td>
                    <td class="text-center" style="white-space:nowrap;" data-label="Coordenadas">
                        @if($r->tieneCoordenadas())
                            <span class="font-monospace">{{ $r->coordenadas() }}</span>
                            <a href="{{ $r->enlaceMaps() }}" target="_blank" rel="noopener" class="text-success ms-1" title="Abrir en Google Maps (pestaña nueva)"><i class="ti ti-map-pin"></i></a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="white-space:pre-line;" data-label="Descripción">{{ $r->descripcion ?: '—' }}</td>
                    <td class="text-center" data-label="Registró">{{ $r->registradoPor?->username ?? $r->registradoPor?->name ?? '—' }}</td>
                    @if($puedeEditar)
                        <td class="text-center celda-acciones">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0" wire:click="eliminar({{ $r->id }})" @creadoEl($r)
                                    data-confirmar="¿Eliminar la ubicación del {{ $r->fecha->format('d/m/Y H:i') }} del vehículo {{ $placa }}?" title="Eliminar">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
