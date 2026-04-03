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
                                    <li class="header-search">
                                        <a href="#" class="d-block head-icon" role=button data-bs-toggle="offcanvas"
                                           data-bs-target="#offcanvasTop" aria-controls="offcanvasTop">
                                            <i class="ti ti-search"></i>
                                        </a>

                                        <div class="offcanvas offcanvas-top search-canvas" tabindex="-1"
                                             id="offcanvasTop">
                                            <div class="offcanvas-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <form class="me-3 app-form app-icon-form " action="#">
                                                            <div class="position-relative">
                                                                <input type="search" class="form-control"
                                                                       placeholder="Search..."
                                                                       aria-label="Search">
                                                                <i class="ti ti-search f-s-15"></i>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                                                            aria-label="Close"></button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

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

                                                {{-- Punto animado solo si hay alertas --}}
                                                @if(($vehicleExpCount ?? 0) > 0)
                                                    <span
                                                        class="position-absolute translate-middle p-1 bg-danger border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__slower"></span>
                                                @endif

                                                {{-- Contador (opcional). Descomenta si quieres ver el número --}}
                                                {{-- <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                    {{ $vehicleExpCount ?? 0 }}
                                                  </span> --}}
                                            </a>

                                            <div class="dropdown-menu dropdown-menu-end bg-transparent border-0">
                                                <div class="card">
                                                    <div class="card-header bg-primary">
                                                        <h5 class="text-white">
                                                            Vencimientos próximos
                                                            <span class="float-end">
              <i class="ti ti-bell text-white"></i>
            </span>
                                                        </h5>
                                                    </div>

                                                    <div class="card-body p-0">
                                                        <div class="head-container app-scroll">
                                                            @forelse(($vehicleExpAlerts ?? []) as $n)
                                                                <div class="head-box">
                <span class="text-light-{{ $n['color'] }} h-40 w-40 d-flex-center b-r-50">
                  {{-- SD/RT/CD con color rojo/amarillo --}}
                  <span class="badge bg-{{ $n['color'] }} text-white">{{ $n['abbr'] }}</span>
                </span>

                                                                    <div class="flex-grow-1 ps-2">
                                                                        <h6 class="mb-0">
                                                                            {{ $n['plate'] }}
                                                                            <span class="badge bg-light text-dark ms-1">{{ $n['days'] }} día(s)</span>
                                                                        </h6>
                                                                        <p class="text-secondary f-s-13 mb-0">
                                                                            {{ $n['label'] }} vence en {{ $n['days'] }}
                                                                            día(s)
                                                                            <span class="text-muted">({{ \Carbon\Carbon::parse($n['due_date'])->format('d/m/Y') }})</span>
                                                                        </p>
                                                                    </div>

                                                                    <div class="text-end">
                                                                        {{-- Si quieres un botón que lleve al listado filtrado por placa: --}}
                                                                        <a href="{{ route('settings.vehicles.index') }}?filter=plate&search={{ urlencode($n['plate']) }}"
                                                                           class="f-s-12 text-muted text-decoration-underline">ver</a>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="hidden-massage py-4 px-3 text-center">
                                                                    <img src="{{asset('assets/images/icons/bell.png')}}"
                                                                         class="w-50 h-50 mb-3 mt-2" alt="">
                                                                    <div>
                                                                        <h6 class="mb-0">Sin notificaciones</h6>
                                                                        <p class="text-secondary">No hay vencimientos
                                                                            próximos (≤ 10 días).</p>
                                                                    </div>
                                                                </div>
                                                            @endforelse
                                                        </div>
                                                    </div>

                                                    <div class="card-footer">
                                                        <a href="{{ route('settings.vehicles.index') }}"
                                                           class="btn btn-primary w-100">
                                                            <i class="ti ti-plus"></i> Ver todo
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

                                    <li class="header-profile">
                                        <div class="flex-shrink-0 dropdown">
                                            <a href="#" class="d-block head-icon pe-0" data-bs-toggle="dropdown"
                                               aria-expanded="false">
                                                <img src="{{auth()->user()->avatar_url}}" alt="mdo"
                                                     class="rounded-circle h-35 w-35">
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end header-card border-0 px-2">
                                                <li class="dropdown-item d-flex align-items-center p-2">
                                  <span class="h-35 w-35 d-flex-center b-r-50 position-relative">
                                    <img src="{{auth()->user()->avatar_url}}" alt=""
                                         class="img-fluid b-r-50">
                                    <span
                                        class="position-absolute top-0 end-0 p-1 bg-success border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__fast"></span>
                                  </span>
                                                    <div class="flex-grow-1 ps-2">
                                                        <h6 class="mb-0"> {{auth()->user()->name}}</h6>
                                                        <p class="f-s-12 mb-0 text-secondary">{{auth()->user()->roles->first()->name}}</p>
                                                    </div>
                                                </li>

                                                <li class="app-divider-v dotted py-1"></li>
                                                <!--li>
                                                    <a class="dropdown-item" href="{{route('logout')}}">
                                                        <i class="ti ti-user-circle pe-1 f-s-18"></i> Profile Detaiils
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="ti ti-notification pe-1 f-s-18"></i> Notification
                                                    </a>
                                                </li>

                                                <li class="app-divider-v dotted py-1"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="ti ti-help pe-1 f-s-18"></i> Help
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{('faq')}}">
                                                        <i class="ti ti-file-dollar pe-1 f-s-18"></i> FAQ
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{route('dashboard.index')}}">
                                                        <i class="ti ti-currency-dollar pe-1 f-s-18"></i> Pricing
                                                    </a>
                                                </li>
                                                <li class="app-divider-v dotted py-1"></li-->
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
