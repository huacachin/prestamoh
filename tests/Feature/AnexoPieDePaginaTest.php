<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Qué lleva cada documento al pie (15/09):
 *   contrato → NADA (las maestras en papel no lo llevan)
 *   Anexo 1  → NADA (cabe en una hoja; el maestro del área tampoco lo trae)
 *   Anexo 2  → las FORMAS DE PAGO, como sus 15 maestros; ya no la numeración
 * Se fija porque el estilo .pie-pagina vive en la hoja COMPARTIDA: basta con
 * pegar el div de vuelta para que reaparezca en el documento equivocado.
 */
class AnexoPieDePaginaTest extends TestCase
{
    private function plantilla(string $nombre): string
    {
        return file_get_contents(resource_path("views/documentos/pdf/{$nombre}.blade.php"));
    }

    public function test_el_anexo_1_ya_no_lleva_pie_de_pagina(): void
    {
        $this->assertStringNotContainsString('class="pie-pagina"', $this->plantilla('anexo1'));
    }

    public function test_el_contrato_sigue_sin_pie_de_pagina(): void
    {
        $this->assertStringNotContainsString('class="pie-pagina"', $this->plantilla('contrato'));
    }

    public function test_el_anexo_2_lleva_las_formas_de_pago_al_pie(): void
    {
        $anexo2 = $this->plantilla('anexo2');
        $this->assertStringContainsString('pie-formas', $anexo2);
        $this->assertStringContainsString("config('documentos.formas_pago')", $anexo2);
        // Ya no numera páginas: el maestro no lo hace.
        $this->assertStringNotContainsString('<span class="num">', $anexo2);
    }

    /** El estilo sigue existiendo (lo usa el Anexo 2) y solo aplica al PDF. */
    public function test_el_estilo_del_pie_solo_aplica_al_pdf(): void
    {
        $estilos = $this->plantilla('estilos');
        $this->assertStringContainsString('.pie-pagina', $estilos);
        $this->assertStringContainsString('.pie-pagina { display: none; }', $estilos);
    }
}
