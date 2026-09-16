<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Route;

/**
 * Menú lateral: ÚNICA fuente de verdad de qué módulos existen, en qué orden y
 * qué permiso pide cada uno. Vivía dentro de sidebar.blade.php, pero el
 * aterrizaje tras iniciar sesión necesita lo mismo, y tenerlo dos veces se
 * desincroniza.
 *
 * `rutaInicio()` devuelve el primer módulo que el usuario SÍ puede abrir. Antes
 * el login mandaba a /dashboard y, sin ese permiso, el controlador desviaba a
 * /credits con la ruta escrita a mano: quien no tuviera créditos —el caso del
 * rol area-legal— rebotaba entre pantallas prohibidas (14/09).
 */
class MenuLateral
{
    /** Ruta de respaldo: no requiere permiso de módulo, solo estar autenticado. */
    public const RUTA_RESPALDO = 'sin-accesos';

    /**
     * Aterrizajes PREFERIDOS (permiso => ruta), en orden. Son decisiones de
     * negocio que el orden del menú no refleja: el analista de créditos
     * aterriza en su listado de créditos, no en la primera opción de Registro.
     * Si no cumple ninguna, se recorre el menú.
     */
    public const PREFERIDAS = [
        'dashboard' => 'dashboard.index',
        'creditos' => 'credits.index',
        'clientes' => 'clients.index',
    ];

    public static function items(): array
    {
        return [
            ['type' => 'title', 'title' => ''],

            [
                'id' => 'dashboard-simple',
                'title' => 'Panel De Control',
                'icon' => 'ti ti-home',
                'route' => 'dashboard.index',
                'can' => 'dashboard',
            ],

            [
                'id' => 'settings',
                'title' => 'Configuración',
                'icon' => 'ti ti-settings',
                'canAny' => ['configuracion.usuarios', 'configuracion.sucursales'],
                'children' => [
                    ['title' => 'Usuarios',     'route' => 'settings.users.index',          'can' => 'configuracion.usuarios'],
                    ['title' => 'Sucursales',   'route' => 'settings.headquarters.index',   'can' => 'configuracion.sucursales'],
                ],
            ],

            [
                'id' => 'registro',
                'title' => 'Registro',
                'icon' => 'ti ti-file-text',
                'canAny' => ['registro.activar', 'registro.estado', 'clientes', 'registro.cesados', 'configuracion.conceptos', 'registro.eliminar-masivo', 'pagos', 'creditos', 'configuracion.tipo-cambio'],
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
                'id' => 'caja',
                'title' => 'Caja',
                'icon' => 'ti ti-home-dollar',
                'canAny' => ['caja.apertura', 'caja.ingresos', 'caja.egresos'],
                'children' => [
                    ['title' => 'Apertura Caja',  'route' => 'cash.opening',  'can' => 'caja.apertura'],
                    ['title' => 'Ingreso',        'route' => 'cash.incomes',  'can' => 'caja.ingresos'],
                    ['title' => 'Egreso',         'route' => 'cash.expenses', 'can' => 'caja.egresos'],
                ],
            ],

            [
                'id' => 'reportes',
                'title' => 'Reportes',
                'icon' => 'ti ti-report-analytics',
                'canAny' => ['reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal', 'reportes.asesor', 'reportes.pagos', 'reportes.caja-estadistica', 'reportes.credito-estadistica', 'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3', 'reportes.cartera', 'reportes.morosidad', 'reportes.cancelados', 'reportes.simulador'],
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
                'id' => 'legal',
                'title' => 'Área Legal',
                'icon' => 'ti ti-gavel',
                'canAny' => ['legal.garantias', 'legal.notaria', 'legal.judicial', 'legal.papeletas', 'legal.caja', 'legal.configuracion'],
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
                'id' => 'auditoria',
                'title' => 'Auditoría',
                'icon' => 'ti ti-shield-lock',
                'route' => 'audit.index',
                'role' => 'director',
            ],
        ];
    }

    /**
     * Primer módulo accesible para el usuario, en el orden del menú. Devuelve
     * el NOMBRE de la ruta. Si no puede abrir ninguno, cae a su perfil: es
     * preferible a rebotar contra un 403.
     */
    public static function rutaInicio(?Authorizable $user): string
    {
        if (! $user) {
            return self::RUTA_RESPALDO;
        }

        foreach (self::PREFERIDAS as $permiso => $ruta) {
            if ($user->can($permiso) && Route::has($ruta)) {
                return $ruta;
            }
        }

        foreach (self::items() as $item) {
            foreach (self::rutasDe($item) as $ruta => $permiso) {
                if (self::puede($user, $item, $permiso) && Route::has($ruta)) {
                    return $ruta;
                }
            }
        }

        return self::RUTA_RESPALDO;
    }

    /** Rutas candidatas de un item (la propia y las de sus hijos), en orden. */
    private static function rutasDe(array $item): array
    {
        $rutas = [];
        if (! empty($item['route'])) {
            $rutas[$item['route']] = $item['can'] ?? null;
        }
        foreach ($item['children'] ?? [] as $hijo) {
            if (! empty($hijo['route'])) {
                $rutas[$hijo['route']] = $hijo['can'] ?? null;
            }
        }

        return $rutas;
    }

    private static function puede(Authorizable $user, array $item, ?string $permiso): bool
    {
        // Items por rol (Auditoría): el permiso fino no aplica.
        if (! empty($item['role'])) {
            return method_exists($user, 'hasRole') && $user->hasRole($item['role']);
        }

        return $permiso === null || $user->can($permiso);
    }
}
