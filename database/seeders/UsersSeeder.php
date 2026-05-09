<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Roles y permisos vienen de RoleSetupSeeder y RolePermissionSeeder.
        // Director hereda automáticamente todos los permisos vía rol.
        $email    = 'admin@prestamos.local';
        $username = 'admin';

        // Buscar primero por email (clave única real de identidad). Si no, por username.
        // No forzamos id=1: en una BD con datos legacy importados, id=1 puede pertenecer
        // a un usuario real y un updateOrCreate por id sobrescribiría sus datos.
        $admin = User::where('email', $email)->first()
            ?? User::where('username', $username)->first();

        if ($admin) {
            // Existe: NO tocamos campos únicos (email, username, document_number) para
            // evitar choques con otros registros. Sólo refrescamos lo seguro.
            $admin->fill([
                'name'           => 'Admin',
                'headquarter_id' => $admin->headquarter_id ?? 1,
                'status'         => 'active',
                'nivel'          => 6,
            ])->save();
        } else {
            $admin = User::create([
                'name'            => 'Admin',
                'username'        => $username,
                'email'           => $email,
                'password'        => bcrypt('admin123'),
                'document_type'   => 'DNI',
                'document_number' => '00000000',
                'phone'           => '000000000',
                'headquarter_id'  => 1,
                'status'          => 'active',
                'nivel'           => 6,
            ]);
        }

        $admin->syncRoles(['director']);
        // Sin permisos directos: la herencia por rol cubre todo.
        $admin->syncPermissions([]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
