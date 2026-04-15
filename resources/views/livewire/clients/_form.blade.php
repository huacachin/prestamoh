@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa los siguientes errores:</strong>
        <ul class="mb-0 mt-2 ps-3">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    {{-- Datos Personales --}}
    <div class="col-12"><div class="app-divider-v">DATOS PERSONALES</div></div>

    <div class="col-12 col-md-4">
        <label class="form-label">Nombres (*)</label>
        <input type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror"
               placeholder="Nombres" wire:model.defer="nombre">
        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Apellido Paterno (*)</label>
        <input type="text" class="form-control form-control-sm @error('apellido_pat') is-invalid @enderror"
               placeholder="Apellido Paterno" wire:model.defer="apellido_pat">
        @error('apellido_pat') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Apellido Materno</label>
        <input type="text" class="form-control form-control-sm" placeholder="Apellido Materno" wire:model.defer="apellido_mat">
    </div>

    <div class="col-auto">
        <label class="form-label">Tipo Doc.</label>
        <select class="form-select form-select-sm" wire:model.defer="tipo_documento">
            <option value="DNI">DNI</option>
            <option value="RUC">RUC</option>
            <option value="CE">CE</option>
        </select>
    </div>

    <div class="col-auto">
        <label class="form-label">N° Documento (*)</label>
        <input type="text" class="form-control form-control-sm @error('documento') is-invalid @enderror"
               placeholder="Número" wire:model.defer="documento">
        @error('documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-auto">
        <label class="form-label">Fecha Nac.</label>
        <input type="date" class="form-control form-control-sm" wire:model.defer="fecha_nacimiento">
    </div>

    <div class="col-auto">
        <label class="form-label">Sexo</label>
        <select class="form-select form-select-sm" wire:model.defer="sexo">
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
        </select>
    </div>

    <div class="col-auto">
        <label class="form-label">Email</label>
        <input type="email" class="form-control form-control-sm" placeholder="email@ejemplo.com" wire:model.defer="email">
    </div>

    {{-- Contacto --}}
    <div class="col-12"><div class="app-divider-v">CONTACTO</div></div>

    <div class="col-auto">
        <label class="form-label">Teléfono Fijo</label>
        <input type="text" class="form-control form-control-sm" placeholder="Fijo" wire:model.defer="telefono_fijo">
    </div>

    <div class="col-auto">
        <label class="form-label">Celular 1</label>
        <input type="text" class="form-control form-control-sm" placeholder="Celular" wire:model.defer="celular1">
    </div>

    <div class="col-auto">
        <label class="form-label">Celular 2</label>
        <input type="text" class="form-control form-control-sm" placeholder="Celular 2" wire:model.defer="celular2">
    </div>

    {{-- Ubicación --}}
    <div class="col-12"><div class="app-divider-v">UBICACIÓN</div></div>

    <div class="col-12 col-md-4">
        <label class="form-label">Dirección</label>
        <input type="text" class="form-control form-control-sm" placeholder="Dirección" wire:model.defer="direccion">
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Referencia</label>
        <input type="text" class="form-control form-control-sm" placeholder="Referencia" wire:model.defer="referencia">
    </div>

    <div class="col-auto">
        <label class="form-label">Distrito</label>
        <input type="text" class="form-control form-control-sm" placeholder="Distrito" wire:model.defer="distrito">
    </div>

    <div class="col-auto">
        <label class="form-label">Provincia</label>
        <input type="text" class="form-control form-control-sm" placeholder="Provincia" wire:model.defer="provincia">
    </div>

    <div class="col-auto">
        <label class="form-label">Departamento</label>
        <input type="text" class="form-control form-control-sm" placeholder="Departamento" wire:model.defer="departamento">
    </div>

    <div class="col-auto">
        <label class="form-label">Zona</label>
        <input type="text" class="form-control form-control-sm" placeholder="Zona" wire:model.defer="zona">
    </div>

    {{-- Emergencia --}}
    <div class="col-12"><div class="app-divider-v">CONTACTO DE EMERGENCIA</div></div>

    <div class="col-12 col-md-3">
        <label class="form-label">Nombre Contacto</label>
        <input type="text" class="form-control form-control-sm" placeholder="Nombre" wire:model.defer="contacto_emergencia">
    </div>

    <div class="col-auto">
        <label class="form-label">Teléfono Contacto</label>
        <input type="text" class="form-control form-control-sm" placeholder="Teléfono" wire:model.defer="telefono_contacto">
    </div>

    {{-- Datos Bancarios --}}
    <div class="col-12"><div class="app-divider-v">DATOS BANCARIOS</div></div>

    <div class="col-auto">
        <label class="form-label">Banco Haberes</label>
        <input type="text" class="form-control form-control-sm" placeholder="Banco" wire:model.defer="banco_haberes">
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Cuenta Haberes</label>
        <input type="text" class="form-control form-control-sm" placeholder="N° Cuenta" wire:model.defer="cuenta_haberes">
    </div>

    <div class="col-auto">
        <label class="form-label">Banco CTS</label>
        <input type="text" class="form-control form-control-sm" placeholder="Banco CTS" wire:model.defer="banco_cts">
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Cuenta CTS</label>
        <input type="text" class="form-control form-control-sm" placeholder="N° Cuenta CTS" wire:model.defer="cuenta_cts">
    </div>

    <div class="col-auto">
        <label class="form-label">AFP</label>
        <input type="text" class="form-control form-control-sm" placeholder="AFP" wire:model.defer="afp">
    </div>

    <div class="col-auto">
        <label class="form-label">CUSSP</label>
        <input type="text" class="form-control form-control-sm" placeholder="CUSSP" wire:model.defer="cussp">
    </div>

    {{-- Detalles --}}
    <div class="col-12"><div class="app-divider-v">DETALLES Y ASIGNACIÓN</div></div>

    <div class="col-auto">
        <label class="form-label">Asesor</label>
        <select class="form-select form-select-sm" wire:model.defer="asesor_id">
            <option value="">— Seleccionar —</option>
            @foreach($asesores as $a)
                <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-auto">
        <label class="form-label">Sucursal</label>
        <select class="form-select form-select-sm" wire:model.defer="headquarter_id">
            <option value="">— Seleccionar —</option>
            @foreach($headquarters as $h)
                <option value="{{ $h->id }}">{{ $h->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control form-control-sm" rows="3" placeholder="Observaciones" wire:model.defer="observaciones"></textarea>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Foto</label>
        <input type="file" class="form-control form-control-sm" accept="image/*" wire:model="imagen">
        @error('imagen') <span class="title-modules small">{{ $message }}</span> @enderror
        <div class="mt-2">
            @if($imagen)
                <img src="{{ $imagen->temporaryUrl() }}" alt="Preview" style="max-height: 120px; border-radius: 8px;">
            @elseif(!empty($imagen_actual))
                <img src="{{ asset('storage/' . $imagen_actual) }}" alt="Foto actual" style="max-height: 120px; border-radius: 8px;">
            @endif
        </div>
    </div>
</div>
