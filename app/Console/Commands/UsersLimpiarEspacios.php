<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Recorta los espacios sobrantes de los datos de usuario ya guardados.
 *
 * Livewire no pasa por el TrimStrings de las peticiones normales, así que un
 * espacio de más al tipear se guardaba tal cual: "Marvin " (usuario 8) y
 * "Licet Tafur Collantes " (usuario 3). No rompe el ingreso —la colación
 * utf8mb4_unicode_ci ignora los espacios finales al comparar— pero ensucia
 * exportes y comparaciones en código, y rompería si la base pasara a una
 * colación NO PAD. El formulario ya recorta al guardar; esto limpia lo viejo.
 *
 * Idempotente: correrlo dos veces no cambia nada.
 */
class UsersLimpiarEspacios extends Command
{
    protected $signature = 'users:limpiar-espacios {--dry-run : Solo mostrar qué cambiaría}';

    protected $description = 'Recorta espacios sobrantes en nombre, usuario, email, documento y teléfono de los usuarios';

    /** @var list<string> */
    private const CAMPOS = ['name', 'username', 'email', 'document_number', 'phone'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? '── DRY-RUN (no se toca nada) ──' : '── EJECUCIÓN REAL ──');

        $tocados = 0;
        foreach (User::query()->orderBy('id')->get() as $user) {
            $cambios = [];
            foreach (self::CAMPOS as $campo) {
                $valor = $user->{$campo};
                if (! is_string($valor)) {
                    continue;
                }
                $limpio = trim($valor);
                if ($limpio !== $valor) {
                    $cambios[$campo] = $limpio;
                }
            }

            if ($cambios === []) {
                continue;
            }

            $tocados++;
            foreach ($cambios as $campo => $limpio) {
                $this->line(sprintf('   #%d %-16s "%s" → "%s"', $user->id, $campo, $user->{$campo}, $limpio));
            }

            if (! $dry) {
                // Guarda SIN tocar updated_at: es una limpieza, no una edición.
                User::where('id', $user->id)->update($cambios);
            }
        }

        if ($tocados === 0) {
            $this->info('✓ No hay espacios sobrantes que limpiar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($dry
            ? "Dry-run: {$tocados} usuario(s) cambiarían. Quita --dry-run para aplicar."
            : "✓ {$tocados} usuario(s) limpiados.");

        return self::SUCCESS;
    }
}
