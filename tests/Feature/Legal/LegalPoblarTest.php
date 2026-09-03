<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * legal:poblar orquesta los 6 importadores del Área Legal en orden de
 * dependencias (flota antes que garantías y papeletas). Aquí NO se importan
 * Excel reales: se apunta a una carpeta inexistente para verificar el
 * contrato del orquestador — cada paso reporta su archivo faltante (warn y
 * salto, sin invocar el importador), --solo filtra pasos conservando la
 * numeración original "Paso N/6", y el exit code global es 1 cuando algún
 * paso falla (un archivo faltante cuenta como fallo, así que con TODOS los
 * archivos faltantes el comando termina con exit 1).
 */
class LegalPoblarTest extends TestCase
{
    use RefreshDatabase;

    public function test_carpeta_inexistente_falla(): void
    {
        // Ningún Excel existe → los 6 pasos reportan archivo faltante y el
        // exit code global es 1 (archivo faltante = fallo contabilizado).
        $this->artisan('legal:poblar', ['carpeta' => '/tmp/no-existe-xyz'])
            ->expectsOutputToContain('Paso 1/6: flota')
            ->expectsOutputToContain('Paso 2/6: garantias')
            ->expectsOutputToContain('Paso 3/6: notaria')
            ->expectsOutputToContain('Paso 4/6: expedientes')
            ->expectsOutputToContain('Paso 5/6: papeletas')
            ->expectsOutputToContain('Paso 6/6: caja')
            ->expectsOutputToContain('Archivo no encontrado')
            ->assertExitCode(1);
    }

    public function test_solo_filtra_pasos(): void
    {
        // --solo=caja: solo se menciona el paso de caja (con su numeración
        // original 6/6); los demás pasos ni se anuncian ni salen en la tabla.
        $this->artisan('legal:poblar', ['carpeta' => '/tmp/no-existe-xyz', '--solo' => ['caja']])
            ->expectsOutputToContain('Paso 6/6: caja')
            ->doesntExpectOutputToContain('flota')
            ->doesntExpectOutputToContain('garantias')
            ->doesntExpectOutputToContain('notaria')
            ->doesntExpectOutputToContain('expedientes')
            ->doesntExpectOutputToContain('papeletas')
            ->assertExitCode(1);
    }
}
