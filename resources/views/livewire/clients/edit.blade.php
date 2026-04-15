<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">CLIENTES : ACTUALIZAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Editar</span></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('livewire.clients._form')

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" wire:click="update">Guardar cambios</button>
                <button class="btn btn-sm btn-danger" wire:click="questionDelete({{ $clientId }})">Eliminar</button>
                <a href="{{ route('clients.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    </div>
</div>
