<?php

namespace Database\Seeders;

use App\Models\Permission as Perm;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $items = [
            // ─── Acceso a páginas (singles) ───
            ['name' => 'dashboard',  'label' => 'Dashboard',  'module' => 'dashboard',  'module_label' => 'Dashboard',  'description' => 'Acceso al Dashboard'],
            ['name' => 'clientes',   'label' => 'Clientes',   'module' => 'clientes',   'module_label' => 'Clientes',   'description' => 'Acceso a Clientes'],
            ['name' => 'creditos',   'label' => 'Créditos',   'module' => 'creditos',   'module_label' => 'Créditos',   'description' => 'Acceso a Créditos'],
            ['name' => 'pagos',      'label' => 'Pagos',      'module' => 'pagos',      'module_label' => 'Pagos',      'description' => 'Acceso a Pagos'],

            // ─── Caja ───
            ['name' => 'caja.apertura', 'label' => 'Apertura',  'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Apertura de Caja'],
            ['name' => 'caja.ingresos', 'label' => 'Ingresos',  'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Ingresos'],
            ['name' => 'caja.egresos',  'label' => 'Egresos',   'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Egresos'],

            // ─── Reportes ───
            ['name' => 'reportes.credito-diario',      'label' => 'Reporte Credito D.',     'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte de créditos diarios'],
            ['name' => 'reportes.credito-mensual',     'label' => 'Reporte Credito M.',     'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte de créditos mensuales'],
            ['name' => 'reportes.credito-semanal',     'label' => 'Reporte Credito S.',     'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte de créditos semanales'],
            ['name' => 'reportes.cartera',             'label' => 'Cartera Activa',         'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Cartera'],
            ['name' => 'reportes.pagos',               'label' => 'Pagos',                  'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Pagos'],
            ['name' => 'reportes.morosidad',           'label' => 'Morosidad',              'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Morosidad'],
            ['name' => 'reportes.caja',                'label' => 'Caja',                   'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Caja'],
            ['name' => 'reportes.asesor',              'label' => 'Reporte de Asesor',      'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte diario por asesor'],
            ['name' => 'reportes.caja-estadistica',    'label' => 'Estad. Caja M.A.',       'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte estadístico de caja mensual/anual'],
            ['name' => 'reportes.credito-estadistica', 'label' => 'Estad. Crédito',         'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte estadístico de créditos'],
            ['name' => 'reportes.caja-general-1',      'label' => 'Rep. General Caja 1',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 1'],
            ['name' => 'reportes.caja-general-2',      'label' => 'Rep. General Caja 2',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 2'],
            ['name' => 'reportes.caja-general-3',      'label' => 'Rep. General Caja 3',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 3'],
            ['name' => 'reportes.cancelados',          'label' => 'Resumen Cancelados',     'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Resumen de créditos cancelados'],
            ['name' => 'reportes.simulador',           'label' => 'Simulador',              'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Simulador de crédito'],

            // ─── Configuración ───
            ['name' => 'configuracion.usuarios',     'label' => 'Usuarios',     'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Usuarios'],
            ['name' => 'configuracion.sucursales',   'label' => 'Sucursales',   'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Sucursales'],
            ['name' => 'configuracion.conceptos',    'label' => 'Conceptos',    'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Conceptos'],
            ['name' => 'configuracion.tipo-cambio',  'label' => 'Tipo Cambio',  'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Tipo de Cambio'],

            // ─── Registro ───
            ['name' => 'registro.activar',                  'label' => 'Activar Prestamos', 'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Activar préstamos cancelados/refinanciados'],
            ['name' => 'registro.estado',                   'label' => 'Cambiar Estado',    'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Cambiar estado/situación de créditos'],
            ['name' => 'registro.cesados',                  'label' => 'Clientes Cesados',  'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Ver clientes cesados/inactivos'],
            ['name' => 'registro.eliminar-masivo',          'label' => 'Eliminar Masivo',   'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Eliminación masiva de registros'],
            ['name' => 'registro.eliminar-masivo.revertir', 'label' => 'Revertir Masivo',   'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Revertir una eliminación masiva ejecutada'],

            // ─── Permisos finos sobre acciones de Caja ───
            ['name' => 'caja.bypass-fecha-anterior', 'label' => 'Bypass fecha anterior', 'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Permite registrar/editar movimientos con fecha de mes anterior'],
            ['name' => 'caja.editar-historico',      'label' => 'Editar histórico',      'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Editar ingresos/egresos ya creados (no del día)'],
            ['name' => 'caja.ver-todo',              'label' => 'Ver toda la caja',      'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Ver ingresos/egresos de todos los usuarios (sin poder editar lo histórico)'],
            ['name' => 'caja.eliminar',              'label' => 'Eliminar',              'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Eliminar ingresos/egresos y sus adjuntos'],

            // ─── Permisos finos sobre Clientes / Créditos / Pagos ───
            ['name' => 'clientes.scope-propio',         'label' => 'Solo mis clientes',    'module' => 'clientes', 'module_label' => 'Clientes', 'description' => 'Limita la lista a clientes asignados al usuario'],
            ['name' => 'clientes.eliminar',             'label' => 'Eliminar clientes',    'module' => 'clientes', 'module_label' => 'Clientes', 'description' => 'Eliminar clientes y sus adjuntos'],
            ['name' => 'creditos.eliminar',             'label' => 'Eliminar créditos',    'module' => 'creditos', 'module_label' => 'Créditos', 'description' => 'Eliminar registros de créditos'],
            ['name' => 'creditos.ser-asesor-responsable', 'label' => 'Ser asesor responsable', 'module' => 'creditos', 'module_label' => 'Créditos', 'description' => 'El usuario aparece en el select "Asesor responsable" de clientes/créditos/refinanciamientos'],
            ['name' => 'pagos.eliminar',                'label' => 'Eliminar/anular pagos', 'module' => 'pagos',    'module_label' => 'Pagos',    'description' => 'Anular o eliminar pagos registrados'],
            ['name' => 'pagos.mora-manual',             'label' => 'Mora manual',          'module' => 'pagos',    'module_label' => 'Pagos',    'description' => 'Editar/override del Total Mora al registrar un pago'],

            // ─── Permisos finos transversales ───
            ['name' => 'usuarios.gestionar-permisos', 'label' => 'Gestionar permisos',      'module' => 'configuracion', 'module_label' => 'Configuración',  'description' => 'Modificar la matriz de permisos de otros usuarios'],
            ['name' => 'transacciones.autorizar',     'label' => 'Autorizar transacciones', 'module' => 'transacciones', 'module_label' => 'Transacciones', 'description' => 'Autorizar acciones que requieren visto bueno gerencial (anulaciones, ajustes, etc.)'],
            ['name' => 'acceso.cross-headquarter',    'label' => 'Ver todas las sedes',    'module' => 'acceso',        'module_label' => 'Acceso',         'description' => 'El usuario ve datos de todas las sucursales (no solo la suya)'],
            ['name' => 'clientes.editar-identidad',   'label' => 'Editar identidad cliente', 'module' => 'clientes',     'module_label' => 'Clientes',       'description' => 'Editar campos críticos del cliente: DNI, nombre, apellidos'],
        ];

        foreach ($items as $it) {
            Perm::updateOrCreate(
                ['name' => $it['name'], 'guard_name' => $guard],
                [
                    'module' => $it['module'] ?? null,
                    'module_label' => $it['module_label'] ?? null,
                    'label' => $it['label'] ?? null,
                    'description' => $it['description'] ?? null,
                ]
            );
        }

        // Limpieza: borrar permisos legacy que ya no estén en el catálogo (idempotente)
        $current = collect($items)->pluck('name')->all();
        Perm::where('guard_name', $guard)->whereNotIn('name', $current)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
