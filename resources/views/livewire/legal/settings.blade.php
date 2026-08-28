<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">CONFIGURACIÓN LEGAL</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Área Legal</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="{{ route('legal.settings') }}" class="f-s-14">Configuración</a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('legal_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('legal_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('legal_error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('legal_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        // Etiqueta legible de un sub-campo: dni → DNI, partida_poder → Partida poder
        $tituloCampo = fn (string $s) => $s === 'dni' ? 'DNI' : ucfirst(str_replace('_', ' ', $s));
    @endphp

    <form wire:submit.prevent="guardar">
        <div class="row">
            @foreach($valores as $clave => $valor)
                @php
                    $tipo = $tipos[$clave];
                    $col = ['texto' => 'col-xl-4 col-md-6', 'mapa' => 'col-xl-6', 'lista' => 'col-xl-12'][$tipo];
                @endphp
                <div class="{{ $col }}">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 f-s-13">
                                <i class="ti ti-adjustments-alt f-s-14"></i>
                                {{ $etiquetas[$clave] ?? $clave }}
                            </h6>
                            <span class="badge bg-light text-secondary" style="font-size: 10px;">{{ $clave }}</span>
                        </div>
                        <div class="card-body py-2">

                            {{-- Valor simple (string) --}}
                            @if($tipo === 'texto')
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm"
                                       wire:model="valores.{{ $clave }}">

                            {{-- Objeto (mapa): un input por sub-campo --}}
                            @elseif($tipo === 'mapa')
                                <div class="row g-2">
                                    @foreach($valor as $sub => $v)
                                        <div class="{{ $sub === 'domicilio' ? 'col-12' : 'col-md-6' }}">
                                            <label class="form-label mb-0 small"><b>{{ $tituloCampo($sub) }}</b></label>
                                            <input type="text" autocomplete="off"
                                                   class="form-control form-control-sm @error("valores.{$clave}.{$sub}") is-invalid @enderror"
                                                   wire:model="valores.{{ $clave }}.{{ $sub }}"
                                                   @if($sub === 'dni') maxlength="8" inputmode="numeric" @endif>
                                            @error("valores.{$clave}.{$sub}")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>

                            {{-- Lista de objetos: tabla editable --}}
                            @else
                                @php $columnas = $columnasListas[$clave] ?? []; @endphp
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-2" style="font-size: 11px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th class="text-center" width="40">N°</th>
                                                @foreach($columnas as $sub)
                                                    <th>{{ $tituloCampo($sub) }}</th>
                                                @endforeach
                                                <th class="text-center" width="70">Quitar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($valor as $i => $fila)
                                                <tr wire:key="{{ $clave }}-{{ $i }}">
                                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                                    @foreach($columnas as $sub)
                                                        <td>
                                                            <input type="text" autocomplete="off"
                                                                   class="form-control form-control-sm @error("valores.{$clave}.{$i}.{$sub}") is-invalid @enderror"
                                                                   wire:model="valores.{{ $clave }}.{{ $i }}.{{ $sub }}"
                                                                   @if($sub === 'dni') maxlength="8" inputmode="numeric" @endif>
                                                            @error("valores.{$clave}.{$i}.{$sub}")
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                    @endforeach
                                                    <td class="text-center align-middle">
                                                        <button type="button" class="btn btn-xs btn-danger"
                                                                style="padding: 2px 8px; font-size: 10px;"
                                                                title="Quitar fila"
                                                                wire:click="quitarFila('{{ $clave }}', {{ $i }})">
                                                            <i class="ti ti-trash f-s-12"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ count($columnas) + 2 }}" class="text-center text-muted py-3">
                                                        Sin registros
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        wire:click="agregarFila('{{ $clave }}')">
                                    <i class="ti ti-plus f-s-12"></i> Agregar fila
                                </button>
                            @endif

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary"
                    wire:loading.attr="disabled" wire:target="guardar">
                <span wire:loading.remove wire:target="guardar">
                    <i class="ti ti-device-floppy f-s-14"></i> Guardar cambios
                </span>
                <span wire:loading wire:target="guardar">
                    Guardando…
                </span>
            </button>
        </div>
    </form>
</div>
