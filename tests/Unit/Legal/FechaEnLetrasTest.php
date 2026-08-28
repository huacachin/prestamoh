<?php

namespace Tests\Unit\Legal;

use App\Support\Legal\FechaEnLetras;
use PHPUnit\Framework\TestCase;

/**
 * Fechas de documento legal: la forma corta ("24 de agosto de 2026") y la
 * larga notarial ("a los veinticuatro días del mes de agosto del año dos mil
 * veintiséis"), con el día 1 como "al primer día" y las tildes de dieciséis /
 * veintiséis que NumerosEnLetras (mayúsculas bancarias) no trae.
 */
class FechaEnLetrasTest extends TestCase
{
    public function test_simple_formato_corto(): void
    {
        $this->assertSame('24 de agosto de 2026', FechaEnLetras::simple('2026-08-24'));
    }

    public function test_larga_escribe_dia_y_anio_en_letras(): void
    {
        $larga = FechaEnLetras::larga('2026-08-24');

        $this->assertStringContainsString('veinticuatro días', $larga);
        $this->assertStringContainsString('dos mil veintiséis', $larga);
        $this->assertSame(
            'a los veinticuatro días del mes de agosto del año dos mil veintiséis',
            $larga
        );
    }

    public function test_larga_dia_uno_usa_al_primer_dia(): void
    {
        $this->assertStringContainsString('al primer día', FechaEnLetras::larga('2026-06-01'));
    }

    public function test_larga_acentua_dieciseis(): void
    {
        $this->assertStringContainsString('dieciséis', FechaEnLetras::larga('2026-01-16'));
    }
}
