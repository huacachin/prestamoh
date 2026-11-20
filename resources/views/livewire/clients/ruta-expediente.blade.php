<div>
    @if($ruta !== null)
        @include('livewire.clients.partials._ruta-expediente', ['ruta' => $ruta, 'tab' => $tab, 'navegable' => true, 'prestamos' => $prestamos])
    @endif
</div>
