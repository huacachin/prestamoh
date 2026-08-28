<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleSetupSeeder extends Seeder
{
    /**
     * Roles homologados al organigrama actual de Huacachin.
     *  - "director" reemplaza al viejo "superusuario" (es el rol con todos los permisos).
     *  - "analista-creditos" reemplaza al viejo "asesor".
     *  - "cobranzas" reemplaza al viejo "cobranza".
     *  - Roles "web" y "supervisor"/"controlador" (legacy) quedan eliminados.
     */
    public const ROLES = [
        'director',           // super (todos los permisos)
        'gerente',
        'administrador',
        'analista-creditos',  // ex "asesor"
        'area-legal',
        'cobranzas',          // ex "cobranza"
        'caja',
        'contabilidad',
        'marketing',
    ];

    public function run(): void
    {
        $guard = 'web';

        foreach (self::ROLES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }
    }

    /**
     * Devuelve los roles ordenados por jerarquía organizacional (Director arriba, Marketing abajo).
     * Roles ajenos a la jerarquía quedan al final, ordenados por nombre.
     */
    /**
     * Roles que se pueden ASIGNAR desde /users (pedido 21/08): el resto del
     * catálogo (gerente, contabilidad, cobranzas, caja, area-legal, marketing)
     * existe para el futuro pero queda bloqueado en el formulario y en el
     * backend hasta que el negocio los active.
     */
    public const ROLES_ASIGNABLES = ['director', 'administrador', 'analista-creditos', 'area-legal'];

    /** Solo los roles asignables, en el orden de la jerarquía. */
    public static function asignableRoles(array $columns = ['id', 'name']): Collection
    {
        return self::orderedRoles($columns)
            ->filter(fn ($r) => in_array($r->name, self::ROLES_ASIGNABLES, true))
            ->values();
    }

    public static function orderedRoles(array $columns = ['id', 'name']): Collection
    {
        $hierarchy = array_flip(self::ROLES);

        return Role::where('guard_name', 'web')
            ->get($columns)
            ->sortBy(fn ($r) => $hierarchy[$r->name] ?? PHP_INT_MAX)
            ->values();
    }
}
