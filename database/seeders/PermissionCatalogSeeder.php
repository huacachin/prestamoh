<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission as Perm;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::disableForeignKeyConstraints();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        $items = [
            // Singles
            ['name' => 'dashboard',  'label' => 'Dashboard',  'module' => 'dashboard',  'module_label' => 'Dashboard',  'description' => 'Acceso al Dashboard'],
            ['name' => 'clientes',   'label' => 'Clientes',   'module' => 'clientes',   'module_label' => 'Clientes',   'description' => 'Acceso a Clientes'],
            ['name' => 'creditos',   'label' => 'Créditos',   'module' => 'creditos',   'module_label' => 'Créditos',   'description' => 'Acceso a Créditos'],
            ['name' => 'pagos',      'label' => 'Pagos',      'module' => 'pagos',      'module_label' => 'Pagos',      'description' => 'Acceso a Pagos'],

            // Caja (hijos)
            ['name' => 'caja.apertura', 'label' => 'Apertura',  'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Apertura de Caja'],
            ['name' => 'caja.ingresos', 'label' => 'Ingresos',  'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Ingresos'],
            ['name' => 'caja.egresos',  'label' => 'Egresos',   'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Egresos'],
            ['name' => 'caja.balance',  'label' => 'Balance',   'module' => 'caja', 'module_label' => 'Caja', 'description' => 'Acceso a Balance'],

            // Reportes (hijos)
            ['name' => 'reportes.cartera',    'label' => 'Cartera Activa', 'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Cartera'],
            ['name' => 'reportes.pagos',      'label' => 'Pagos',          'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Pagos'],
            ['name' => 'reportes.morosidad',  'label' => 'Morosidad',      'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Morosidad'],
            ['name' => 'reportes.caja',       'label' => 'Caja',           'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Acceso a Reporte Caja'],

            // Configuración (hijos)
            ['name' => 'configuracion.usuarios',    'label' => 'Usuarios',     'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Usuarios'],
            ['name' => 'configuracion.sucursales',   'label' => 'Sucursales',   'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Sucursales'],
            ['name' => 'configuracion.conceptos',    'label' => 'Conceptos',    'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Conceptos'],
            ['name' => 'configuracion.tipo-cambio',  'label' => 'Tipo Cambio',  'module' => 'configuracion', 'module_label' => 'Configuración', 'description' => 'Acceso a Tipo de Cambio'],

            // Registro
            ['name' => 'registro.activar',         'label' => 'Activar Prestamos',   'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Activar préstamos cancelados/refinanciados'],
            ['name' => 'registro.estado',          'label' => 'Cambiar Estado',      'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Cambiar estado/situación de créditos'],
            ['name' => 'registro.cesados',         'label' => 'Clientes Cesados',    'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Ver clientes cesados/inactivos'],
            ['name' => 'registro.eliminar-masivo', 'label' => 'Eliminar Masivo',     'module' => 'registro', 'module_label' => 'Registro', 'description' => 'Eliminación masiva de registros'],

            // Reportes (nuevos)
            ['name' => 'reportes.asesor',              'label' => 'Reporte de Asesor',       'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte diario por asesor'],
            ['name' => 'reportes.caja-estadistica',    'label' => 'Estad. Caja M.A.',       'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte estadístico de caja mensual/anual'],
            ['name' => 'reportes.credito-estadistica', 'label' => 'Estad. Crédito',         'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte estadístico de créditos'],
            ['name' => 'reportes.caja-general-1',      'label' => 'Rep. General Caja 1',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 1'],
            ['name' => 'reportes.caja-general-2',      'label' => 'Rep. General Caja 2',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 2'],
            ['name' => 'reportes.caja-general-3',      'label' => 'Rep. General Caja 3',    'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Reporte general de caja 3'],
            ['name' => 'reportes.cancelados',          'label' => 'Resumen Cancelados',     'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Resumen de créditos cancelados'],
            ['name' => 'reportes.simulador',           'label' => 'Simulador',              'module' => 'reportes', 'module_label' => 'Reportes', 'description' => 'Simulador de crédito'],
        ];

        foreach ($items as $it) {
            $p = new Perm();
            $p->name         = $it['name'];
            $p->guard_name   = $guard;
            $p->module       = $it['module'] ?? null;
            $p->module_label = $it['module_label'] ?? null;
            $p->label        = $it['label'] ?? null;
            $p->description  = $it['description'] ?? null;
            $p->save();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
