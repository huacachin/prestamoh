<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallationCheck extends Command
{
    protected $signature = 'installation:check';
    protected $description = 'Health check de la instalación (read-only). No modifica nada.';

    private array $issues = [];

    public function handle(): int
    {
        $this->info('═══ HEALTH CHECK del sistema Préstamos Huacachin ═══');
        $this->newLine();

        $this->checkStorageSymlink();
        $this->checkCriticalTables();
        $this->checkRolesAndPermissions();
        $this->checkConcepts();
        $this->checkCorrelativos();
        $this->checkInvalidDates();
        $this->checkMissingFields();
        $this->checkAutoIncrement();
        $this->checkMassDeletionsConsistency();
        $this->checkClientHeadquarter();

        $this->newLine();
        if (empty($this->issues)) {
            $this->info('✓ Sistema OK — sin problemas detectados.');
            return self::SUCCESS;
        }

        $this->warn('⚠ Se detectaron ' . count($this->issues) . ' problema(s):');
        foreach ($this->issues as $i => $msg) {
            $this->line('  ' . ($i + 1) . '. ' . $msg);
        }
        $this->newLine();
        $this->info('Para aplicar los fixes automáticos: php artisan installation:run-all');
        return self::FAILURE;
    }

    private function ok2(string $msg): void { $this->line('  <fg=green>✓</> ' . $msg); }
    private function warn2(string $msg): void { $this->line('  <fg=yellow>⚠</> ' . $msg); $this->issues[] = $msg; }
    private function bad(string $msg): void { $this->line('  <fg=red>✗</> ' . $msg); $this->issues[] = $msg; }

    private function checkStorageSymlink(): void
    {
        $this->info('Storage symlink:');
        is_link(public_path('storage'))
            ? $this->ok2('public/storage existe')
            : $this->bad('Falta symlink: ejecutar `php artisan storage:link`');
        $this->newLine();
    }

    private function checkCriticalTables(): void
    {
        $this->info('Tablas críticas:');
        $tables = [
            'clients', 'credits', 'credit_installments', 'payments',
            'incomes', 'expenses', 'mass_deletions', 'mass_deletion_details',
            'cash_openings', 'mora_acumulada', 'dias_mora', 'correlativos',
            'concepts', 'headquarters', 'users',
            'client_attachments', 'client_avales', 'income_attachments', 'expense_attachments',
        ];
        foreach ($tables as $t) {
            Schema::hasTable($t)
                ? $this->ok2("Tabla `$t` existe")
                : $this->bad("Falta tabla `$t` — ejecutar `php artisan migrate`");
        }
        $this->newLine();
    }

    private function checkRolesAndPermissions(): void
    {
        $this->info('Roles y permisos:');

        $expectedRoles = \Database\Seeders\RoleSetupSeeder::ROLES;
        $existing = DB::table('roles')->where('guard_name', 'web')->pluck('name')->all();

        $missing = array_diff($expectedRoles, $existing);
        $extra   = array_diff($existing, $expectedRoles);

        if (empty($missing)) {
            $this->ok2(count($expectedRoles) . ' roles homologados (' . implode(', ', $expectedRoles) . ')');
        } else {
            $this->bad('Faltan roles: ' . implode(', ', $missing) . ' — ejecutar `php artisan db:seed --class=RoleSetupSeeder`');
        }

        if (!empty($extra)) {
            $this->warn2('Roles legacy aún presentes: ' . implode(', ', $extra) . ' — usar `installation:migrate-roles`');
        }

        $perms = DB::table('permissions')->count();
        $perms >= 38 ? $this->ok2("$perms permisos registrados") : $this->warn2("Solo $perms permisos — esperados al menos 38");

        // Director (super) debe tener TODOS los permisos
        $director = DB::table('roles')->where('name', 'director')->first();
        if ($director) {
            $assigned = DB::table('role_has_permissions')->where('role_id', $director->id)->count();
            $assigned === $perms
                ? $this->ok2("Director tiene los $perms permisos (super-rol)")
                : $this->warn2("Director tiene $assigned/$perms permisos — ejecutar `db:seed --class=RolePermissionSeeder`");
        }

        // Verificar usuarios sin rol
        $sinRol = DB::table('users')
            ->whereNotIn('id', DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->pluck('model_id'))
            ->where('status', 'active')
            ->count();
        $sinRol === 0
            ? $this->ok2('Todos los usuarios activos tienen rol')
            : $this->warn2("$sinRol usuarios activos sin rol asignado");

        $this->newLine();
    }

    private function checkConcepts(): void
    {
        $this->info('Catálogo de Conceptos:');
        $ing = DB::table('concepts')->where('type', 'ingreso')->count();
        $egr = DB::table('concepts')->where('type', 'egreso')->count();
        $ing > 0 ? $this->ok2("$ing conceptos de ingreso") : $this->warn2("Sin conceptos de ingreso — cargar seeder");
        $egr > 0 ? $this->ok2("$egr conceptos de egreso") : $this->warn2("Sin conceptos de egreso — cargar seeder");
        $this->newLine();
    }

    private function checkCorrelativos(): void
    {
        $this->info('Correlativos:');
        $tipos = ['Cliente', 'Credito'];
        foreach ($tipos as $t) {
            $val = DB::table('correlativos')->where('tipo', $t)->value('correl');
            $val !== null
                ? $this->ok2("Correlativo $t = $val")
                : $this->bad("Falta correlativo `$t` — ejecutar `php artisan installation:sync-correlativos`");
        }
        $this->newLine();
    }

    private function checkInvalidDates(): void
    {
        $this->info('Fechas inválidas (0000-00-00):');

        // payments.fecha (NULL es legítimo — solo chequear 0000-00-00)
        try {
            $n = DB::table('payments')->whereRaw("CAST(fecha AS CHAR) = '0000-00-00'")->count();
            $n === 0
                ? $this->ok2('payments sin 0000-00-00')
                : $this->warn2("$n payments con fecha=0000-00-00 — usar `installation:fix-invalid-dates`");
        } catch (\Throwable $e) {}

        try {
            $n = DB::table('credits')->whereRaw("CAST(fecha_cancelacion AS CHAR) = '0000-00-00'")->count();
            $n === 0
                ? $this->ok2('credits.fecha_cancelacion sin 0000-00-00')
                : $this->warn2("$n credits con fecha_cancelacion=0000-00-00");
        } catch (\Throwable $e) {}

        $this->newLine();
    }

    private function checkMissingFields(): void
    {
        $this->info('Campos requeridos por nuevos features:');

        $expSinDoc = DB::table('expenses')->whereNull('documento')->count();
        $expSinDoc === 0
            ? $this->ok2('expenses.documento poblado')
            : $this->warn2("$expSinDoc expenses sin `documento` — usar `installation:fix-expense-documento`");

        $incSinModo = DB::table('incomes')->whereNull('modo')->count();
        $incSinModo === 0 ? $this->ok2('incomes.modo poblado') : $this->warn2("$incSinModo incomes sin `modo`");

        $expSinModo = DB::table('expenses')->whereNull('modo')->count();
        $expSinModo === 0 ? $this->ok2('expenses.modo poblado') : $this->warn2("$expSinModo expenses sin `modo`");

        $incSinCaja = DB::table('incomes')->whereNull('caja')->count();
        $incSinCaja === 0 ? $this->ok2('incomes.caja poblado') : $this->warn2("$incSinCaja incomes sin `caja`");

        $expSinCaja = DB::table('expenses')->whereNull('caja')->count();
        $expSinCaja === 0 ? $this->ok2('expenses.caja poblado') : $this->warn2("$expSinCaja expenses sin `caja`");

        $this->newLine();
    }

    private function checkAutoIncrement(): void
    {
        $this->info('AUTO_INCREMENT vs MAX(id):');
        $tables = ['clients', 'credits', 'credit_installments', 'payments',
                   'incomes', 'expenses', 'mass_deletions', 'mass_deletion_details'];
        $bad = 0;
        foreach ($tables as $t) {
            $max = (int) DB::table($t)->max('id');
            try {
                $row = DB::selectOne("SHOW TABLE STATUS LIKE '$t'");
                $ai  = (int) ($row->Auto_increment ?? 0);
            } catch (\Throwable $e) {
                continue;
            }
            // InnoDB cachea SHOW TABLE STATUS: AI puede reportarse igual a MAX y el próximo INSERT
            // igual asignar MAX+1 correctamente. Solo flagear si AI < MAX (real corruption).
            if ($ai >= $max) {
                $this->ok2("$t · max=$max next_ai=$ai");
            } else {
                $this->warn2("$t · max=$max next_ai=$ai (AUTO_INCREMENT bajo — usar `installation:fix-autoincrement`)");
                $bad++;
            }
        }
        $this->newLine();
    }

    private function checkMassDeletionsConsistency(): void
    {
        $this->info('Consistencia mass_deletions:');
        $diff = DB::table('mass_deletions as m')
            ->leftJoin('mass_deletion_details as d', 'd.mass_deletion_id', '=', 'm.id')
            ->select('m.id')
            ->groupBy('m.id', 'm.amount')
            ->havingRaw('ROUND(m.amount - COALESCE(SUM(d.amount), 0), 2) != 0')
            ->count();
        $diff === 0
            ? $this->ok2('Todos los amount coinciden con sus details')
            : $this->warn2("$diff mass_deletions con amount ≠ SUM(details) — usar `mass-deletions:fix-amounts`");
        $this->newLine();
    }

    private function checkClientHeadquarter(): void
    {
        $this->info('headquarter_id en usuarios y data:');
        $u = DB::table('users')->whereNull('headquarter_id')->count();
        $u === 0 ? $this->ok2('Usuarios con headquarter_id') : $this->warn2("$u usuarios sin headquarter_id (asignar manualmente)");
        $this->newLine();
    }
}
