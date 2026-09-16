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
        // Word no descarga fuentes web al abrir un .doc: las cuatro @font-face
        // eran inútiles y podían disparar el aviso de contenido externo.
        $this->assertStringNotContainsString('@font-face', $css);
    }

    /**
     * 16/09 — el margen de Word se declara AQUÍ y ya no en DocResponse.
     *
     * Antes lo fijaba DocResponse con un valor único, y el CONTRATO —que usa
     * márgenes más apretados para entrar en 5 hojas— salía en Word con los
     * del anexo: el texto entraba más angosto y se le corrían todos los
     * saltos de línea y de página. estilos.blade.php es el único que sabe si
     * el documento es compacto, así que el @page tiene que salir de aquí.
     */
    public function test_word_declara_el_margen_que_le_toca_a_cada_documento(): void
    {
        $anexo = view('documentos.pdf.estilos', ['medio' => 'word'])->render();
        $contrato = view('documentos.pdf.estilos', ['medio' => 'word', 'compacto' => true])->render();

        $this->assertStringContainsString('@page {', $anexo, 'Word necesita la caja de página');
        $this->assertStringContainsString('@page {', $contrato);

        // A4 en puntos y no la palabra "A4": en una instalación configurada
        // en Carta la palabra se ignora y el Anexo 1 se parte en dos hojas.
        $this->assertStringContainsString('595.28pt 841.89pt', $anexo);

        $margen = function (string $css): string {
            preg_match('/@page\s*\{([^}]*)\}/', $css, $m);

            return trim($m[1] ?? '');
        };

        $this->assertNotSame(
            $margen($anexo), $margen($contrato),
            'el contrato usa márgenes apretados: en Word no pueden ser los del anexo'
        );
    }
}
