<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Orquestador de la carga inicial del Área Legal: ejecuta los 6 importadores
 * del módulo sobre la carpeta "Proyecto. Sistema Area Legal", EN ESTE ORDEN
 * (las dependencias importan — los vehículos deben existir antes de importar
 * garantías y papeletas):
 *
 *   1. flota       → 3. Tramites administrativos. Papeletas/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx
 *   2. garantias   → 1. Constitucion de Garantia Mobiliaria SIGM/B. 2026. Registro de garantías constituidas - SIGM.xlsx
 *   3. notaria     → 1. Constitucion de Garantia Mobiliaria SIGM/D. Notaria Hinojosa. Registro de documentos pendientes.xlsx
 *   4. expedientes → 2. Expedientes Judiciales/A. Registro de seguimiento. Expedientes Judiciales.xlsx
 *   5. papeletas   → mismo archivo que flota
 *   6. caja        → Cuadre de caja Area Legal. Ingreso y egreso.xlsx
 *
 * Cada importador es IDEMPOTENTE (re-ejecutable sin duplicar registros), por
 * lo que este comando puede correrse varias veces sobre la misma carpeta; con
 * --dry-run se propaga la simulación a cada importador y nada queda en BD.
 *
 * Por cada paso: si el Excel no existe se advierte y se salta (contabilizado
 * como ERROR en el resumen); si existe se invoca el importador y se captura
 * su exit code. Al final se muestra una tabla resumen (paso, archivo
 * encontrado, resultado) y el exit code global es 1 si algún paso falló
 * (archivo faltante o importador con error). La numeración "Paso N/6" es
 * SIEMPRE la posición dentro del orden completo, aunque --solo filtre pasos.
 *
 * Uso:
 *   php artisan legal:poblar "ruta/a/Proyecto. Sistema Area Legal" --dry-run
 *   php artisan legal:poblar "ruta/a/Proyecto. Sistema Area Legal"
 *   php artisan legal:poblar "ruta/a/Proyecto. Sistema Area Legal" --solo=flota --solo=caja
 */
class LegalPoblar extends Command
{
    protected $signature = 'legal:poblar
        {carpeta : Ruta de la carpeta "Proyecto. Sistema Area Legal" que contiene los Excel del área}
        {--dry-run : Propaga --dry-run a cada importador (simulación, nada queda en BD)}
        {--solo=* : Ejecuta solo estos pasos (repetible): flota, garantias, notaria, expedientes, papeletas, caja}';

    protected $description = 'Ejecuta en orden los 6 importadores del Área Legal (flota, garantías, notaría, expedientes, papeletas y caja) sobre la carpeta "Proyecto. Sistema Area Legal"';

    /**
     * Pasos en ORDEN de ejecución: clave => [comando artisan, ruta relativa
     * del Excel dentro de la carpeta]. Flota va primero porque garantías y
     * papeletas dependen de que los vehículos ya existan.
     */
    private const PASOS = [
        'flota' => ['legal:importar-flota', '3. Tramites administrativos. Papeletas/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx'],
        'garantias' => ['legal:importar-garantias', '1. Constitucion de Garantia Mobiliaria SIGM/B. 2026. Registro de garantías constituidas - SIGM.xlsx'],
        'notaria' => ['legal:importar-notaria', '1. Constitucion de Garantia Mobiliaria SIGM/D. Notaria Hinojosa. Registro de documentos pendientes.xlsx'],
        'expedientes' => ['legal:importar-expedientes', '2. Expedientes Judiciales/A. Registro de seguimiento. Expedientes Judiciales.xlsx'],
        'papeletas' => ['legal:importar-papeletas', '3. Tramites administrativos. Papeletas/1. Relacion de Vehiculos G-IH-CH (13-3-24).xlsx'],
        'caja' => ['legal:importar-caja', 'Cuadre de caja Area Legal. Ingreso y egreso.xlsx'],
    ];

    public function handle(): int
    {
        $carpeta = rtrim($this->argument('carpeta'), '/');
        $dryRun = (bool) $this->option('dry-run');
        $solo = $this->option('solo');

        $desconocidos = array_diff($solo, array_keys(self::PASOS));
        if ($desconocidos !== []) {
            $this->error('Pasos desconocidos en --solo: '.implode(', ', $desconocidos)
                .'. Válidos: '.implode(', ', array_keys(self::PASOS)).'.');

            return self::INVALID;
        }

        // --solo filtra pasos pero SIEMPRE se respeta el orden de PASOS.
        $ejecutar = $solo === [] ? array_keys(self::PASOS) : array_keys(array_intersect_key(self::PASOS, array_flip($solo)));

        if ($dryRun) {
            $this->info('Modo simulación (--dry-run): se propaga a cada importador, nada queda en BD.');
        }

        $resumen = [];
        $fallo = false;
        $total = count(self::PASOS);
        $numero = 0;

        foreach (self::PASOS as $clave => [$comando, $relativa]) {
            $numero++;
            if (! in_array($clave, $ejecutar, true)) {
                continue;
            }

            $archivo = $carpeta.'/'.$relativa;

            $this->newLine();
            $this->line("── Paso {$numero}/{$total}: {$clave} ──");

            if (! is_file($archivo)) {
                $this->warn("Archivo no encontrado, paso omitido: {$archivo}");
                $resumen[] = [$clave, 'No', 'ERROR (archivo faltante)'];
                $fallo = true;

                continue;
            }

            $exit = $this->call($comando, ['archivo' => $archivo, '--dry-run' => $dryRun]);

            $resumen[] = [$clave, 'Sí', $exit === self::SUCCESS ? 'OK' : "ERROR (exit {$exit})"];
            $fallo = $fallo || $exit !== self::SUCCESS;
        }

        $this->newLine();
        $this->table(['Paso', 'Archivo encontrado', 'Resultado'], $resumen);

        if ($fallo) {
            $this->error('Uno o más pasos fallaron (ver tabla).');

            return self::FAILURE;
        }

        $this->info('Todos los pasos terminaron OK.');

        return self::SUCCESS;
    }
}
