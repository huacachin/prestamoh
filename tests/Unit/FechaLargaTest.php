<?php

namespace Tests\Unit;

use App\Support\FechaLarga;
use Carbon\Carbon;
use Tests\TestCase;

/** 25/09/2026: cabecera de día de Caja General con día y mes en mayúscula inicial y "del" antes del año, como el legacy. */
class FechaLargaTest extends TestCase
{
    public function test_formato_del_legacy_con_mayusculas_iniciales(): void
    {
        $this->assertSame('Viernes 25 de Septiembre del 2026', FechaLarga::etiqueta('2026-09-25'));
        $this->assertSame('Miércoles 04 de Febrero del 2026', FechaLarga::etiqueta('2026-02-04'));
        $this->assertSame('Sábado 01 de Agosto del 2026', FechaLarga::etiqueta('2026-08-01'));
        $this->assertSame('Domingo 02 de Agosto del 2026', FechaLarga::etiqueta(Carbon::parse('2026-08-02')));
    }
}
