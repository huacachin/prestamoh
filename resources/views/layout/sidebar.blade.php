{{-- resources/views/layout/sidebar.blade.php --}}

@php
    $sidebarItems = [
        [ 'type' => 'title', 'title' => '' ],

        [
            'id'    => 'dashboard',
            'title' => 'Panel de control',
            'icon'  => 'ti ti-home',
            'route' => 'dashboard.index',
            'can'   => 'dashboard',
        ],

        [
            'id'    => 'clients',
            'title' => 'Clientes',
            'icon'  => 'ti ti-users',
            'route' => 'clients.index',
            'can'   => 'clientes',
        ],

        [
            'id'       => 'credits',
            'title'    => 'Créditos',
            'icon'     => 'ti ti-credit-card',
            'route'    => 'credits.index',
            'can'      => 'creditos',
        ],

        [
            'id'    => 'payments',
            'title' => 'Pagos',
            'icon'  => 'ti ti-currency-dollar',
            'route' => 'payments.index',
            'can'   => 'pagos',
        ],

        [
            'id'       => 'caja',
            'title'    => 'Caja',
            'icon'     => 'ti ti-home-dollar',
            'canAny'   => ['caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.balance'],
            'children' => [
                ['title' => 'Apertura',  'route' => 'cash.opening',  'can' => 'caja.apertura'],
                ['title' => 'Ingresos',  'route' => 'cash.incomes',  'can' => 'caja.ingresos'],
                ['title' => 'Egresos',   'route' => 'cash.expenses', 'can' => 'caja.egresos'],
                ['title' => 'Balance',   'route' => 'cash.balance',  'can' => 'caja.balance'],
            ],
        ],

        [
            'id'       => 'reportes',
            'title'    => 'Reportes',
            'icon'     => 'ti ti-report-analytics',
            'canAny'   => ['reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja'],
            'children' => [
                ['title' => 'Cartera Activa',  'route' => 'reports.portfolio',  'can' => 'reportes.cartera'],
                ['title' => 'Pagos',           'route' => 'reports.payments',   'can' => 'reportes.pagos'],
                ['title' => 'Morosidad',       'route' => 'reports.delinquent', 'can' => 'reportes.morosidad'],
                ['title' => 'Caja',            'route' => 'reports.cash',       'can' => 'reportes.caja'],
            ],
        ],

        [
            'id'       => 'settings',
            'title'    => 'Configuración',
            'icon'     => 'ti ti-settings',
            'canAny'   => ['configuracion.usuarios', 'configuracion.sucursales', 'configuracion.conceptos'],
            'children' => [
                ['title' => 'Usuarios',     'route' => 'settings.users.index',         'can' => 'configuracion.usuarios'],
                ['title' => 'Sucursales',   'route' => 'settings.headquarters.index',  'can' => 'configuracion.sucursales'],
                ['title' => 'Conceptos',    'route' => 'settings.concepts.index',      'can' => 'configuracion.conceptos'],
                ['title' => 'Tipo Cambio',  'route' => 'settings.exchange-rates.index', 'can' => 'configuracion.tipo-cambio'],
            ],
        ],
    ];
@endphp

{{-- Render sidebar items --}}
@foreach($sidebarItems as $item)
    @if(isset($item['type']) && $item['type'] === 'title')
        <li class="sidebar-title">{{ $item['title'] }}</li>
    @elseif(isset($item['children']))
        @php
            $canSee = false;
            if (isset($item['canAny'])) {
                $canSee = auth()->user()?->canAny($item['canAny']);
            }
        @endphp
        @if($canSee)
            <li class="sidebar-item has-sub {{ request()->routeIs(collect($item['children'])->pluck('route')->map(fn($r) => $r.'*')->toArray()) ? 'active open' : '' }}">
                <a href="javascript:void(0)" class="sidebar-link">
                    <i class="{{ $item['icon'] }} f-s-16"></i>
                    <span>{{ $item['title'] }}</span>
                </a>
                <ul class="sidebar-sub-item">
                    @foreach($item['children'] as $child)
                        @can($child['can'])
                            <li class="{{ request()->routeIs($child['route'].'*') ? 'active' : '' }}">
                                <a href="{{ route($child['route']) }}">{{ $child['title'] }}</a>
                            </li>
                        @endcan
                    @endforeach
                </ul>
            </li>
        @endif
    @else
        @can($item['can'] ?? '')
            <li class="sidebar-item {{ request()->routeIs(($item['route'] ?? '').'*') ? 'active' : '' }}">
                <a href="{{ route($item['route']) }}" class="sidebar-link">
                    <i class="{{ $item['icon'] }} f-s-16"></i>
                    <span>{{ $item['title'] }}</span>
                </a>
            </li>
        @endcan
    @endif
@endforeach
