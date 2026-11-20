{{-- Barra "Ruta del expediente" (20/11/2026): el paso a paso de un préstamo, con el estado de cada paso.
     · En la ficha del cliente (componente Clients\RutaExpediente) es la navegación: cada paso abre su
       pantalla, se recorre con Anterior / Siguiente y, si el cliente tiene varios préstamos activos, se
       elige de cuál se ven los pasos.
     · En el alta de cliente es el único indicador de pasos y sigue al wizard (RutaExpediente::enAlta trae
       'actual', el paso en el que se está, y 'aviso', qué se hace ahí).
     Recibe: $ruta (App\Support\RutaExpediente), $tab (pestaña abierta o null), $navegable (bool) y,
     opcional, $prestamos (RutaExpediente::prestamosActivos, para elegir préstamo). --}}
@use('App\Support\RutaExpediente', 'Ruta')
@php
    $siguiente = $ruta['siguiente'];
    $porcentaje = $ruta['total'] > 0 ? (int) round($ruta['hechos'] / $ruta['total'] * 100) : 0;
    $aviso = $ruta['aviso'] ?? null;
    $prestamos = $prestamos ?? [];
    $enAlta = ($ruta['actual'] ?? null) !== null;
    // Pasos de la pantalla abierta: en el alta lo dice la ruta; en la ficha, los de la pestaña (Documentos
    // y SIGM comparten pantalla).
    $actuales = $enAlta
        ? [$ruta['actual']]
        : array_column(array_filter($ruta['pasos'], fn (array $p) => $tab !== null && $p['tab'] === $tab), 'clave');
    // El paso resaltado: en el alta, donde se está ("Estás aquí"); en la ficha, el que falta ("Sigue este").
    $resaltado = $enAlta ? $ruta['actual'] : ($siguiente['clave'] ?? null);
    $etiqueta = $enAlta ? 'Estás aquí' : 'Sigue este';
    $enElQueToca = $siguiente !== null && $tab !== null && $siguiente['tab'] === $tab;
    // Anterior / Siguiente: las pantallas de la ruta en su orden, sin las de los pasos que no aplican.
    $pantallas = [];
    foreach ($ruta['pasos'] as $p) {
        if ($p['estado'] !== Ruta::NO_APLICA || $p['tab'] === $tab) {
            $pantallas[$p['tab']] ??= $p['nombre'];
        }
    }
    $orden = array_keys($pantallas);
    $posicion = $tab !== null ? array_search($tab, $orden, true) : false;
    $anterior = $posicion !== false && $posicion > 0 ? $orden[$posicion - 1] : null;
    $posterior = $posicion !== false && $posicion < count($orden) - 1 ? $orden[$posicion + 1] : null;
@endphp
<div class="ruta-expediente mb-3">
    <style>
        .ruta-expediente { border: 1px solid #e3e6ee; border-radius: 8px; padding: 10px 12px; background: #fff; }
        .ruta-cabecera { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .ruta-titulo { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; }
        .ruta-prestamo { font-weight: 600; color: #3a4152; }
        .ruta-prestamo select { font-size: 12px; font-weight: 600; }
        .ruta-avance { font-size: 12px; font-weight: 600; color: #3a4152; }
        .ruta-barra { height: 6px; border-radius: 3px; background: #e6e9f0; overflow: hidden; margin-bottom: 8px; }
        .ruta-barra > div { height: 6px; border-radius: 3px; background: #1a7a34; }
        /* Rejilla, no flex: si no caben en una fila, los que bajan conservan su ancho en vez de estirarse. */
        .ruta-pasos { display: grid; grid-template-columns: repeat(auto-fit, minmax(118px, 1fr)); gap: 6px; }
        .ruta-paso { min-width: 0; min-height: 46px; display: flex; align-items: center; gap: 8px; text-align: left; padding: 5px 8px;
                     border: 2px solid #d9dee8; border-radius: 8px; background: #fff; color: #1f2433; font-size: 12px; line-height: 1.2; }
        button.ruta-paso { cursor: pointer; }
        .ruta-paso.es-actual { border-color: #0a6fb0; background: #eaf4fb; }
        .ruta-num { flex: 0 0 22px; width: 22px; height: 22px; border-radius: 11px; display: flex; align-items: center; justify-content: center;
                    font-size: 11px; font-weight: 700; background: #e6e9f0; color: #3a4152; }
        .ruta-paso.es-hecho .ruta-num { background: #1a7a34; color: #fff; }
        .ruta-paso.es-sigue .ruta-num { background: #0a6fb0; color: #fff; }
        .ruta-paso.es-no-aplica { border-style: dashed; color: #5b6472; }
        .ruta-textos { display: flex; flex-direction: column; min-width: 0; }
        .ruta-nombre { font-weight: 600; }
        .ruta-detalle { font-size: 11px; font-weight: 600; color: #5b6472; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ruta-paso.es-hecho .ruta-detalle { color: #146c2e; }
        .ruta-paso.es-sigue .ruta-detalle { color: #0a5a8f; }
        .ruta-aviso { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; margin-top: 8px;
                      padding: 6px 10px; border-radius: 6px; background: #eef2f8; font-size: 12px; font-weight: 600; }
        .ruta-aviso.es-completa { background: #dff3e4; color: #146c2e; }
        .ruta-aviso .btn { font-size: 12px; }
        .ruta-mensaje { flex: 1 1 220px; text-align: center; }
    </style>

    <div class="ruta-cabecera">
        <span class="ruta-titulo">
            <span><i class="ti ti-route f-s-14"></i> Ruta del expediente</span>
            @if($navegable && $ruta['credito'] !== null)
                @if(count($prestamos) > 1)
                    {{-- Varios préstamos activos: se elige de cuál se ven los pasos. La clave rehace el
                         selector cuando el préstamo cambia desde fuera (uno recién creado). --}}
                    <span class="ruta-prestamo">
                        <label for="ruta-prestamo" class="mb-0">· Pasos del préstamo</label>
                        <select id="ruta-prestamo" class="form-select form-select-sm d-inline-block w-auto py-0" wire:key="ruta-prestamo-{{ $ruta['credito'] }}"
                                wire:change="$parent.$set('prestamo', $event.target.value)">
                            @foreach($prestamos as $opcion)
                                <option value="{{ $opcion['id'] }}" @selected($opcion['id'] === $ruta['credito'])>
                                    #{{ $opcion['id'] }} · {{ $opcion['fecha'] }}{{ $opcion['refinanciacion'] ? ' · refinanciación' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                @else
                    <span class="ruta-prestamo">· Pasos del préstamo #{{ $ruta['credito'] }}</span>
                @endif
            @endif
        </span>
        <span class="ruta-avance">{{ $ruta['hechos'] }} de {{ $ruta['total'] }} pasos</span>
    </div>
    <div class="ruta-barra" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $ruta['total'] }}" aria-valuenow="{{ $ruta['hechos'] }}"
         aria-label="Avance del expediente">
        <div style="width: {{ $porcentaje }}%"></div>
    </div>

    <div class="ruta-pasos">
        @foreach($ruta['pasos'] as $paso)
            @php
                $sigue = $resaltado === $paso['clave'];
                $clases = 'ruta-paso'
                    .($paso['estado'] === Ruta::HECHO ? ' es-hecho' : '')
                    .($paso['estado'] === Ruta::NO_APLICA ? ' es-no-aplica' : '')
                    .($sigue ? ' es-sigue' : '')
                    .(in_array($paso['clave'], $actuales, true) ? ' es-actual' : '');
                $detalle = $sigue ? $etiqueta.' · '.$paso['detalle'] : $paso['detalle'];
                $titulo = $paso['nombre'].': '.$paso['detalle'];
            @endphp
            @if($navegable)
                <button type="button" class="{{ $clases }}" title="{{ $titulo }}" wire:key="ruta-{{ $paso['clave'] }}"
                        wire:click="$parent.$set('tab', '{{ $paso['tab'] }}')">
            @else
                <div class="{{ $clases }}" title="{{ $titulo }}">
            @endif
                <span class="ruta-num">
                    @if($paso['estado'] === Ruta::HECHO)
                        <i class="ti ti-check" aria-hidden="true"></i>
                    @else
                        {{ $paso['num'] }}
                    @endif
                </span>
                <span class="ruta-textos">
                    <span class="ruta-nombre">{{ $paso['nombre'] }}</span>
                    <span class="ruta-detalle">{{ $detalle }}</span>
                </span>
            @if($navegable)
                </button>
            @else
                </div>
            @endif
        @endforeach
    </div>

    <div class="ruta-aviso {{ $ruta['completa'] && $aviso === null ? 'es-completa' : '' }}">
        @if($aviso !== null || ! $navegable)
            <span>{{ $aviso }}</span>
        @else
            {{-- Anterior / Siguiente recorren las pantallas en orden, estén completas o no. --}}
            <button type="button" class="btn btn-sm btn-outline-secondary py-1" @disabled($anterior === null)
                    @if($anterior !== null) wire:click="$parent.$set('tab', '{{ $anterior }}')" @endif>
                <i class="ti ti-arrow-left"></i> Anterior{{ $anterior !== null ? ': '.$pantallas[$anterior] : '' }}
            </button>
            <span class="ruta-mensaje">
                @if($ruta['completa'])
                    <i class="ti ti-circle-check"></i> Expediente completo.
                @elseif($enElQueToca)
                    Estás en el paso que falta: {{ $siguiente['accion'] }}.
                @else
                    Falta {{ $siguiente['accion'] }}.
                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline fw-bold" wire:click="$parent.$set('tab', '{{ $siguiente['tab'] }}')">Ir a ese paso</button>
                @endif
            </span>
            <button type="button" class="btn btn-sm btn-primary py-1" @disabled($posterior === null)
                    @if($posterior !== null) wire:click="$parent.$set('tab', '{{ $posterior }}')" @endif>
                Siguiente{{ $posterior !== null ? ': '.$pantallas[$posterior] : '' }} <i class="ti ti-arrow-right"></i>
            </button>
        @endif
    </div>
</div>
