{{-- Lista de documentos del préstamo en curso (20/11/2026, ruta del expediente): en el orden en que se
     emiten —Contrato → Anexo 1 → Anexo 2 → DJ de propiedad → DJ de publicidad— y al final el SIGM, cada uno
     emitido o por emitir, y cuál sigue. Solo orienta: cada botón abre el mismo modal que el de la cabecera,
     ya con el préstamo de la lista elegido. Las DJ y el SIGM salen del contrato: hasta que exista, esperan.
     Recibe: $lista (Documentos::listaDocumentos). Usa $inscripcionesSigm, $embebido y $client de la pestaña. --}}
@if($lista['mostrar'])
    @php
        $credito = $lista['credito'];
        $sigmInscrito = $credito !== null ? ($inscripcionesSigm[$credito] ?? null) : null;
        $documentosListos = $lista['emitidos'] === $lista['total'];
        // El SIGM "sigue" cuando ya están todos los documentos y falta constituirlo.
        $sigueSigm = $credito !== null && $sigmInscrito === null && $documentosListos;
    @endphp
    <div class="lista-documentos mb-3">
        <style>
            .lista-documentos { border: 1px solid #e3e6ee; border-radius: 8px; padding: 10px 12px; background: #fff; }
            .lista-doc-cabecera { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
            .lista-doc-titulo { font-size: 13px; font-weight: 700; }
            .lista-doc-avance { font-size: 12px; font-weight: 600; color: #3a4152; }
            .lista-doc-aviso { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; margin-bottom: 8px;
                               padding: 6px 10px; border-radius: 6px; background: #fff4d6; color: #5c4400; font-size: 12px; font-weight: 600; }
            /* Rejilla: los que no caben en la fila bajan conservando su ancho. */
            .lista-doc-filas { display: grid; grid-template-columns: repeat(auto-fit, minmax(168px, 1fr)); gap: 6px; }
            .lista-doc { min-width: 0; min-height: 46px; display: flex; align-items: center; gap: 8px; padding: 5px 8px;
                         border: 2px solid #d9dee8; border-radius: 8px; background: #fff; font-size: 12px; line-height: 1.2; }
            .lista-doc.es-sigue { border-color: #0a6fb0; background: #eaf4fb; }
            .lista-doc-num { flex: 0 0 22px; width: 22px; height: 22px; border-radius: 11px; display: flex; align-items: center; justify-content: center;
                             font-size: 11px; font-weight: 700; background: #e6e9f0; color: #3a4152; }
            .lista-doc.es-emitido .lista-doc-num { background: #1a7a34; color: #fff; }
            .lista-doc.es-sigue .lista-doc-num { background: #0a6fb0; color: #fff; }
            .lista-doc-textos { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
            .lista-doc-nombre { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .lista-doc-estado { font-size: 11px; font-weight: 600; color: #5b6472; }
            .lista-doc.es-emitido .lista-doc-estado { color: #146c2e; }
            .lista-doc.es-sigue .lista-doc-estado { color: #0a5a8f; }
            .lista-doc .btn { flex: 0 0 auto; padding: 2px 9px; font-size: 11px; font-weight: 600; }
        </style>

        <div class="lista-doc-cabecera">
            <span class="lista-doc-titulo">
                <i class="ti ti-list-check f-s-14"></i>
                @if(! $embebido && count($lista['opciones']) > 1)
                    {{-- Varios préstamos activos: se elige de cuál se ve la lista (en la ficha se elige en la ruta). --}}
                    <label for="lista-doc-prestamo" class="mb-0">Documentos del préstamo</label>
                    <select id="lista-doc-prestamo" class="form-select form-select-sm d-inline-block w-auto ms-1 py-0" wire:model.live="listaCreditoId">
                        @foreach($lista['opciones'] as $opcion)
                            <option value="{{ $opcion['id'] }}">#{{ $opcion['id'] }} · {{ $opcion['fecha'] }} · {{ $opcion['emitidos'] }} de {{ $lista['total'] }}</option>
                        @endforeach
                    </select>
                @else
                    Documentos del préstamo{{ $credito !== null ? ' #'.$credito : '' }}
                @endif
            </span>
            <span class="lista-doc-avance">{{ $lista['emitidos'] }} de {{ $lista['total'] }} emitidos</span>
        </div>

        @if($credito === null)
            <div class="lista-doc-aviso">
                <span>Los documentos se emiten sobre un préstamo activo: primero crea el préstamo.</span>
                @if($embebido)
                    <button type="button" class="btn btn-sm btn-primary py-1" wire:click="$parent.$set('tab', 'prestamos')">
                        Ir a Préstamos <i class="ti ti-arrow-right"></i>
                    </button>
                @else
                    <a class="btn btn-sm btn-primary py-1" href="{{ route('clients.edit', ['id' => $client->id, 'tab' => 'prestamos']) }}">
                        Ir a Préstamos <i class="ti ti-arrow-right"></i>
                    </a>
                @endif
            </div>
        @endif

        <div class="lista-doc-filas">
            @foreach($lista['filas'] as $fila)
                <div @class(['lista-doc', 'es-emitido' => $fila['emitido'], 'es-sigue' => $fila['sigue']]) wire:key="lista-doc-{{ $fila['tipo'] }}"
                     title="{{ $fila['nombre'] }}">
                    <span class="lista-doc-num">
                        @if($fila['emitido'])
                            <i class="ti ti-check" aria-hidden="true"></i>
                        @else
                            {{ $fila['num'] }}
                        @endif
                    </span>
                    <span class="lista-doc-textos">
                        <span class="lista-doc-nombre">{{ $fila['corto'] }}</span>
                        <span class="lista-doc-estado">
                            @if($fila['emitido'])
                                Emitido
                            @elseif($fila['espera_contrato'])
                                Después del contrato
                            @else
                                {{ $fila['sigue'] ? 'Sigue este' : 'Por emitir' }}
                            @endif
                        </span>
                    </span>
                    @if($fila['puede_emitir'])
                        <button type="button" class="btn btn-sm btn-doc btn-doc-{{ $fila['tipo'] }}" wire:click="emitirDeLaLista('{{ $fila['tipo'] }}')"
                                aria-label="Emitir {{ $fila['nombre'] }}">
                            Emitir
                        </button>
                    @endif
                </div>
            @endforeach

            <div @class(['lista-doc', 'es-emitido' => $sigmInscrito !== null, 'es-sigue' => $sigueSigm]) wire:key="lista-doc-sigm"
                 title="Constitución de la garantía mobiliaria (SIGM)">
                <span class="lista-doc-num">
                    @if($sigmInscrito !== null)
                        <i class="ti ti-check" aria-hidden="true"></i>
                    @else
                        {{ $lista['total'] + 1 }}
                    @endif
                </span>
                <span class="lista-doc-textos">
                    <span class="lista-doc-nombre">SIGM</span>
                    <span class="lista-doc-estado">
                        @if($sigmInscrito !== null)
                            Constituido
                        @elseif(! $lista['tiene_contrato'])
                            Después del contrato
                        @else
                            {{ $sigueSigm ? 'Sigue este' : 'Sin constituir' }}
                        @endif
                    </span>
                </span>
                @if($sigmInscrito === null && $lista['tiene_contrato'])
                    <button type="button" class="btn btn-sm btn-doc btn-doc-sigm" wire:click="abrirModalSigm({{ $credito }})"
                            aria-label="Registrar la constitución del SIGM">
                        Registrar
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
