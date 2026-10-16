<?php

namespace App\Console\Commands;

use App\Models\ClaveSeguridad;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crea, cambia o comprueba la clave de seguridad que protege dar o quitar el
 * rol Director (16/10/2026, Antony; la custodia el gerente). Solo se guarda el
 * hash. Formas de uso:
 *
 *   usuarios:clave-director --generar          genera una clave aleatoria y la MUESTRA una sola vez
 *   usuarios:clave-director                    la pide por teclado (sin eco) dos veces
 *   usuarios:clave-director --stdin            la lee de la primera línea de la entrada estándar
 *   usuarios:clave-director --verificar        comprueba una clave (teclado o --stdin) sin cambiarla
 *   --por=Guilmer                              usuario que queda como "actualizado por"
 */
class UsuariosClaveDirector extends Command
{
    protected $signature = 'usuarios:clave-director
        {--generar : Genera una clave aleatoria y la muestra una sola vez}
        {--stdin : Lee la clave de la primera línea de la entrada estándar}
        {--verificar : Comprueba una clave en vez de cambiarla}
        {--por= : Usuario (username) que la establece}';

    protected $description = 'Crea, cambia o comprueba la clave de seguridad del gerente para dar o quitar el rol Director';

    public function handle(): int
    {
        if ($this->option('verificar')) {
            return $this->verificar();
        }

        $usuarioId = $this->usuarioId();
        if ($usuarioId === false) {
            return self::FAILURE;
        }

        $generada = (bool) $this->option('generar');
        $clave = $generada ? ClaveSeguridad::generar() : $this->leerClave(confirmar: true);
        if ($clave === null) {
            return self::FAILURE;
        }
        if (mb_strlen($clave) < ClaveSeguridad::LARGO_MINIMO) {
            $this->error('La clave debe tener al menos '.ClaveSeguridad::LARGO_MINIMO.' caracteres.');

            return self::FAILURE;
        }

        $existia = ClaveSeguridad::definida(ClaveSeguridad::ROL_DIRECTOR);
        ClaveSeguridad::establecer(ClaveSeguridad::ROL_DIRECTOR, $clave, $usuarioId);

        $this->info($existia ? '✓ Clave de seguridad para el rol Director REEMPLAZADA.' : '✓ Clave de seguridad para el rol Director creada.');
        if ($generada) {
            $this->newLine();
            $this->line('   Clave (se muestra UNA sola vez; entrégasela al gerente):');
            $this->newLine();
            $this->line("      {$clave}");
            $this->newLine();
            $this->warn('   No queda guardada en claro en ningún lado: si se pierde, hay que generar otra.');
        }

        return self::SUCCESS;
    }

    private function verificar(): int
    {
        if (! ClaveSeguridad::definida(ClaveSeguridad::ROL_DIRECTOR)) {
            $this->error('No hay clave de seguridad configurada.');

            return self::FAILURE;
        }
        $clave = $this->leerClave(confirmar: false);
        if ($clave === null) {
            return self::FAILURE;
        }
        if (ClaveSeguridad::verificar(ClaveSeguridad::ROL_DIRECTOR, $clave)) {
            $this->info('✓ La clave es correcta.');

            return self::SUCCESS;
        }
        $this->error('✗ Clave incorrecta.');

        return self::FAILURE;
    }

    /** La clave desde --stdin (primera línea) o por teclado sin eco. */
    private function leerClave(bool $confirmar): ?string
    {
        if ($this->option('stdin')) {
            $linea = fgets(STDIN);
            $clave = is_string($linea) ? trim($linea) : '';
            if ($clave === '') {
                $this->error('No llegó ninguna clave por la entrada estándar.');

                return null;
            }

            return $clave;
        }

        $clave = trim((string) $this->secret('Clave de seguridad'));
        if ($clave === '') {
            $this->error('La clave no puede estar vacía.');

            return null;
        }
        if ($confirmar && trim((string) $this->secret('Repite la clave')) !== $clave) {
            $this->error('Las claves no coinciden.');

            return null;
        }

        return $clave;
    }

    /** Id del usuario de --por (null si no se dio; false si no existe). */
    private function usuarioId(): int|false|null
    {
        $por = $this->option('por');
        if ($por === null || $por === '') {
            return null;
        }
        $id = User::where('username', $por)->value('id');
        if ($id === null) {
            $this->error("No existe el usuario {$por}.");

            return false;
        }

        return (int) $id;
    }
}
