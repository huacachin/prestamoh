<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use App\Services\Documentos\GeneradorAnexo2;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\RenderDocumento;
use App\Support\DocResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El .doc tiene que llegarle a Word como UN documento HTML bien formado.
 *
 * 16/09 — POR QUÉ EXISTE ESTE TEST. Antony reportó "todos los words están
 * mal, deformes, no son fieles al pdf". La causa: DocResponse envolvía el
 * HTML de la vista —que YA es un documento completo— en otro <html><head>…
 * </head><body>. Quedaban dos documentos anidados y el <style> del documento
 * caía DENTRO del <body> exterior; el importador de Word se queda con el
 * <head> de afuera y descarta el de adentro, así que se perdía la hoja de
 * estilos ENTERA.
 *
 * Los tres tests de documentos que ya existían (DocumentoAnexo1Test y
 * compañía) pedían el Word y comprobaban que respondiera 200 y que el texto
 * apareciera. Eso pasaba con el documento roto. Este test mira la ESTRUCTURA,
 * que es lo que Word necesita y lo que nadie estaba mirando.
 */
class WordFidelidadTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'word-fidelidad']));

        $this->client = Client::create([
            'expediente' => '9902', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE',
            'apellido_mat' => 'MAMANI', 'tipo_documento' => 'DNI', 'documento' => '46781901',
            'sexo' => 'F', 'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE',
            'estado_civil' => 'casado', 'email' => 'rosa.word@example.com', 'celular1' => '999111222',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE',
            'provincia' => 'LIMA', 'departamento' => 'LIMA', 'status' => 'active',
        ]);

        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-01',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 500, 'situacion' => 'Activo', 'estado' => 1,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-08')->addWeeks($n - 1),
                'importe_cuota' => 1375, 'importe_interes' => 125, 'pagado' => false,
            ]);
        }

        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'WRD111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-W1', 'nro_motor' => 'MOT-W1', 'valor' => 15000,
        ]);
    }

    /** El .doc tal cual lo sirve DocumentoClienteController::word(). */
    private function doc(string $tipo): string
    {
        $snapshot = match ($tipo) {
            'anexo1' => GeneradorAnexo1::construirSnapshot($this->client, $this->credit, $this->vehiculo),
            'anexo2' => GeneradorAnexo2::construirSnapshot($this->client, $this->credit, [
                'transcripcion' => 'OPERACION EXITOSA; MONTO S/ 5,000.00',
                'monto' => 5000,
            ]),
            'contrato' => GeneradorContrato::construirSnapshot(
                $this->client, $this->credit, [$this->vehiculo->id], 'a1', $this->datosContrato()
            ),
        };

        return DocResponse::desdeHtml(
            RenderDocumento::html($snapshot, $tipo, 'word'), "{$tipo}.doc"
        )->getContent();
    }

    private function datosContrato(): array
    {
        return [
            'fecha' => '2026-09-01',
            'banco' => 'bcp',
            'deudores' => [[
                'sexo' => 'F', 'nombre' => 'ROSA LINDA QUISPE MAMANI', 'dni' => '46781901',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADA',
                'domicilio' => 'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'correo' => 'ROSA.WORD@EXAMPLE.COM',
            ]],
            'bienes' => [$this->vehiculo->id => [
                'es_futuro' => false, 'fecha_acta' => '2026-05-04', 'kardex' => '0373-2026',
                'notario' => 'JULIO BLAS', 'estado_registral' => 'EN TRÁMITE DE INSCRIPCIÓN',
            ]],
        ];
    }

    /** @return string[] */
    public static function tipos(): array
    {
        return [['anexo1'], ['anexo2'], ['contrato']];
    }

    /**
     * ESTE es el test que faltaba: un solo documento, no dos anidados.
     */
    #[DataProvider('tipos')]
    public function test_el_word_es_un_solo_documento_html(string $tipo): void
    {
        $doc = $this->doc($tipo);

        $this->assertSame(1, substr_count($doc, '<html'), "{$tipo}: debe haber UNA etiqueta <html>");
        $this->assertSame(1, substr_count($doc, '<head'), "{$tipo}: debe haber UN <head>");
        $this->assertSame(1, substr_count($doc, '<body'), "{$tipo}: debe haber UN <body>");
        $this->assertSame(1, substr_count($doc, '<!DOCTYPE'), "{$tipo}: debe haber UN <!DOCTYPE>");
    }

    /**
     * Si el <style> queda fuera del <head>, Word lo descarta y el documento
     * sale sin NINGÚN estilo. Ese era exactamente el síntoma reportado.
     */
    #[DataProvider('tipos')]
    public function test_los_estilos_van_dentro_del_head(string $tipo): void
    {
        $doc = $this->doc($tipo);

        $head = strpos($doc, '<head');
        $finHead = strpos($doc, '</head>');
        $style = strpos($doc, '<style>');

        $this->assertNotFalse($style, "{$tipo}: el documento debe traer su hoja de estilos");
        $this->assertTrue(
            $head < $style && $style < $finHead,
            "{$tipo}: el <style> quedó FUERA del <head> — Word descartaría todos los estilos"
        );
    }

    /**
     * El contrato usa márgenes propios (más apretados, para entrar en 5
     * hojas). Cuando el margen lo fijaba DocResponse, el contrato salía en
     * Word con los márgenes del anexo y se le corrían todos los saltos.
     */
    public function test_cada_documento_lleva_sus_propios_margenes(): void
    {
        $margen = function (string $tipo): string {
            // El @page lleva nombre de sección (WordSection1): es como Word
            // ata la puesta en página, y es lo que él mismo emite al guardar
            // como pagina web. Por eso el patrón admite el nombre.
            preg_match('/@page[^{]*\{([^}]*)\}/', $this->doc($tipo), $m);

            return trim($m[1] ?? '');
        };

        $contrato = $margen('contrato');
        $anexo = $margen('anexo1');

        $this->assertStringContainsString('margin:', $contrato, 'el contrato debe declarar sus márgenes');
        $this->assertStringContainsString('margin:', $anexo, 'el anexo debe declarar sus márgenes');
        $this->assertNotSame(
            $contrato, $anexo,
            'el contrato usa márgenes apretados ($compacto) y el anexo no: en Word no pueden ser iguales'
        );
    }

    /**
     * La puesta en página de Word va atada a una SECCIÓN con nombre, y son
     * DOS piezas que solo sirven juntas: la regla `@page WordSection1` en el
     * CSS y un <div class="WordSection1"> envolviendo el cuerpo. Si falta el
     * div, la regla con nombre no se aplica a nada y el documento sale con
     * los márgenes por defecto de Word.
     */
    #[DataProvider('tipos')]
    public function test_la_seccion_de_pagina_esta_completa(string $tipo): void
    {
        $doc = $this->doc($tipo);

        $this->assertStringContainsString('@page WordSection1 {', $doc, "{$tipo}: falta la regla de la sección");
        $this->assertStringContainsString('div.WordSection1 { page: WordSection1; }', $doc, "{$tipo}: falta el enlace");
        $this->assertMatchesRegularExpression(
            '/<body[^>]*><div class="WordSection1">/', $doc,
            "{$tipo}: el cuerpo debe ir dentro del div de la sección o la regla no aplica"
        );
        $this->assertStringContainsString('</div></body>', $doc, "{$tipo}: el div de la sección debe cerrar dentro del body");
    }

    /**
     * Word no descarga fuentes web al abrir un .doc: las cuatro reglas eran
     * inútiles y podían disparar el aviso de contenido externo. La Bookman
     * Old Style se toma de la instalada (viene con Office).
     */
    #[DataProvider('tipos')]
    public function test_no_se_declaran_fuentes_web_en_word(string $tipo): void
    {
        $doc = $this->doc($tipo);

        $this->assertStringNotContainsString('@font-face', $doc, "{$tipo}: Word no carga @font-face por URL");
        $this->assertStringContainsString('Bookman Old Style', $doc, "{$tipo}: debe pedir la fuente instalada");
    }

    /**
     * El BOM es lo que hace que Word respete las tildes; sin él asume la
     * codificación del sistema. Y el bloque mso abre el documento en vista
     * de impresión, que es como se revisa antes de firmar.
     */
    #[DataProvider('tipos')]
    public function test_lleva_bom_y_cabecera_de_word(string $tipo): void
    {
        $doc = $this->doc($tipo);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $doc, "{$tipo}: falta el BOM UTF-8");
        $this->assertStringContainsString('xmlns:w=', $doc, "{$tipo}: falta el espacio de nombres de Word");
        $this->assertStringContainsString('w:WordDocument', $doc, "{$tipo}: falta el bloque mso");
    }

    /**
     * El pie del Anexo 2 (las cuentas a las que paga el cliente) es CONTENIDO
     * del maestro. En PDF va anclado al fondo; en Word no hay anclaje, así
     * que tiene que ir al final del flujo y NO encima del título.
     */
    public function test_el_pie_del_anexo_2_va_al_final_y_no_sobre_el_titulo(): void
    {
        $doc = $this->doc('anexo2');

        $pie = strpos($doc, 'pie-formas">');
        $titulo = strpos($doc, 'anexo-titulo');

        $this->assertNotFalse($pie, 'el Anexo 2 debe conservar el pie con las formas de pago');
        $this->assertTrue($pie > $titulo, 'en Word el pie debe ir DESPUÉS del título, no encima');
    }

    /**
     * Word convierte position:absolute en un marco flotante que se posa
     * encima del contenido. En el Anexo 1 eso tapaba el cronograma.
     */
    public function test_el_anexo_1_no_posiciona_el_pie_en_word(): void
    {
        $doc = $this->doc('anexo1');

        $this->assertMatchesRegularExpression(
            '/\.ax-pie\s*\{[^}]*margin-top/', $doc,
            'en Word el pie del Anexo 1 va en el flujo'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.ax-pie\s*\{[^}]*position\s*:\s*(absolute|fixed)/', $doc,
            'Word no soporta ese posicionamiento: el pie termina encima del cronograma'
        );
    }

    /** Anexo 1 con cronograma largo: el que se reparte en varias columnas. */
    private function anexo1ConCuotas(int $cuotas, string $medio = 'word'): string
    {
        $credito = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-01',
            'importe' => 20000, 'cuotas' => $cuotas, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 2000, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        for ($i = 1; $i <= $cuotas; $i++) {
            CreditInstallment::create([
                'credit_id' => $credito->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-09-08')->addWeeks($i - 1),
                'importe_cuota' => 400, 'importe_interes' => 50, 'pagado' => false,
            ]);
        }

        return RenderDocumento::html(
            GeneradorAnexo1::construirSnapshot($this->client, $credito, $this->vehiculo),
            'anexo1', $medio
        );
    }

    /** Profundidad de anidamiento de tablas en el cuerpo del documento. */
    private function anidamiento(string $html): int
    {
        $cuerpo = substr($html, (int) strpos($html, '<body'));
        preg_match_all('/<table|<\/table>/i', $cuerpo, $m);

        $prof = 0;
        $max = 0;
        foreach ($m[0] as $tag) {
            $prof += str_starts_with(strtolower($tag), '</') ? -1 : 1;
            $max = max($max, $prof);
        }

        return $max;
    }

    /**
     * EL SEGUNDO SÍNTOMA REPORTADO: "el cronograma está mal".
     *
     * En el PDF el cronograma en columnas se arma con una tabla por columna
     * dentro de una contenedora. Word NO aplica las reglas de clase a una
     * tabla ANIDADA: el cronograma salía sin un solo borde y sin la cabecera
     * azul, mientras las tablas de datos de arriba —de primer nivel— se veían
     * perfectas. Por eso en Word se emite UNA tabla plana.
     */
    public function test_en_word_el_cronograma_en_columnas_no_va_anidado(): void
    {
        $word = $this->anexo1ConCuotas(48);

        $this->assertSame(1, $this->anidamiento($word),
            'en Word el cronograma no puede ir en tablas anidadas: pierde bordes y cabecera azul');
        // Solo el CUERPO: la regla CSS table.ax-split sigue viviendo en el
        // <style>, que es compartido por los tres medios. Lo que no debe
        // existir es el ELEMENTO, que es el que anida.
        $cuerpo = substr($word, (int) strpos($word, '<body'));
        $this->assertStringNotContainsString('class="ax-split"', $cuerpo,
            'la tabla contenedora es justo la que anida');
        $this->assertStringContainsString('total-celda', $cuerpo,
            'la fila Total va por celda, porque la fila mezcla total y separadores');
    }

    /** El PDF conserva la maquetación anidada, que es la validada en papel. */
    public function test_el_pdf_conserva_el_cronograma_anidado(): void
    {
        $pdf = $this->anexo1ConCuotas(48, 'pdf');

        $this->assertStringContainsString('ax-split', $pdf, 'el PDF no cambia: sigue con la contenedora');
        $this->assertSame(2, $this->anidamiento($pdf));
    }

    /**
     * TERCER SÍNTOMA REPORTADO: "la captura del voucher está muy grande".
     *
     * Word no soporta max-width ni max-height, así que pintaba la imagen a su
     * tamaño natural. El voucher real mide 708x1280 px: en Word ocupaba tres
     * hojas y salía cortado por la derecha. Las medidas se calculan en el
     * servidor y se emiten como atributos, que Word sí respeta.
     */
    public function test_el_voucher_del_anexo_2_lleva_medidas_explicitas_en_word(): void
    {
        // Mismo tamaño que el voucher real de producción.
        $img = imagecreatetruecolor(708, 1280);
        $ruta = 'documentos/word-test/voucher.jpg';
        Storage::disk('public')->put($ruta, '');
        imagejpeg($img, Storage::disk('public')->path($ruta));
        imagedestroy($img);

        $snapshot = GeneradorAnexo2::construirSnapshot($this->client, $this->credit, [
            'transcripcion' => 'OPERACION EXITOSA', 'monto' => 5000,
        ]);
        $snapshot['imagen_path'] = $ruta;

        $word = RenderDocumento::html($snapshot, 'anexo2', 'word');

        // 708x1280 topa por el alto: 420/1280 = 0.3281 -> 232 x 420.
        $this->assertMatchesRegularExpression('/<img[^>]*width="232"/', $word, 'ancho escalado');
        $this->assertMatchesRegularExpression('/<img[^>]*height="420"/', $word, 'alto escalado al tope');

        // El PDF sigue con su max-width/max-height, que dompdf sí entiende.
        $pdf = RenderDocumento::html($snapshot, 'anexo2', 'pdf');
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*width="232"/', $pdf,
            'el PDF no lleva medidas fijas: las resuelve por CSS');
    }
}
