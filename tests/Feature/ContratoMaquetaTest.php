<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\RenderDocumento;
use App\Support\Documentos\DomicilioLegal;
use App\Support\Documentos\MaquetaContrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Maqueta del contrato (16/10/2026, pedido de Antony): margen exterior de
 * 3.2 cm para que la firma de cada hoja entre, y tope de 5 hojas garantizado
 * en la emisión: si el PDF se pasa, GeneradorContrato aprieta la maqueta por
 * niveles (MaquetaContrato) y el nivel queda en el snapshot; si ni el más
 * apretado entra, no se emite nada.
 */
class ContratoMaquetaTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $sede = Headquarter::create(['name' => 'Sede Maqueta', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'maqueta', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9906', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
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
            'client_id' => $this->client->id, 'placa' => 'MAQ111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-M1', 'nro_motor' => 'MOT-M1', 'valor' => 15000,
        ]);
    }

    private function datos(): array
    {
        return [
            'fecha' => '28/08/2026',
            'banco' => 'bcp',
            'deudores' => [[
                'sexo' => 'F', 'nombre' => mb_strtoupper($this->client->fullName()), 'dni' => '46781900',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
                'domicilio' => DomicilioLegal::deCliente($this->client),
                'correo' => 'ROSA.P@EXAMPLE.COM',
            ]],
        ];
    }

    /** Cláusulas adicionales de relleno: $n párrafos de unas 60 palabras. */
    private function parrafos(int $n): string
    {
        $frase = 'EL PRESENTE ACUERDO SE INTERPRETA CONFORME A LAS NORMAS VIGENTES Y LAS PARTES DECLARAN CONOCER SUS '
            .'ALCANCES EN TODOS SUS EXTREMOS, SIN RESERVA NI LIMITACIÓN ALGUNA, RATIFICANDO CADA UNA DE LAS ESTIPULACIONES '
            .'AQUÍ CONTENIDAS Y COMPROMETIÉNDOSE A CUMPLIRLAS DE BUENA FE. ';

        $parrafos = [];
        for ($i = 1; $i <= $n; $i++) {
            $parrafos[] = "{$i}. ".str_repeat($frase, 2);
        }

        return implode("\n", $parrafos);
    }

    /** Hojas reales del snapshot renderizado como PDF con un nivel dado de maqueta. */
    private function hojas(array $snapshot, int $ajuste): int
    {
        $snapshot['ajuste'] = $ajuste;

        return MaquetaContrato::hojas(
            Pdf::loadHTML(RenderDocumento::html($snapshot, 'contrato', 'pdf'))->setPaper('a4')->output()
        );
    }

    public function test_el_margen_exterior_mide_3_2_cm_y_la_previa_lo_replica(): void
    {
        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $this->datos()
        );
        $margenes = MaquetaContrato::nivel(0)['margenes'];
        [, $derecho] = explode(' ', $margenes);

        $this->assertSame('3.2cm', $derecho, 'El margen derecho (costado exterior, donde se firma cada hoja) debe medir 3.2 cm');
        $this->assertStringContainsString("@page { margin: {$margenes}; }", RenderDocumento::html($snapshot, 'contrato', 'pdf'));
        $this->assertStringContainsString("padding: {$margenes};", RenderDocumento::html($snapshot, 'contrato', 'previa'));
    }

    public function test_si_el_pdf_pasa_de_cinco_hojas_se_aprieta_la_maqueta_hasta_que_entra(): void
    {
        // El relleno más corto (de 10 en 10 párrafos) que pasa del tope con la
        // maqueta normal: así el test prueba la reserva de niveles y no
        // depende de cuánto sobra hoy. Pasos de 10 (≈ media hoja) porque
        // dompdf se arrastra con párrafos muy largos.
        $datos = $this->datos();
        $snapshot = [];
        for ($n = 10; $n <= 150; $n += 10) {
            $datos['clausulas_adicionales'] = $this->parrafos($n);
            $snapshot = GeneradorContrato::construirSnapshot($this->client, $this->credit, [$this->vehiculo->id], 'a2', $datos);
            if ($this->hojas($snapshot, 0) > MaquetaContrato::MAX_HOJAS) {
                break;
            }
        }
        $this->assertGreaterThan(MaquetaContrato::MAX_HOJAS, $this->hojas($snapshot, 0), 'El relleno no llegó a pasar el tope');

        $doc = GeneradorContrato::generar($this->client, $this->credit, [$this->vehiculo->id], 'a2', $datos);

        $pdf = Storage::disk('public')->get($doc->pdf_path);
        $this->assertGreaterThan(0, $doc->snapshot['ajuste'], 'Debió apretar la maqueta');
        $this->assertLessThanOrEqual(MaquetaContrato::MAX_HOJAS, MaquetaContrato::hojas($pdf));
        // El snapshot congelado re-renderiza con las mismas hojas que el PDF emitido.
        $this->assertSame(MaquetaContrato::hojas($pdf), $this->hojas($doc->snapshot, $doc->snapshot['ajuste']));
        $this->assertStringContainsString('maqueta apretada al nivel '.$doc->snapshot['ajuste'], (string) ActivityLog::latest('id')->value('description'));
    }

    public function test_si_ni_la_maqueta_mas_apretada_entra_no_se_emite(): void
    {
        $datos = $this->datos();
        // ≈ 3 hojas de relleno: ni el nivel más apretado lo absorbe.
        $datos['clausulas_adicionales'] = $this->parrafos(40);

        try {
            GeneradorContrato::generar($this->client, $this->credit, [$this->vehiculo->id], 'a2', $datos);
            $this->fail('Debió rechazar un contrato que no entra en '.MaquetaContrato::MAX_HOJAS.' hojas');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('hojas y el máximo es '.MaquetaContrato::MAX_HOJAS, $e->getMessage());
        }

        $this->assertSame(0, DocumentoCliente::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
