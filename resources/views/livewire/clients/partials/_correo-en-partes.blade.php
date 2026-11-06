{{-- Correo en dos partes, como en Nuevo cliente (Trello, tarjeta 4, 05/11): el usuario
     y el dominio elegido (@GMAIL.COM, @HOTMAIL.COM u otro escrito a mano). $prop es el
     arreglo del componente con email_usuario, email_dominio y email_dominio_otro
     (App\Support\CorreoEnPartes arma el email entero al guardar). --}}
<div class="{{ $col ?? 'col-12 col-md-4' }}">
    <label class="form-label mb-0 small fw-semibold">Correo *</label>
    <div class="input-group input-group-sm">
        <input type="text" class="form-control campo-obligatorio @error($prop.'.email_usuario') is-invalid @enderror @error($prop.'.email') is-invalid @enderror"
               wire:model="{{ $prop }}.email_usuario" autocomplete="off" placeholder="usuario" maxlength="100">
        <span class="input-group-text">@</span>
        <select class="form-select campo-obligatorio @error($prop.'.email_dominio') is-invalid @enderror" wire:model.live="{{ $prop }}.email_dominio" style="max-width: 150px;">
            <option value="">— dominio —</option>
            @foreach(\App\Livewire\Clients\Create::EMAIL_DOMINIOS as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
        @if(($datos['email_dominio'] ?? '') === 'otro')
            <input type="text" class="form-control campo-obligatorio @error($prop.'.email_dominio_otro') is-invalid @enderror"
                   wire:model="{{ $prop }}.email_dominio_otro" autocomplete="off" placeholder="dominio.com" maxlength="100">
        @endif
    </div>
    @foreach(['email_usuario', 'email_dominio', 'email_dominio_otro', 'email'] as $parte)
        @error($prop.'.'.$parte) <div class="text-danger small">{{ $message }}</div> @enderror
    @endforeach
</div>
