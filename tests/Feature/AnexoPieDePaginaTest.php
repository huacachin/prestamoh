<?php

namespace Tests\Feature;

use App\Services\Documentos\RenderDocumento;
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

    /**
     * En WORD el pie vive DENTRO del margen inferior de la página, así que
     * ese margen tiene que ser más alto que el pie; si no, Word lo dibuja
     * encima del cronograma. El 18/09, al bajar los márgenes del anexo, los
     * dos números quedaron en 1,2 cm y el pie se montó sobre el contenido —y
     * ningún test lo vio, porque todos miraban el PDF, donde el pie va
     * anclado y no reserva alto.
     */
    public function test_en_word_el_margen_inferior_del_anexo_1_deja_sitio_al_pie(): void
    {
        $html = RenderDocumento::html(
            $this->snapshotMinimo(), 'anexo1', 'word'
        );

        preg_match('/@page\s+WordSection1\s*\{[^}]*margin:\s*[\d.]+cm\s+[\d.]+cm\s+([\d.]+)cm/', $html, $m);
        $this->assertNotEmpty($m, 'no se pudo leer el margen inferior de la sección de Word');
        preg_match('/mso-footer-margin:\s*([\d.]+)cm/', $html, $f);
        $this->assertNotEmpty($f, 'el Anexo 1 debe declarar mso-footer-margin');

        $inferior = (float) $m[1];
        $delPapelAlPie = (float) $f[1];
        // El pie mide ~1,4 cm: raya, aire y dos renglones.
        $this->assertGreaterThanOrEqual($delPapelAlPie + 1.4, $inferior,
            "el margen inferior de Word ({$inferior} cm) no deja sitio al pie, "
            ."que arranca a {$delPapelAlPie} cm del papel: Word lo dibujará sobre el cronograma");
    }

    /** Lo mínimo que la plantilla necesita para renderizar. */
    private function snapshotMinimo(): array
    {
        $cliente = [
            'nombre' => 'CLIENTE DE PRUEBA', 'documento_tipo' => 'DNI', 'documento' => '12345678',
            'domicilio' => 'CALLE FALSA 123', 'celular' => '999888777', 'correo' => 'c@example.com',
        ];

        return [
            'marca' => 'HUACACHIN', 'fecha' => '18/09/2026',
            'cliente' => $cliente, 'clientes' => [$cliente],
            'vehiculo' => null, 'vehiculos' => [],
            'credito' => [
                'numero' => 1, 'correlativo' => '2026-001', 'moneda' => 'SOLES', 'monto' => 10000.0,
                'frecuencia' => 'SEMANAL', 'cuotas' => 4, 'cuota' => 500.0, 'plazo' => '4 semanas',
                'fecha_inicio' => '18/09/2026', 'tim' => '5%',
            ],
            'cronograma' => [
                'filas' => array_map(fn ($i) => ['n' => $i, 'fecha' => '25/09/2026', 'monto' => 500.0], range(1, 4)),
                'total' => 2000.0,
            ],
        ];
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
