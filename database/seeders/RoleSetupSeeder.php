<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSetupSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'director', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'asesor', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'cobranza', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'web', 'guard_name' => $guard]);
    }
}
