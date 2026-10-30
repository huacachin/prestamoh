<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\GeneradorDeclaracion;
use App\Services\Documentos\RenderDocumento;
use App\Support\Documentos\MaquetaContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Declaraciones juradas de propiedad y de publicidad (30/10/2026, Agregados 01 y 02 del Área Legal):
 * salen del contrato emitido del crédito, una por vehículo, con el modelo que corresponde (uno o dos
 * declarantes, empresa o bien futuro), y van al final del orden Contrato → Anexo 1 → Anexo 2.
 */
class DeclaracionesJuradasTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-30 10:00:00');

        $sede = Headquarter::create(['name' => 'Sede DJ', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'legal-dj', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9910', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781900', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
            'email' => 'rosa.p@example.com',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'DJP111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-DJ', 'nro_motor' => 'MOT-DJ', 'valor' => 15000,
        ]);
    }

    /** Contrato REAL (modelo a.2, deudora) emitido con el wizard. */
    private function contratoReal(): DocumentoCliente
    {
        return GeneradorContrato::generar($this->client, $this->credit, [$this->vehiculo->id], 'a2', [
            'fecha' => '28/08/2026', 'banco' => 'bcp', 'valor_bien' => 20000,
            'deudores' => [[
                'sexo' => 'F', 'nombre' => 'QUISPE MAMANI ROSA LINDA', 'dni' => '46781900',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
                'domicilio' => 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'correo' => 'ROSA.P@EXAMPLE.COM',
            ]],
        ]);
    }

    /**
     * Contrato con el snapshot armado a mano: solo las claves que lee la declaración.
     *
     * @param  list<array<string, mixed>>  $deudores
     * @param  list<array<string, mixed>>  $bienes
     */
    private function contratoCon(array $deudores, array $bienes, bool $juridica = false): DocumentoCliente
    {
        return DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $this->credit->id, 'tipo' => 'contrato',
            'modelo' => 'a3', 'version' => 1, 'estado' => 'emitido',
            'snapshot' => ['fecha' => '2026-10-15', 'conjunto' => ['juridica' => $juridica], 'deudores' => $deudores, 'bienes' => $bienes],
        ]);
    }

    private function persona(string $nombre, string $dni, string $domicilio): array
    {
        return ['esJuridica' => false, 'nombre' => $nombre, 'dni' => $dni, 'documentoTipo' => 'DNI', 'domicilio' => $domicilio];
    }

    private function bien(string $placa, bool $futuro = false): array
    {
        return ['placa' => $placa, 'esFuturo' => $futuro, 'kardex' => $futuro ? '2026-4512' : null, 'notario' => $futuro ? 'PAÚL JHON HINOJOSA CARRILLO' : null];
    }

    /** Texto corrido del documento, sin etiquetas ni estilos. */
    private function texto(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(style|head)\b.*?</\1>#is', '', $html))));
    }

    public function test_la_dj_de_propiedad_sale_del_contrato_con_los_datos_en_rojo_solo_en_la_previa(): void
    {
        Storage::fake('public');
        $this->contratoReal();

        $previa = GeneradorDeclaracion::previsualizar($this->client, $this->credit, 'djprop');
        $rojo = fn (string $t) => '<span class="variable">'.$t.'</span>';
        foreach (['QUISPE MAMANI ROSA LINDA', '46781900', 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA', 'DJP111', '30 DE OCTUBRE DE 2026'] as $dato) {
            $this->assertStringContainsString($rojo($dato), $previa, "Debió salir en rojo: {$dato}");
        }
        $texto = $this->texto($previa);
        $this->assertStringContainsString('DECLARACIÓN JURADA DE PROPIEDAD, POSESIÓN', $texto);
        $this->assertStringContainsString('YO, QUISPE MAMANI ROSA LINDA, IDENTIFICADO(A) CON DNI N.° 46781900, CON DOMICILIO EN CALLE LAS BEGONIAS 450', $texto);
        $this->assertStringContainsString('EL VEHÍCULO DE PLACA DE RODAJE N.° DJP111 ES DE MI PROPIEDAD', $texto);
        $this->assertStringContainsString('LIMA, 30 DE OCTUBRE DE 2026. FIRMA DEL(DE LA) DECLARANTE QUISPE MAMANI ROSA LINDA DNI N.° 46781900 HUELLA DIGITAL', $texto);
        $this->assertStringContainsString('<b>PRIMERO: PROPIEDAD Y LEGITIMIDAD.</b>', $previa);

        $doc = GeneradorDeclaracion::generar($this->client, $this->credit, 'djprop');
        $this->assertSame(['djprop', 'uno', 1, 'DJP111'], [$doc->tipo, $doc->modelo, $doc->version, $doc->snapshot['bien']['placa']]);
        $pdf = Storage::disk('public')->get($doc->pdf_path);
        $this->assertSame(1, MaquetaContrato::hojas($pdf), 'una sola hoja');
        foreach (['pdf', 'word'] as $medio) {
            $html = RenderDocumento::html($doc->snapshot, 'djprop', $medio);
            $this->assertStringNotContainsString('class="variable"', $html, $medio);
            $this->assertSame($texto, $this->texto($html), "El texto del medio {$medio} difiere de la previa");
        }
        $this->assertDatabaseHas('activity_log', ['description' => "Generó la declaración jurada de propiedad v1 del crédito #{$this->credit->id} (placa DJP111, un declarante)"]);
    }

    public function test_la_de_publicidad_cita_la_fecha_del_contrato_y_las_constantes_de_la_marca(): void
    {
        Storage::fake('public');
        $this->contratoReal();

        $texto = $this->texto(GeneradorDeclaracion::previsualizar($this->client, $this->credit, 'djpubli'));

        $this->assertStringContainsString('EN MI CONDICIÓN DE TITULAR DEL VEHÍCULO DE PLACA DJP111, CONSTITUIDO EN GARANTÍA MOBILIARIA A FAVOR DE GUILMER NICEFARO HUACACHIN PAUCAR, DNI N.º 40463004, MEDIANTE CONTRATO DE FECHA 28 DE AGOSTO DEL 2026', $texto);
        $this->assertStringContainsString('LICENCIATARIO DE LA MARCA PRESTA EXPRESS, Y A INVERSIONES HUACACHIN S.A.C., RUC N.º 20516719452', $texto);
        $this->assertStringContainsString('SUSCRIBO LA PRESENTE DECLARACIÓN EN LIMA, EL 30 DE OCTUBRE DEL 2026. FIRMA DE LA PERSONA TITULAR QUISPE MAMANI ROSA LINDA DNI N.º 46781900', $texto);

        $doc = GeneradorDeclaracion::generar($this->client, $this->credit, 'djpubli');
        $this->assertSame(1, MaquetaContrato::hojas(Storage::disk('public')->get($doc->pdf_path)));
    }

    public function test_elige_el_modelo_segun_el_contrato(): void
    {
        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', '46781900', 'AV. AREQUIPA 3400, DISTRITO DE LINCE');
        $juan = $this->persona('TAFUR CUENCA JUAN', '72957633', 'JR. LOS GERANIOS 12, DISTRITO DE ATE');

        // Dos declarantes.
        $this->contratoCon([$rosa, $juan], [$this->bien('DOS222')]);
        $s = GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop');
        $this->assertSame('dos', $s['variante']);
        $texto = $this->texto(RenderDocumento::html($s, 'djprop', 'pdf'));
        $this->assertStringContainsString('QUIENES SUSCRIBIMOS, QUISPE MAMANI ROSA LINDA, CON DNI N.° 46781900, CON DOMICILIO EN AV. AREQUIPA 3400, DISTRITO DE LINCE; Y TAFUR CUENCA JUAN, CON DNI N.° 72957633', $texto);
        $this->assertSame(1, substr_count($texto, 'FIRMA DECLARANTE 2'), 'un bloque de firma por declarante (en el .docx salía repetido)');
        $this->assertStringContainsString('QUIENES SUSCRIBIMOS', $this->texto(GeneradorDeclaracion::previsualizar($this->client, $this->credit, 'djpubli')));

        // Bien futuro: kárdex y notario del contrato (el .docx 2.1 ponía el kárdex donde va el notario).
        DocumentoCliente::query()->delete();
        $this->contratoCon([$rosa], [$this->bien('FUT333', futuro: true)]);
        $s = GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop');
        $this->assertSame('futuro-uno', $s['variante']);
        $this->assertStringContainsString('PLACA N° FUT333, CONFORME A LA TRANSFERENCIA VEHICULAR CON KARDEX N° 2026-4512 REALIZADO ANTE EL NOTARIO PAÚL JHON HINOJOSA CARRILLO.', $this->texto(RenderDocumento::html($s, 'djprop', 'pdf')));
        $this->assertSame('uno', GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djpubli')['variante'], 'la de publicidad no distingue el bien futuro');

        // Empresa: razón social, RUC y la firma de su gerente.
        DocumentoCliente::query()->delete();
        $this->contratoCon([[
            'esJuridica' => true, 'nombre' => 'TRANSPORTES ROSA S.A.C.', 'ruc' => '20601234567', 'domicilio' => 'AV. COLONIAL 100, LIMA',
            'partida' => '14512345', 'oficinaRegistral' => 'LIMA',
            'gerente' => ['nombre' => 'QUISPE MAMANI ROSA LINDA', 'documentoTipo' => 'DNI', 'dni' => '46781900'],
        ]], [$this->bien('EMP444')], juridica: true);
        $prop = $this->texto(RenderDocumento::html(GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop'), 'djprop', 'pdf'));
        $this->assertStringContainsString('YO, TRANSPORTES ROSA S.A.C., IDENTIFICADA CON RUC N.° 20601234567, CON DOMICILIO EN AV. COLONIAL 100, LIMA, DEBIDAMENTE REPRESENTADA POR SU GERENTE GENERAL, QUISPE MAMANI ROSA LINDA, CON DNI N.° 46781900', $prop);
        $this->assertStringContainsString('FIRMA DEL/LA GERENTE GENERAL QUISPE MAMANI ROSA LINDA DNI N.° 46781900', $prop);
        $publi = $this->texto(RenderDocumento::html(GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djpubli'), 'djpubli', 'pdf'));
        $this->assertStringContainsString('CON FACULTADES INSCRITAS EN LA PARTIDA REGISTRAL N.º 14512345 DEL REGISTRO DE PERSONAS JURÍDICAS DE LIMA', $publi);
        $this->assertStringContainsString('FIRMA DE TITULAR TRANSPORTES ROSA S.A.C. RUC N.º 20601234567', $publi);
    }

    public function test_una_declaracion_por_vehiculo_y_los_rechazos(): void
    {
        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', '46781900', 'AV. AREQUIPA 3400');

        try {
            GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop');
            $this->fail('Sin contrato no debía generarse');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('primero genera el contrato', $e->getMessage());
        }

        $this->contratoCon([$rosa], [$this->bien('UNO111'), $this->bien('DOS222')]);
        $this->assertSame(['UNO111', 'DOS222'], GeneradorDeclaracion::placasDe($this->client, $this->credit));
        $this->assertSame('DOS222', GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop', 'DOS222')['bien']['placa']);
        $this->expectExceptionMessage('La placa ZZZ999 no está en el contrato');
        GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop', 'ZZZ999');
    }

    public function test_la_empresa_no_tiene_modelo_de_bien_futuro(): void
    {
        $this->contratoCon([['esJuridica' => true, 'nombre' => 'EMPRESA S.A.C.', 'ruc' => '20600000001', 'gerente' => []]], [$this->bien('FUT555', futuro: true)], juridica: true);

        $this->expectExceptionMessage('No hay modelo de declaración de propiedad de bien futuro para empresas.');
        GeneradorDeclaracion::construirSnapshot($this->client, $this->credit, 'djprop');
    }

    public function test_los_botones_generan_y_el_listado_las_pone_despues_del_anexo_2(): void
    {
        Storage::fake('public');
        $this->contratoReal();

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->assertSee('DJ Propiedad')
            ->assertSee('DJ Publicidad')
            ->call('abrirModalDeclaracion', 'djpubli')
            ->assertSet('djCreditoId', $this->credit->id)
            ->assertSet('djPlaca', 'DJP111')
            ->call('generarDeclaracion')
            ->assertDispatched('declaracion-modal-close')
            ->call('abrirModalDeclaracion', 'djprop')
            ->call('generarDeclaracion')
            ->assertNotDispatched('errorAlert');

        $this->assertSame(['contrato', 'djpubli', 'djprop'], DocumentoCliente::orderBy('id')->pluck('tipo')->all());
        // Agregado 02: en el listado van Contrato → … → DJ de propiedad → DJ de publicidad.
        // (assertSeeInOrder de Livewire mira el JSON con los acentos escapados: se comparan posiciones en el HTML.)
        $html = $c->html();
        $tabla = substr($html, strpos($html, '<tbody>'));
        $posiciones = array_map(fn ($t) => strpos($tabla, $t), ['a2 — a.2 GPS. Deudora', 'DJ de propiedad', 'Placa DJP111 · un declarante', 'DJ de publicidad']);
        $this->assertNotContains(false, $posiciones);
        $ordenadas = $posiciones;
        sort($ordenadas);
        $this->assertSame($ordenadas, $posiciones);
    }
}
