<!-- Header Section starts -->
<header class="header-main">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 d-flex align-items-center header-left">
                                <span class="header-toggle me-3">
                                  <i class="ti ti-menu"></i>
                                </span>
                            </div>

                            <div class="col-6 d-flex align-items-center justify-content-end header-right">
                                <ul class="d-flex align-items-center">
                                    <li class="header-dark head-icon">
                                        <div class="sun-logo">
                                            <i class="ti ti-moon-off"></i>
                                        </div>
                                        <div class="moon-logo">
                                            <i class="ti ti-moon-filled"></i>
                                        </div>
                                    </li>

                                    <li class="header-notification">
                                        <div class="flex-shrink-0 app-dropdown">
                                            <a href="#" class="d-block head-icon position-relative"
                                               data-bs-toggle="dropdown"
                                               data-bs-auto-close="outside" aria-expanded="false">
                                                <i class="ti ti-bell"></i>
                                            </a>

                                            <div class="dropdown-menu dropdown-menu-end bg-transparent border-0">
                                                <div class="card">
                                                    <div class="card-header bg-primary">
                                                        <h5 class="text-white">
                                                            Notificaciones
                                                            <span class="float-end">
                                                                <i class="ti ti-bell text-white"></i>
                                                            </span>
                                                        </h5>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <div class="hidden-massage py-4 px-3 text-center">
                                                            <div>
                                                                <h6 class="mb-0">Sin notificaciones</h6>
                                                                <p class="text-secondary">No hay alertas pendientes.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

                                    <li class="header-profile">
                                        <div class="flex-shrink-0 dropdown">
                                            <a href="#" class="d-block head-icon pe-0" data-bs-toggle="dropdown"
                                               aria-expanded="false">
                                                <span class="rounded-circle h-35 w-35 d-flex-center bg-primary text-white">
                                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                                </span>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end header-card border-0 px-2">
                                                <li class="dropdown-item d-flex align-items-center p-2">
                                                    <span class="h-35 w-35 d-flex-center b-r-50 bg-primary text-white">
                                                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                                    </span>
                                                    <div class="flex-grow-1 ps-2">
                                                        <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                                                        <p class="f-s-12 mb-0 text-secondary">{{ auth()->user()->roles->first()?->name ?? '—' }}</p>
                                                    </div>
                                                </li>

                                                <li class="app-divider-v dotted py-1"></li>
                                                <li class="btn-light-danger b-r-5">
                                                    <livewire:auth.logout />
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- Header Section ends -->
