<?php

namespace Tests\Unit\Legal;

use App\Support\Legal\Ordinales;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Numeración ordinal de cláusulas por lista de claves activas: al quitar una
 * cláusula opcional (GPS) todas las posteriores se renumeran solas — la
 * corrección NOVENO→DÉCIMO SÉPTIMO que hoy se hace a mano en las plantillas
 * Word. Citar una cláusula que no está en el contrato debe fallar fuerte,
 * nunca imprimir un ordinal equivocado.
 */
class OrdinalesTest extends TestCase
{
    public function test_ordinales_del_uno_al_nueve(): void
    {
        $esperados = [
            1 => 'PRIMERO', 2 => 'SEGUNDO', 3 => 'TERCERO', 4 => 'CUARTO', 5 => 'QUINTO',
            6 => 'SEXTO', 7 => 'SÉPTIMO', 8 => 'OCTAVO', 9 => 'NOVENO',
        ];

        foreach ($esperados as $n => $texto) {
            $this->assertSame($texto, Ordinales::ordinal($n), "ordinal({$n})");
        }
    }

    public function test_ordinales_compuestos(): void
    {
        $this->assertSame('DÉCIMO', Ordinales::ordinal(10));
        $this->assertSame('DÉCIMO SÉPTIMO', Ordinales::ordinal(17));
        $this->assertSame('VIGÉSIMO', Ordinales::ordinal(20));
        $this->assertSame('VIGÉSIMO PRIMERO', Ordinales::ordinal(21));
    }

    public function test_ordinal_menor_a_uno_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Ordinales::ordinal(0);
    }

    public function test_ordinal_mayor_a_treinta_y_nueve_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Ordinales::ordinal(40);
    }

    public function test_quitar_gps_renumera_las_clausulas_posteriores(): void
    {
        $conGps = [
            'antecedentes', 'objeto', 'gravamen', 'obligacion', 'plazo',
            'custodia', 'seguro', 'incumplimiento', 'gps', 'ejecucion', 'domicilio',
        ];
        $sinGps = array_values(array_diff($conGps, ['gps']));

        $contratoConGps = new Ordinales($conGps);
        $contratoSinGps = new Ordinales($sinGps);

        $this->assertSame('NOVENO', $contratoConGps->de('gps'));
        $this->assertSame('DÉCIMO', $contratoConGps->de('ejecucion'));

        // Sin GPS, la cláusula que le seguía baja EXACTAMENTE un ordinal
        $this->assertSame('NOVENO', $contratoSinGps->de('ejecucion'));
        $this->assertSame(
            $contratoConGps->numero('ejecucion') - 1,
            $contratoSinGps->numero('ejecucion')
        );
        $this->assertSame(
            $contratoConGps->numero('domicilio') - 1,
            $contratoSinGps->numero('domicilio')
        );
        // Las anteriores a GPS no se mueven
        $this->assertSame($contratoConGps->numero('plazo'), $contratoSinGps->numero('plazo'));
    }

    public function test_citar_clausula_inactiva_lanza_excepcion(): void
    {
        $ord = new Ordinales(['antecedentes', 'objeto']);

        $this->expectException(InvalidArgumentException::class);
        $ord->de('gps');
    }

    public function test_tiene_indica_si_la_clausula_esta_activa(): void
    {
        $ord = new Ordinales(['antecedentes', 'gps']);

        $this->assertTrue($ord->tiene('gps'));
        $this->assertFalse($ord->tiene('seguro'));
    }

    public function test_claves_conserva_el_orden_de_entrada(): void
    {
        $claves = ['antecedentes', 'objeto', 'gravamen', 'ejecucion'];

        $this->assertSame($claves, (new Ordinales($claves))->claves());
    }
}
