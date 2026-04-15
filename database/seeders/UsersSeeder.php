<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        Role::firstOrCreate(['name' => 'superusuario',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'director',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'asesor',        'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cobranza',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'web',           'guard_name' => 'web']);

        $allPerms = [
            'dashboard', 'clientes', 'creditos', 'pagos',
            'caja.apertura', 'caja.ingresos', 'caja.egresos', 'caja.balance',
            'reportes.cartera', 'reportes.pagos', 'reportes.morosidad', 'reportes.caja',
            'configuracion.usuarios', 'configuracion.sucursales', 'configuracion.conceptos', 'configuracion.tipo-cambio',
            // Registro
            'registro.activar', 'registro.estado', 'registro.cesados', 'registro.eliminar-masivo',
            // Reportes (nuevos)
            'reportes.asesor', 'reportes.caja-estadistica', 'reportes.credito-estadistica',
            'reportes.caja-general-1', 'reportes.caja-general-2', 'reportes.caja-general-3',
            'reportes.cancelados', 'reportes.simulador',
        ];

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
                'role'            => 'superusuario',
                'direct_perms'    => $allPerms,
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

            $user->syncPermissions($data['direct_perms']);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
