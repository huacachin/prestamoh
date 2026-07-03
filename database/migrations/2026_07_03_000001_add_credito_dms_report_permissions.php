<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reporte Credito D./M./S. dejan de usar el permiso "pagos" y pasan a tener
 * permiso propio. Para no quitarle acceso a nadie, se otorgan a todo rol y
 * usuario que hoy tenga "pagos" (directo).
 */
return new class extends Migration
{
    private array $perms = [
        'reportes.credito-diario' => ['label' => 'Reporte Credito D.', 'description' => 'Reporte de créditos diarios'],
        'reportes.credito-mensual' => ['label' => 'Reporte Credito M.', 'description' => 'Reporte de créditos mensuales'],
        'reportes.credito-semanal' => ['label' => 'Reporte Credito S.', 'description' => 'Reporte de créditos semanales'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->perms as $name => $extra) {
            \App\Models\Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'reportes', 'module_label' => 'Reportes'] + $extra
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // App\Models\Permission es un modelo plano (catálogo); las relaciones
        // roles/users viven en el modelo de Spatie.
        $nombres = array_keys($this->perms);
        $pagos = \Spatie\Permission\Models\Permission::where('name', 'pagos')->where('guard_name', 'web')->first();
        if ($pagos) {
            foreach ($pagos->roles as $role) {
                $role->givePermissionTo($nombres);
            }
            foreach ($pagos->users as $user) {
                $user->givePermissionTo($nombres);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        \App\Models\Permission::whereIn('name', array_keys($this->perms))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
