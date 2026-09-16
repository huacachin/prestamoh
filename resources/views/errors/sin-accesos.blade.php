@extends('layout.master')
@section('title', 'Sin accesos')
@section('main-content')
    {{-- Un usuario sin NINGÚN módulo asignado no debe rebotar contra un 403 ni
         quedar en un bucle de redirecciones: se le dice qué pasa y a quién
         acudir (14/09, caso del usuario Marvin con rol area-legal). --}}
    <div class="container-fluid">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-lock f-s-40 text-muted"></i>
                        <h4 class="main-title title-modules mt-3">Sin módulos asignados</h4>
                        <p class="mb-1">
                            Tu usuario <strong>{{ auth()->user()?->username }}</strong> no tiene ningún
                            módulo habilitado, así que no hay ninguna pantalla que mostrarte.
                        </p>
                        <p class="text-muted small mb-4">
                            Pídele al administrador que revise tus permisos en
                            Configuración → Usuarios → Permisos.
                        </p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-sm btn-dark"><i class="ti ti-logout"></i> Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
