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
        $users = [
            [
                'id'              => 1,
                'name'            => 'Admin',
                'username'        => 'admin',
                'email'           => 'admin@prestamos.local',
                'password'        => bcrypt('admin123'),
                'document_type'   => 'DNI',
                'document_number' => '00000000',
                'phone'           => '000000000',
                'headquarter_id'  => 1,
                'status'          => 'active',
                'nivel'           => 6,
                'role'            => 'director',
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['id' => $data['id']],
                [
                    'name'            => $data['name'],
                    'username'        => $data['username'],
                    'email'           => $data['email'],
                    'password'        => $data['password'],
                    'document_type'   => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'phone'           => $data['phone'],
                    'headquarter_id'  => $data['headquarter_id'],
                    'status'          => $data['status'],
                    'nivel'           => $data['nivel'],
                ]
            );

            if ($data['role']) {
                $user->syncRoles([$data['role']]);
            }

            // Sin permisos directos: la herencia por rol cubre todo.
            $user->syncPermissions([]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
