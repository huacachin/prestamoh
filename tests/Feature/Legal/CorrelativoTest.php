<?php

namespace Tests\Feature\Legal;

use App\Support\Correlativo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correlativos atómicos de la tabla `correlativos` (lectura + avance en una
 * transacción con lockForUpdate): el tipo 'Contrato' nace aquí y no puede
 * repetir números, a diferencia del patrón legacy que ya produjo expedientes
 * duplicados.
 */
class CorrelativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_fila_y_devuelve_uno_y_luego_dos(): void
    {
        $this->assertDatabaseMissing('correlativos', ['tipo' => 'Contrato']);

        $this->assertSame(1, Correlativo::siguiente('Contrato'));
        $this->assertDatabaseHas('correlativos', ['tipo' => 'Contrato', 'correl' => 1]);

        $this->assertSame(2, Correlativo::siguiente('Contrato'));
        $this->assertDatabaseHas('correlativos', ['tipo' => 'Contrato', 'correl' => 2]);
    }

    public function test_llamadas_consecutivas_nunca_repiten(): void
    {
        $obtenidos = [];
        for ($i = 0; $i < 10; $i++) {
            $obtenidos[] = Correlativo::siguiente('Contrato');
        }

        // Secuencia estricta 1..10: sin huecos y sin repetidos
        $this->assertSame(range(1, 10), $obtenidos);
        $this->assertSame(count($obtenidos), count(array_unique($obtenidos)));
    }
}
