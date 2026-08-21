<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PermisosVista;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migra usuarios con roles legacy a sus equivalentes en el organigrama nuevo.
 * Borra los roles legacy una vez vaciados.
 */
class InstallationMigrateRoles extends Command
{
    protected $signature = 'installation:migrate-roles {--dry-run : Mostrar cambios sin aplicarlos}';

    protected $description = 'Mapea usuarios de roles legacy → roles nuevos y elimina los roles legacy.';

    /** Mapping: rol viejo → rol nuevo */
    private const MAP = [
        'superusuario' => 'director',
        'asesor' => 'analista-creditos',
        'cobranza' => 'cobranzas',
        'web' => null,   // se elimina sin reasignar (no había UI externa operativa)
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('═══ Migración de roles legacy → nuevos ═══');
        $this->newLine();

        $changes = [];   // [user_id, old_role, new_role|null]
        $missingTarget = [];

        foreach (User::with('roles')->get() as $u) {
            foreach ($u->roles as $r) {
                if (! array_key_exists($r->name, self::MAP)) {
                    continue;
                }
                $newRole = self::MAP[$r->name];
                if ($newRole && ! Role::where('name', $newRole)->where('guard_name', 'web')->exists()) {
                    $missingTarget[$newRole] = true;

                    continue;
                }
                $changes[] = [$u->id, $u->name, $r->name, $newRole];
            }
        }

        if (! empty($missingTarget)) {
            $this->error('Faltan roles destino: '.implode(', ', array_keys($missingTarget)).
                        '. Ejecuta `php artisan db:seed --class=RoleSetupSeeder` antes.');

            return self::FAILURE;
        }

        if (empty($changes)) {
            $this->info('✓ No hay usuarios con roles legacy. Solo limpio roles vacíos…');
        } else {
            $this->table(
                ['User ID', 'Nombre', 'Rol legacy', 'Rol nuevo'],
                array_map(fn ($c) => [$c[0], $c[1], $c[2], $c[3] ?? '— (sin reasignar)'], $changes)
            );
        }

        if ($dryRun) {
            $this->warn('DRY RUN — sin cambios aplicados.');

            return self::SUCCESS;
        }

        if (! empty($changes) && ! $this->confirm('¿Aplicar la migración?', true)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as [$userId, $userName, $oldRole, $newRole]) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                if ($newRole) {
                    if (! $user->hasRole($newRole)) {
                        $user->assignRole($newRole);
                    }
                }

                $user->removeRole($oldRole);
            }
        });

        // Borrar roles legacy ahora que ningún usuario los tiene
        foreach (array_keys(self::MAP) as $legacy) {
            $role = Role::where('name', $legacy)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }
            $stillUsed = $role->users()->count();
            if ($stillUsed > 0) {
                $this->warn("Rol '$legacy' aún tiene $stillUsed usuario(s) — no se elimina.");

                continue;
            }
            $role->delete();
            $this->line("  · Eliminado rol legacy: $legacy");
        }

        // Limpiar permisos directos huérfanos del legacy y RE-SEMBRAR la vista:
        // los checkboxes de /users/{id}/perms son permisos DIRECTOS (modelo de
        // visualización 21/08) — sin esta siembra, borrar model_has_permissions
        // dejaría a todos los no-directores sin ningún módulo visible.
        $clearedDirect = DB::table('model_has_permissions')->delete();
        if ($clearedDirect > 0) {
            $this->line("  · Borrados $clearedDirect permisos directos legacy.");
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sembrados = 0;
        User::with('roles.permissions', 'permissions')->get()->each(function (User $u) use (&$sembrados) {
            PermisosVista::sincronizarConRol($u);
            $sembrados++;
        });
        $this->line("  · Vista re-sembrada para $sembrados usuario(s) según su rol.");

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('✓ Migración aplicada.');

        return self::SUCCESS;
    }
}
