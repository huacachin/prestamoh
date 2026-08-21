<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPerms = Permission::where('guard_name', 'web')->pluck('name')->all();

        // Permisos restrictivos: limitan la vista en lugar de otorgar capacidades.
        // El Director NO debe recibirlos aunque sea super-rol, porque "tener" un
        // permiso restrictivo recorta lo que ve (ej. scope-propio limita a sus
        // clientes asignados). Asignárselos al Director rompe la regla "Director
        // ve TODO".
        $restrictivos = ['clientes.scope-propio'];
        $directorPerms = array_values(array_diff($allPerms, $restrictivos));

        $matrix = [
            // ─── DIRECTOR (super, sin permisos restrictivos) ───
            'director' => $directorPerms,

            // ─── GERENTE (operativa + reportes amplios + autorizar) ───
            'gerente' => [
                'dashboard',
                'clientes', 'creditos', 'pagos',
                'pagos.mora-manual',
                'caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.ver-todo',
                'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
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
                'caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.ver-todo',
                // Sin bypass-fecha-anterior ni editar-historico (21/08): el
                // administrador opera SOLO el día en curso; lo histórico es
                // exclusivo del director.
                'caja.eliminar',
                // Eliminar clientes/créditos y editar identidad (DNI, nombres):
                // exclusivos del director (mapa confirmado 21/08).
                'pagos.eliminar', 'pagos.mora-manual',
                'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
                'reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja',
                'reportes.asesor', 'reportes.caja-estadistica', 'reportes.credito-estadistica',
                'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
                'reportes.cancelados', 'reportes.simulador',
                'configuracion.usuarios', 'configuracion.sucursales',
                'configuracion.conceptos', 'configuracion.tipo-cambio',
                'usuarios.gestionar-permisos',
                'registro.activar', 'registro.estado', 'registro.cesados',
                'registro.eliminar-masivo',
                'transacciones.autorizar',
                'acceso.cross-headquarter',
            ],

            // ─── ANALISTA DE CRÉDITOS (ex Asesor: captura, ve sus créditos) ───
            'analista-creditos' => [
                // Sin dashboard: entra directo a su listado de créditos.
                'clientes', 'creditos', 'pagos',
                'clientes.scope-propio',
                'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
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
                'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
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
                'caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.ver-todo', 'caja.editar-historico',
                'reportes.credito-diario', 'reportes.credito-mensual', 'reportes.credito-semanal',
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
            if (! $role) {
                $this->command->warn("Rol '$roleName' no existe — saltando.");

                continue;
            }
            $role->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
