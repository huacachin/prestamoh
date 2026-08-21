<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermisosVista;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra los checks de visualización (/users/{id}/perms) de TODOS los
 * usuarios según su rol actual (matriz ∩ PermisosVista::LISTA).
 *
 * ⚠️ RESETEA los checks personalizados a los del rol — usarlo solo en el
 * primer despliegue del modelo de vista o tras una remigración (en la
 * remigración normal no hace falta: installation:migrate-roles asigna roles
 * y el override de User::assignRole siembra la vista solo).
 */
class PermisosVistaSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::with('roles.permissions', 'permissions')->get()->each(function (User $user) {
            PermisosVista::sincronizarConRol($user);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
