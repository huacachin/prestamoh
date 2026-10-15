{{-- Ubicaciones GPS de UNA placa (pestaña GPS del cliente, 10/10/2026): tabla
     del último registro hacia abajo, o el aviso de que aún no hay. Lo usa
     gps-vehiculos.blade.php por cada vehículo. $ubicaciones, $placa, $puedeEditar.
     En el celular se apila (tabla-apilable, CSS en gps.blade.php).
     Columnas (14/10, Antony): Op. · N° · Fecha · Usuario · Descripción · Coordenadas. --}}
@if($ubicaciones->isEmpty())
    <div class="text-muted small fst-italic">Sin ubicaciones todavía.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover tabla-ubicaciones tabla-apilable">
            <thead class="table-secondary">
                <tr>
                    @if($puedeEditar)<th class="text-center" width="40">Op.</th>@endif
                    <th class="text-center" width="40">N°</th>
                    <th class="text-center" width="120">Fecha</th>
                    <th class="text-center" width="100">Usuario</th>
                    <th>Descripción</th>
                    <th class="text-center" width="210">Coordenadas</th>
                </tr>
            </thead>
            <tbody>
            @foreach($ubicaciones as $r)
                <tr wire:key="ubicacion-{{ $r->id }}">
                    @if($puedeEditar)
                        <td class="text-center celda-acciones">
                            {{-- 12/10: editar, solo lo registrado hoy salvo el director (el servidor lo vuelve a comprobar).
                                 13/10 (Antony): en la fila solo va el lápiz; Eliminar sale dentro del formulario de edición. --}}
                            @if($this->puedeEditarUbicacion($r))
                                <button type="button" class="btn btn-sm btn-outline-primary py-0" wire:click="editar({{ $r->id }})" title="Editar">
                                    <i class="ti ti-pencil"></i>
                                </button>
                            @endif
                        </td>
                    @endif
                    <td class="text-center" data-label="N°">{{ $loop->iteration }}</td>
                    <td class="text-center" style="white-space:nowrap;" data-label="Fecha">{{ $r->fecha->format('d/m/Y') }} <span class="text-muted">{{ $r->fecha->format('H:i') }}</span></td>
                    <td class="text-center" data-label="Usuario">{{ $r->registradoPor?->username ?? $r->registradoPor?->name ?? '—' }}</td>
                    <td style="white-space:pre-line;" data-label="Descripción">{{ $r->descripcion ?: '—' }}</td>
                    <td class="text-center" style="white-space:nowrap;" data-label="Coordenadas">
                        @if($r->tieneCoordenadas())
                            <span class="font-monospace">{{ $r->coordenadas() }}</span>
                            <a href="{{ $r->enlaceMaps() }}" target="_blank" rel="noopener" class="text-success ms-1" title="Abrir en Google Maps (pestaña nueva)"><i class="ti ti-map-pin"></i></a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
