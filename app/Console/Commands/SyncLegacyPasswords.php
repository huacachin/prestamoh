<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SyncLegacyPasswords extends Command
{
    protected $signature = 'legacy:sync-passwords
                            {--dry-run : Solo mostrar qué se actualizaría, sin guardar cambios}';

    protected $description = 'Sincroniza las contraseñas desde la columna obs3 de la tabla legacy clientes';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo DRY-RUN: no se realizarán cambios reales.');
        }

        // Verificar conexión legacy
        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error('No se puede conectar a la base de datos legacy: ' . $e->getMessage());
            return 1;
        }

        $legacyUsers = DB::connection('legacy')
            ->table('clientes')
            ->whereNotNull('user')
            ->where('user', '<>', '')
            ->get(['cod', 'user', 'nombre', 'obs3']);

        $this->info("Usuarios legacy encontrados: {$legacyUsers->count()}");
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        $rows = [];
        foreach ($legacyUsers as $lu) {
            $username = strtolower(trim($lu->user));
            $obs3 = trim($lu->obs3 ?? '');

            if (empty($username)) {
                continue;
            }

            $user = User::where('username', $username)->first();

            if (!$user) {
                $notFound++;
                $rows[] = [$username, $lu->nombre, '❌ NO ENCONTRADO', '-'];
                continue;
            }

            if (empty($obs3)) {
                $skipped++;
                $rows[] = [$username, $lu->nombre, '⚠️  obs3 vacío', '-'];
                continue;
            }

            if (!$dryRun) {
                $user->password = Hash::make($obs3);
                $user->save();
            }

            $updated++;
            $rows[] = [
                $username,
                $lu->nombre,
                '✅ ' . ($dryRun ? 'A actualizar' : 'Actualizada'),
                $obs3,
            ];
        }

        $this->table(['Username', 'Nombre', 'Estado', 'Contraseña'], $rows);
        $this->newLine();

        $this->info("Resumen:");
        $this->line("  ✅ Actualizadas: {$updated}");
        $this->line("  ⚠️  Omitidas (sin obs3): {$skipped}");
        $this->line("  ❌ No encontradas en Laravel: {$notFound}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('No se hicieron cambios reales. Quita --dry-run para aplicar.');
        }

        return 0;
    }
}
