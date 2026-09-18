<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Qué lleva cada documento al pie:
 *   contrato → NADA (15/09: las maestras en papel no lo llevan)
 *   Anexo 1  → NADA (15/09: cabe en una hoja; su maestro tampoco lo trae)
 *   Anexo 2  → NADA (18/09, pedido de Antony: llevaba las formas de pago y se
 *               retiran — son las cuentas a las que PAGA el cliente, y esto
 *               es la constancia de un desembolso ya entregado)
 *
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

    public function test_el_anexo_2_ya_no_lleva_pie_de_pagina(): void
    {
        $anexo2 = $this->plantilla('anexo2');
        $this->assertStringNotContainsString('pie-pagina', $anexo2);
        $this->assertStringNotContainsString('pie-formas', $anexo2);
        $this->assertStringNotContainsString("config('documentos.formas_pago')", $anexo2);
        // Ya no numera páginas: el maestro no lo hace.
        $this->assertStringNotContainsString('<span class="num">', $anexo2);
    }

    /** Ningún documento del cliente lleva pie: el div no puede estar en ninguna plantilla. */
    public function test_ninguna_plantilla_emite_el_pie(): void
    {
        foreach (['contrato', 'anexo1', 'anexo2'] as $doc) {
            $this->assertStringNotContainsString('class="pie-pagina', $this->plantilla($doc), "{$doc} no debe llevar pie");
        }
    }

    /**
     * Y el estilo se fue con el div: dejarlo suelto en la hoja COMPARTIDA es
     * lo que hace fácil que el pie reaparezca en el documento equivocado.
     * (El contrato del módulo legal sí numera páginas, pero usa su propia
     * hoja, legal/pdf/estilos, y no esta.)
     */
    public function test_la_hoja_compartida_ya_no_estila_ningun_pie(): void
    {
        $estilos = $this->plantilla('estilos');

        $this->assertStringNotContainsString('.pie-pagina', $estilos);
        $this->assertStringNotContainsString('.pie-formas', $estilos);
        $this->assertStringNotContainsString('counter(page)', $estilos);
    }
}
