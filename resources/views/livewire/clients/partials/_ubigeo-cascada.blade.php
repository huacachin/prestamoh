{{-- Departamento → Provincia → Distrito con el catálogo de Nuevo cliente (Trello, tarjeta 4,
     05/11). select2 con búsqueda y texto libre (historial y casos borde); el wire:key lleva
     la cascada para que el morph cambie el nodo entero y select2 se reinicie limpio. La
     cascada la hace el componente con App\Support\Ubigeo::encadenar(). $prop es el
     arreglo del componente; $auto, los campos que llenó la API (van en rojo); $conBuscador = false
     da selects simples (dentro de un modal de Bootstrap el buscador de select2 no recibe el
     foco) $amarillo, la clase del amarillo de la pantalla, y $obligatorio = false quita el asterisco. --}}
@php
    $dep = (string) ($datos['departamento'] ?? '');
    $prov = (string) ($datos['provincia'] ?? '');
    $asterisco = ($obligatorio ?? true) ? ' *' : '';
    $clases = 'form-select form-select-sm '.(($conBuscador ?? true) ? 'select2-tags ' : '').($amarillo ?? 'campo-obligatorio');
    $marcas = fn (string $campo) => ($errors->has($prop.'.'.$campo) ? 'is-invalid ' : '').(in_array($campo, $auto ?? [], true) ? 'campo-api' : '');
@endphp
<div class="{{ $col ?? 'col-6 col-md-3' }}" wire:key="{{ $prop }}-ubigeo-dep">
    <label class="form-label mb-0 small fw-semibold">Departamento{{ $asterisco }}</label>
    <select class="{{ $clases }} {{ $marcas('departamento') }}" wire:model.live="{{ $prop }}.departamento">
        @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::departamentos(), $dep) as $op)
            <option value="{{ $op }}">{{ $op }}</option>
        @endforeach
    </select>
    @error($prop.'.departamento') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
<div class="{{ $col ?? 'col-6 col-md-3' }}" wire:key="{{ $prop }}-ubigeo-prov-{{ md5($dep) }}">
    <label class="form-label mb-0 small fw-semibold">Provincia{{ $asterisco }}</label>
    <select class="{{ $clases }} {{ $marcas('provincia') }}" wire:model.live="{{ $prop }}.provincia">
        @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::provinciasDe($dep), $prov) as $op)
            <option value="{{ $op }}">{{ $op }}</option>
        @endforeach
    </select>
    @error($prop.'.provincia') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
<div class="{{ $col ?? 'col-6 col-md-3' }}" wire:key="{{ $prop }}-ubigeo-dist-{{ md5($dep.'|'.$prov) }}">
    <label class="form-label mb-0 small fw-semibold">Distrito{{ $asterisco }}</label>
    <select class="{{ $clases }} {{ $marcas('distrito') }}" wire:model="{{ $prop }}.distrito" data-placeholder="Busca o escribe...">
        <option value="">{{ ($conBuscador ?? true) ? '' : '— Distrito —' }}</option>
        @foreach(\App\Support\Ubigeo::conHistorico(\App\Support\Ubigeo::distritosDe($dep, $prov), $datos['distrito'] ?? '') as $op)
            <option value="{{ $op }}">{{ $op }}</option>
        @endforeach
    </select>
    @error($prop.'.distrito') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
