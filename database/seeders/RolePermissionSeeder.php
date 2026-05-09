<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Asigna la matriz de permisos a cada rol homologado.
 *
 * Reglas:
 *  - Director: TODOS los permisos (super-rol).
 *  - Resto: matriz explícita acordada con el cliente.
 *
 * Re-ejecutable: usa syncPermissions, no acumula.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $allPerms = Permission::where('guard_name', 'web')->pluck('name')->all();

        $matrix = [
            // ─── DIRECTOR (super) ───
            'director' => $allPerms,

            // ─── GERENTE (operativa + reportes amplios + autorizar) ───
            'gerente' => [
                'dashboard',
                'clientes', 'creditos', 'pagos',
                'caja.apertura', 'caja.ingresos', 'caja.egresos',
                'reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja',
                'reportes.asesor', 'reportes.caja-estadistica', 'reportes.credito-estadistica',
                'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
                'reportes.cancelados', 'reportes.simulador',
                'registro.activar', 'registro.estado', 'registro.cesados',
                'registro.eliminar-masivo', 'registro.eliminar-masivo.revertir',
                'transacciones.autorizar',
            ],

            // ─── ADMINISTRADOR (operativa + configuración completa) ───
            'administrador' => [
                'dashboard',
                'clientes', 'creditos', 'pagos',
                'caja.apertura', 'caja.ingresos', 'caja.egresos',
                'caja.bypass-fecha-anterior', 'caja.editar-historico', 'caja.eliminar',
                'clientes.eliminar', 'clientes.editar-identidad',
                'creditos.eliminar', 'pagos.eliminar',
                'reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja',
                'reportes.asesor', 'reportes.caja-estadistica', 'reportes.credito-estadistica',
                'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
                'reportes.cancelados', 'reportes.simulador',
                'configuracion.usuarios', 'configuracion.sucursales',
                'configuracion.conceptos', 'configuracion.tipo-cambio',
                'usuarios.gestionar-permisos',
                'registro.activar', 'registro.estado', 'registro.cesados',
                'registro.eliminar-masivo', 'registro.eliminar-masivo.revertir',
                'transacciones.autorizar',
                'acceso.cross-headquarter',
            ],

            // ─── ANALISTA DE CRÉDITOS (ex Asesor: captura, ve sus créditos) ───
            'analista-creditos' => [
                'dashboard',
                'clientes', 'creditos', 'pagos',
                'clientes.scope-propio',
                'creditos.ser-asesor-responsable',
                'reportes.cartera', 'reportes.asesor', 'reportes.simulador',
                'reportes.credito-estadistica',
                'registro.cesados',
            ],

            // ─── AREA LEGAL (consulta + cambia estado para casos legales) ───
            'area-legal' => [
                'dashboard',
                'clientes', 'creditos',
                'reportes.cartera', 'reportes.morosidad', 'reportes.cancelados',
                'reportes.credito-estadistica',
                'registro.estado', 'registro.cesados',
            ],

            // ─── COBRANZAS (recibe pagos, ve cartera por cobrar) ───
            'cobranzas' => [
                'dashboard',
                'clientes', 'creditos', 'pagos',
                'caja.ingresos',
                'creditos.ser-asesor-responsable',
                'reportes.cartera', 'reportes.pagos', 'reportes.morosidad',
                'registro.cesados',
            ],

            // ─── CAJA (apertura/cierre, ingresos/egresos, reportes de caja) ───
            'caja' => [
                'dashboard',
                'caja.apertura', 'caja.ingresos', 'caja.egresos',
                'reportes.caja', 'reportes.caja-estadistica',
                'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
                'configuracion.tipo-cambio',
            ],

            // ─── CONTABILIDAD (lectura amplia + edición de caja histórica) ───
            'contabilidad' => [
                'dashboard',
                'creditos', 'pagos',
                'caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.editar-historico',
                'reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja',
                'reportes.asesor', 'reportes.caja-estadistica', 'reportes.credito-estadistica',
                'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
                'reportes.cancelados',
                'configuracion.conceptos', 'configuracion.tipo-cambio',
                'acceso.cross-headquarter',
            ],

            // ─── MARKETING (lectura de cartera para análisis comercial) ───
            'marketing' => [
                'dashboard',
                'clientes',
                'reportes.cartera', 'reportes.credito-estadistica',
            ],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                $this->command->warn("Rol '$roleName' no existe — saltando.");
                continue;
            }
            $role->syncPermissions($perms);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
