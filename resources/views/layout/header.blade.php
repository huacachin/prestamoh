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

                                {{-- Trigger visual de la paleta de comandos (cmd+k) --}}
                                <button type="button"
                                        class="cmdk-trigger d-none d-md-inline-flex"
                                        title="Buscar (Cmd+K)"
                                        onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'k', metaKey: true}))">
                                    <i class="ti ti-search"></i>
                                    <span>Buscar</span>
                                    <kbd>⌘K</kbd>
                                </button>
                            </div>

                            <style>
                                .cmdk-trigger {
                                    display: inline-flex; align-items: center; gap: 8px;
                                    background: rgba(255,255,255,0.12);
                                    border: 1px solid rgba(255,255,255,0.18);
                                    color: rgba(255,255,255,0.9);
                                    border-radius: 8px;
                                    padding: 5px 10px;
                                    font-size: 12px;
                                    cursor: pointer;
                                    transition: background .15s ease;
                                    min-width: 180px;
                                }
                                .cmdk-trigger:hover { background: rgba(255,255,255,0.2); }
                                .cmdk-trigger i { font-size: 14px; opacity: .8; }
                                .cmdk-trigger span { flex: 1; text-align: left; opacity: .85; }
                                .cmdk-trigger kbd {
                                    background: rgba(0,0,0,0.2);
                                    color: #fff;
                                    border-radius: 3px;
                                    padding: 1px 6px;
                                    font-size: 10px;
                                    font-family: 'Poppins', sans-serif;
                                    border: 0;
                                }
                            </style>

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

                                    {{-- Campana de compromisos de pago (cobranza) --}}
                                    <livewire:layout.compromisos-bell />

                                    <li class="header-profile">
                                        <div class="flex-shrink-0 dropdown">
                                            <a href="#" class="d-block head-icon pe-0" data-bs-toggle="dropdown"
                                               aria-expanded="false">
                                                @php
                                                    $avatarName = auth()->user()->name ?? 'Usuario';
                                                    $avatarUrl = 'https://ui-avatars.com/api/?name='
                                                        . urlencode($avatarName)
                                                        . '&size=128&background=0D8ABC&color=fff';
                                                @endphp
                                                <img src="{{ $avatarUrl }}"
                                                     alt="{{ $avatarName }}"
                                                     class="rounded-circle"
                                                     width="35" height="35"
                                                     style="object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,.15);">
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end header-card border-0 px-2">
                                                <li class="dropdown-item d-flex align-items-center p-2">
                                                    <img src="{{ $avatarUrl }}"
                                                         alt="{{ $avatarName }}"
                                                         class="rounded-circle"
                                                         width="35" height="35"
                                                         style="object-fit: cover;"
                                                         title="{{ auth()->user()->name }}">
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
