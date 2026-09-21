<?php

namespace Tests\Feature;

use App\Support\Documentos\Enfasis;
use Tests\TestCase;

/**
 * Negritas y subrayado del contrato (05/09): las maestras resaltan los
 * TÉRMINOS DEFINIDOS y las etiquetas que abren párrafo, y el título va
 * negrita + subrayado.
 */
class ContratoEnfasisTest extends TestCase
{
    public function test_resalta_los_terminos_definidos(): void
    {
        $html = '<p>OTORGADO POR EL ACREEDOR EN FAVOR DE EL DEUDOR.</p>';
        $this->assertSame(
            '<p>OTORGADO POR <b>EL ACREEDOR</b> EN FAVOR DE <b>EL DEUDOR</b>.</p>',
            Enfasis::aplicar($html)
        );
    }

    public function test_resalta_las_flexiones_de_genero(): void
    {
        foreach (['LOS DEUDORES', 'LA DEUDORA', 'LAS DEUDORAS', 'EL CONSTITUYENTE', 'LOS CONSTITUYENTES'] as $termino) {
            $this->assertStringContainsString(
                "<b>{$termino}</b>",
                Enfasis::aplicar("<p>ASÍ {$termino} DECLARAN.</p>"),
                "debe resaltar {$termino}"
            );
        }
    }

    /** La etiqueta se flexiona en tiempo de render: va por patrón, no por literal. */
    public function test_resalta_la_etiqueta_completa_y_no_sus_partes(): void
    {
        foreach ([
            'DATOS DEL CONSTITUYENTE Y EL DEUDOR:',
            'DATOS DE LOS CONSTITUYENTES Y LOS DEUDORES:',
            'DATOS DE LA CONSTITUYENTE Y LA DEUDORA:',
            'DATOS DE LAS CONSTITUYENTES Y LAS DEUDORAS:',
        ] as $etiqueta) {
            $salida = Enfasis::aplicar("<p>{$etiqueta} JUAN PEREZ</p>");
            $this->assertStringContainsString("<b>{$etiqueta}</b>", $salida, "etiqueta entera: {$etiqueta}");
            // Y no partida en dos negritas.
            $this->assertStringNotContainsString('</b> Y <b>', $salida);
        }
    }

    /**
     * Con dos bienes la etiqueta del valor va numerada (18/09) y tiene que
     * salir en negrita ENTERA, número incluido —Antony, 21/09: "falta poner
     * negrita en Valor del bien afectado 1: y 2:"—. La sin número sigue igual.
     */
    public function test_resalta_la_etiqueta_del_valor_numerada(): void
    {
        foreach (['VALOR DEL BIEN AFECTADO 1:', 'VALOR DEL BIEN AFECTADO 2:', 'VALOR DEL BIEN AFECTADO:'] as $etiqueta) {
            $salida = Enfasis::aplicar("<p>{$etiqueta} S/ 15,000.00 (QUINCE MIL CON 00/100 SOLES)</p>");
            $this->assertStringContainsString("<b>{$etiqueta}</b> S/", $salida, $etiqueta);
        }
    }

    public function test_no_toca_los_atributos_html(): void
    {
        // "EL DEUDOR" dentro de un atributo NO debe recibir etiquetas.
        $html = '<div title="EL DEUDOR"><p>EL DEUDOR FIRMA</p></div>';
        $salida = Enfasis::aplicar($html);
        $this->assertStringContainsString('title="EL DEUDOR"', $salida);
        $this->assertStringContainsString('<p><b>EL DEUDOR</b> FIRMA</p>', $salida);
    }

    public function test_el_titulo_va_negrita_y_subrayado(): void
    {
        $css = view('documentos.pdf.estilos', ['medio' => 'pdf'])->render();
        $bloque = substr($css, strpos($css, '.titulo-contrato'), 200);
        $this->assertStringContainsString('font-weight: bold', $bloque);
        $this->assertStringContainsString('text-decoration: underline', $bloque);
    }
}
