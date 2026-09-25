<?php

namespace Tests\Unit;

use App\Support\VehiculoCatalogos;
use PHPUnit\Framework\TestCase;

class VehiculoCatalogosTest extends TestCase
{
    /** 26/09: PICK UP faltaba en el alta de cliente y en la placa. */
    public function test_el_catalogo_de_carrocerias_incluye_pick_up(): void
    {
        $this->assertContains('PICK UP', VehiculoCatalogos::CARROCERIAS);
    }

    /** Un valor guardado fuera del catálogo (API de placa, histórico) se conserva como opción. */
    public function test_para_valor_conserva_lo_guardado_fuera_del_catalogo(): void
    {
        $opciones = VehiculoCatalogos::paraValor(VehiculoCatalogos::CARROCERIAS, 'METROPOLITANO');
        $this->assertSame('METROPOLITANO', $opciones[0]);

        // Lo que ya está en el catálogo no se duplica, aunque venga en otra caja.
        $opciones = VehiculoCatalogos::paraValor(VehiculoCatalogos::CARROCERIAS, 'pick up');
        $this->assertSame(1, count(array_filter($opciones, fn ($o) => mb_strtoupper($o) === 'PICK UP')));
    }
}
