<div class="container-fluid">
    <style>
        .campo-api { color:#c0392b !important; font-weight:600; border-color:#e6a6a0 !important; background-color:#fff7f6 !important; }
    </style>
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">AVAL DEL CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Aval #{{ $client->expediente ?? $clientId }}</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            {{-- Header con cliente --}}
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-0" style="color:red;">{{ $client->fullName() }}</h6>
                    <small class="text-muted">
                        Exp. {{ $client->expediente }} · DNI/RUC {{ $client->documento }}
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('clients.show', $client->id) }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-arrow-back"></i> Regresar al cliente
                    </a>
                </div>
            </div>

            @unless($puedeEditar)
                <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                    <i class="ti ti-info-circle"></i>
                    Como Asesor solo puedes ver los avales. No puedes agregar ni eliminar.
                </div>
            @endunless

            {{-- Lista de avales actuales --}}
            <h6 class="mb-1" style="color:red;">Avales registrados</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-striped table-hover" style="font-size:12px;">
                    <thead class="bg-primary">
                        <tr>
                            <th class="text-center" style="width:60px;">Código</th>
                            <th>Nombre</th>
                            <th class="text-center" style="width:120px;">DNI</th>
                            <th>Dirección</th>
                            <th class="text-center" style="width:120px;">Teléfono</th>
                            @if($puedeEditar)
                                <th class="text-center" style="width:80px;">Op.</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($avales as $a)
                            <tr>
                                <td class="text-center">{{ $a->id }}</td>
                                <td>{{ $a->nombre }}</td>
                                <td class="text-center">{{ $a->dni }}</td>
                                <td>{{ $a->direccion ?? '—' }}</td>
                                <td class="text-center">{{ $a->telefono ?? '—' }}</td>
                                @if($puedeEditar)
                                    <td class="text-center">
                                        <button type="button" class="btn btn-xs btn-danger"
                                                style="padding:2px 8px; font-size:10px;"
                                                wire:click="questionDelete({{ $a->id }})">
                                            <i class="ti ti-trash"></i> Eliminar
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $puedeEditar ? 6 : 5 }}" class="text-center text-muted py-3">
                                    Este cliente aún no tiene avales registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($puedeEditar)
                <hr class="my-2" style="border-color:#e8e2d5;">

                {{-- Form para agregar nuevo aval --}}
                <h6 class="mb-1" style="color:red;">Nuevo aval</h6>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit.prevent="save">
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small fw-semibold">DNI / RUC</label>
                            <input type="text" class="form-control form-control-sm @error('dni') is-invalid @enderror"
                                   wire:model.defer="dni"
                                   name="dni" autocomplete="off"
                                   placeholder="DNI (8) o RUC (11)"
                                   maxlength="11"
                                   wire:keydown.enter.prevent="consultarDni">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-danger w-100"
                                    wire:click="consultarDni"
                                    wire:loading.attr="disabled" wire:target="consultarDni">
                                <span wire:loading.remove wire:target="consultarDni">
                                    <i class="ti ti-search"></i> Consultar
                                </span>
                                <span wire:loading wire:target="consultarDni">
                                    <i class="ti ti-loader-2"></i> Buscando…
                                </span>
                            </button>
                        </div>
                        <div class="col-md-7">
                            @if($dniMsg)
                                @php
                                    $alertClass = match($dniMsgType) {
                                        'ok'   => 'alert-success',
                                        'warn' => 'alert-warning',
                                        'err'  => 'alert-danger',
                                        default => 'alert-info',
                                    };
                                    $icon = match($dniMsgType) {
                                        'ok'   => 'ti-circle-check',
                                        'warn' => 'ti-alert-triangle',
                                        'err'  => 'ti-alert-circle',
                                        default => 'ti-info-circle',
                                    };
                                @endphp
                                <div class="alert {{ $alertClass }} mb-0 py-1 px-2" style="font-size:12px;">
                                    <i class="ti {{ $icon }}"></i> {{ $dniMsg }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label mb-0 small fw-semibold">Nombre</label>
                            <input type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror @if(in_array('nombre', $autoCampos)) campo-api @endif"
                                   wire:model.defer="nombre" name="nombre" autocomplete="name" placeholder="Apellidos y nombres">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0 small fw-semibold">Dirección</label>
                            <input type="text" class="form-control form-control-sm @if(in_array('direccion', $autoCampos)) campo-api @endif"
                                   wire:model.defer="direccion" name="direccion" autocomplete="street-address" placeholder="Av. Arequipa Nro. 3400">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0 small fw-semibold">Teléfono</label>
                            <input type="text" class="form-control form-control-sm @if(in_array('telefono', $autoCampos)) campo-api @endif"
                                   wire:model.defer="telefono" name="telefono" autocomplete="tel" placeholder="999-999-999">
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button type="submit" class="btn btn-sm btn-dark"
                                wire:loading.attr="disabled" wire:target="save">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="save">Guardar aval</span>
                            <span wire:loading wire:target="save">Guardando…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
