<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tipografía de los documentos legales (05/09): Bookman Old Style 6.5pt,
 * la de las maestras del área legal.
 *
 * El PDF debe EMBEBER la fuente (ruta local dentro del chroot de dompdf) y
 * la previa servirla por URL, para que la pantalla se vea igual que el
 * papel aunque la máquina no tenga Office instalado.
 */
class DocumentosTipografiaTest extends TestCase
{
    private function css(string $medio): string
    {
        return view('documentos.pdf.estilos', ['medio' => $medio])->render();
    }

    public function test_el_cuerpo_va_en_bookman_a_6_5_puntos(): void
    {
        $css = $this->css('pdf');
        $this->assertStringContainsString('font-family: "Bookman Old Style"', $css);
        $this->assertStringContainsString('font-size: 6.5pt', $css);
        // Respaldo si la fuente no resuelve: nunca quedarse sin serif.
        $this->assertStringContainsString('"DejaVu Serif", serif', $css);
    }

    public function test_el_pdf_apunta_a_las_cuatro_variantes_por_ruta_local(): void
    {
        $css = $this->css('pdf');
        foreach (['BookmanOldStyle', 'BookmanOldStyleBold', 'BookmanOldStyleItalic', 'BookmanOldStyleBoldItalic'] as $ttf) {
            $this->assertStringContainsString($ttf.'.ttf', $css);
            $this->assertFileExists(public_path("fonts/bookman/{$ttf}.ttf"), 'el TTF debe viajar en el repo');
        }
        // Ruta de archivo (no URL): dompdf solo resuelve dentro de su chroot.
        $this->assertStringContainsString(public_path('fonts/bookman'), $css);
    }

    public function test_la_previa_sirve_la_fuente_por_url(): void
    {
        $css = $this->css('previa');
        $this->assertStringContainsString('/fonts/bookman/BookmanOldStyle.ttf', $css);
        // Sin regla @page: en la previa el margen va como padding del body.
        $this->assertStringNotContainsString('@page {', $css);
        $this->assertStringContainsString('http', $css, 'la previa la sirve por URL, no por ruta de disco');
    }

    /** Word no embebe: declara la familia y usa la instalada (viene con Office). */
    public function test_word_declara_la_familia_para_la_fuente_local(): void
    {
        $css = $this->css('word');
        $this->assertStringContainsString('font-family: "Bookman Old Style"', $css);
        $this->assertStringNotContainsString('@page {', $css, 'en Word el margen lo fija DocResponse');
    }
}
