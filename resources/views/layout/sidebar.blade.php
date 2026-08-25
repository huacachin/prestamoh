{{-- resources/views/layout/sidebar.blade.php --}}

@php
    $sidebarItems = [
        [ 'type' => 'title', 'title' => '' ],

        [
            'id'    => 'dashboard-simple',
            'title' => 'Panel De Control',
            'icon'  => 'ti ti-home',
            'route' => 'dashboard.index',
            'can'   => 'dashboard',
        ],

        [
            'id'       => 'settings',
            'title'    => 'Configuración',
            'icon'     => 'ti ti-settings',
            'canAny'   => ['configuracion.usuarios', 'configuracion.sucursales'],
            'children' => [
                ['title' => 'Usuarios',     'route' => 'settings.users.index',          'can' => 'configuracion.usuarios'],
                ['title' => 'Sucursales',   'route' => 'settings.headquarters.index',   'can' => 'configuracion.sucursales'],
            ],
        ],

        [
            'id'       => 'registro',
            'title'    => 'Registro',
            'icon'     => 'ti ti-file-text',
            'canAny'   => ['registro.activar', 'registro.estado', 'clientes', 'registro.cesados', 'configuracion.conceptos', 'registro.eliminar-masivo', 'pagos', 'creditos', 'configuracion.tipo-cambio'],
            'children' => [
                ['title' => 'Activar Prestamos',  'route' => 'credits.activate',              'can' => 'registro.activar'],
                ['title' => 'Cambiar Estado',      'route' => 'credits.change-status',          'can' => 'registro.estado'],
                ['title' => 'Cliente',             'route' => 'clients.index',                  'can' => 'clientes'],
                ['title' => 'Cliente Cesados',     'route' => 'clients.ceased',                 'can' => 'registro.cesados'],
                ['title' => 'Conceptos Fijos',     'route' => 'settings.concepts.index',        'can' => 'configuracion.conceptos'],
                ['title' => 'Eliminar Masivo',     'route' => 'credits.mass-delete',            'can' => 'registro.eliminar-masivo'],
                ['title' => 'Pagos/Credito',       'route' => 'payments.index',                 'can' => 'pagos'],
                ['title' => 'Prestamo',            'route' => 'credits.index',                  'can' => 'creditos'],
                ['title' => 'Tipo de Cambio',      'route' => 'settings.exchange-rates.index',  'can' => 'configuracion.tipo-cambio'],
            ],
        ],

        [
            'id'       => 'caja',
            'title'    => 'Caja',
            'icon'     => 'ti ti-home-dollar',
            'canAny'   => ['caja.apertura', 'caja.ingresos', 'caja.egresos'],
            'children' => [
                ['title' => 'Apertura Caja',  'route' => 'cash.opening',  'can' => 'caja.apertura'],
                ['title' => 'Ingreso',        'route' => 'cash.incomes',  'can' => 'caja.ingresos'],
                ['title' => 'Egreso',         'route' => 'cash.expenses', 'can' => 'caja.egresos'],
            ],
        ],

        [
            'id'       => 'reportes',
            'title'    => 'Reportes',
            'icon'     => 'ti ti-report-analytics',
            'canAny'   => ['reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal', 'reportes.asesor', 'reportes.pagos', 'reportes.caja-estadistica', 'reportes.credito-estadistica', 'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3', 'reportes.cartera', 'reportes.morosidad', 'reportes.cancelados', 'reportes.simulador'],
            'children' => [
                ['title' => 'Reporte Credito D.',      'route' => 'payments.daily',              'can' => 'reportes.credito-diario'],
                ['title' => 'Reporte Credito M.',      'route' => 'payments.monthly',            'can' => 'reportes.credito-mensual'],
                ['title' => 'Reporte Credito S.',      'route' => 'payments.weekly',              'can' => 'reportes.credito-semanal'],
                ['title' => 'Reporte de Asesor',       'route' => 'reports.advisor',              'can' => 'reportes.asesor'],
                ['title' => 'Reporte de Pago',         'route' => 'reports.payments',             'can' => 'reportes.pagos'],
                ['title' => 'Rep. Estad. Caja M.A.',   'route' => 'reports.cash-statistics',      'can' => 'reportes.caja-estadistica'],
                ['title' => 'Rep. Estad. Crédito',     'route' => 'reports.credit-statistics',    'can' => 'reportes.credito-estadistica'],
                ['title' => 'Rep. General Caja 1',     'route' => 'reports.cash-general-1',       'can' => 'reportes.caja-general-1'],
                ['title' => 'Rep. General Caja 2',     'route' => 'reports.cash-general-2',       'can' => 'reportes.caja-general-2'],
                ['title' => 'Rep. General Caja 3',     'route' => 'reports.cash-general-3',       'can' => 'reportes.caja-general-3'],
                ['title' => 'Resumen de Créditos',     'route' => 'reports.portfolio',            'can' => 'reportes.cartera'],
                ['title' => 'Pendientes x Cobrar',     'route' => 'reports.delinquent',           'can' => 'reportes.morosidad'],
                ['title' => 'Resumen de Cancelados',   'route' => 'reports.cancelled',            'can' => 'reportes.cancelados'],
                ['title' => 'Simulacro de Crédito',    'route' => 'reports.simulator',            'can' => 'reportes.simulador'],
            ],
        ],

        [
            'id'       => 'legal',
            'title'    => 'Área Legal',
            'icon'     => 'ti ti-gavel',
            'canAny'   => ['legal.garantias', 'legal.notaria', 'legal.judicial', 'legal.papeletas', 'legal.caja', 'legal.configuracion'],
            'children' => [
                ['title' => 'Garantías SIGM',  'route' => 'legal.garantias.index',   'can' => 'legal.garantias'],
                ['title' => 'Vehículos',       'route' => 'legal.vehiculos',         'can' => 'legal.garantias'],
                ['title' => 'Notaría',         'route' => 'legal.notaria',           'can' => 'legal.notaria'],
                ['title' => 'Expedientes Jud.', 'route' => 'legal.expedientes.index', 'can' => 'legal.judicial'],
                ['title' => 'Papeletas',       'route' => 'legal.papeletas',         'can' => 'legal.papeletas'],
                ['title' => 'Caja Legal',      'route' => 'legal.caja',              'can' => 'legal.caja'],
                ['title' => 'Config. Legal',   'route' => 'legal.settings',          'can' => 'legal.configuracion'],
            ],
        ],

        [
            'id'    => 'auditoria',
            'title' => 'Auditoría',
            'icon'  => 'ti ti-shield-lock',
            'route' => 'audit.index',
            'role'  => 'director',
        ],
    ];
@endphp

<nav id="sidebar-nav" class="dark-sidebar">
    <div class="app-logo">
        <a class="logo d-inline-block" href="{{ route('dashboard.index') }}">
            <img width="1000px" src="{{ asset('assets/images/logo/logo1.png') }}" alt="#" class="dark-logo">
        </a>
        <span class="bg-light-light toggle-semi-nav">
            <i class="ti ti-chevrons-right f-s-20"></i>
        </span>
    </div>

    <div class="app-nav" id="app-simple-bar">
        @if(!empty($sidebarItems))
            @include('partials.sidebar-menu', ['items' => $sidebarItems])
        @endif
    </div>

    <div class="menu-navs">
        <span class="menu-previous"><i class="ti ti-chevron-left"></i></span>
        <span class="menu-next"><i class="ti ti-chevron-right"></i></span>
    </div>
</nav>
