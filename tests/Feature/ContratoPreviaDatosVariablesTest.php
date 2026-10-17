<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\RenderDocumento;
use App\Support\Documentos\BancosVoucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Previa del contrato (16/10/2026, pedido de Antony): los DATOS VARIABLES
 * —lo que viene de la ficha, el crédito, los vehículos y el wizard— salen en
 * rojo para revisarlos antes de emitir. ContratoVm::v() los envuelve en
 * <span class="variable"> SOLO en el medio 'previa'; el PDF y el Word salen
 * sin el span y con exactamente el mismo texto. Las constantes del acreedor
 * y su apoderada siguen en negro.
 */
class ContratoPreviaDatosVariablesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede Previa', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'previa', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9905', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
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
            'client_id' => $this->client->id, 'placa' => 'PRV111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-V1', 'nro_motor' => 'MOT-V1', 'valor' => 15000,
        ]);
    }

    private function datos(): array
    {
        return [
            'fecha' => '28/08/2026',
            'banco' => 'bcp',
            'valor_bien' => 20000,
            'clausulas_adicionales' => 'Se entregará copia de la tarjeta de propiedad.',
            'deudores' => [[
                'sexo' => 'F', 'nombre' => mb_strtoupper($this->client->fullName()), 'dni' => '46781900',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'ABOGADA', 'estado_civil' => 'CASADO',
                'domicilio' => 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'correo' => 'ROSA.P@EXAMPLE.COM',
            ]],
        ];
    }

    public function test_la_previa_pinta_en_rojo_cada_dato_variable_y_deja_las_constantes_en_negro(): void
    {
        $previa = GeneradorContrato::previsualizar(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $this->datos()
        );
        $rojo = fn (string $t) => '<span class="variable">'.$t.'</span>';

        foreach ([
            mb_strtoupper($this->client->fullName()), '46781900', 'PERUANO', 'ABOGADA', 'CASADA',
            'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'ROSA.P@EXAMPLE.COM', 'PRV111', 'TOYOTA', 'HIACE',
            'S/ 20,000.00 (VEINTE MIL CON 00/100 SOLES)',
            'S/ 5,000.00 (CINCO MIL CON 00/100 SOLES)',
            'S/ 1,300.00 (MIL TRESCIENTOS CON 00/100 SOLES)',
            '28 DE AGOSTO DEL 2026',
            mb_strtoupper(BancosVoucher::nombreLegal('bcp')),
            'SE ENTREGARÁ COPIA DE LA TARJETA DE PROPIEDAD.',
        ] as $dato) {
            $this->assertStringContainsString($rojo($dato), $previa, "Debió salir en rojo: {$dato}");
        }
        $this->assertStringContainsString($rojo('4').' CUOTAS', $previa);
        $this->assertStringContainsString('.variable { color: #c00000; }', $previa);

        // El acreedor y su apoderada son constantes: en negro.
        $acreedor = config('documentos.acreedor');
        $apoderada = config('documentos.apoderada');
        $this->assertStringContainsString('IDENTIFICADO CON DNI N° '.$acreedor['dni'].', ESTADO CIVIL', $previa);
        $this->assertStringNotContainsString($rojo($acreedor['dni']), $previa);
        $this->assertStringNotContainsString($rojo($apoderada['nombre']), $previa);
        $this->assertStringNotContainsString($rojo($acreedor['domicilio']), $previa);
    }

    public function test_el_pdf_y_el_word_salen_sin_resaltado_y_con_el_mismo_texto_que_la_previa(): void
    {
        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $this->datos()
        );
        $previa = RenderDocumento::html($snapshot, 'contrato', 'previa');
        $this->assertStringContainsString('class="variable"', $previa);

        foreach (['pdf', 'word'] as $medio) {
            $html = RenderDocumento::html($snapshot, 'contrato', $medio);
            $this->assertStringNotContainsString('class="variable"', $html, $medio);
            $this->assertStringNotContainsString('.variable {', $html, $medio);
            $this->assertSame($this->texto($previa), $this->texto($html), "El texto del medio {$medio} difiere de la previa");
        }
    }

    /** Texto corrido del cuerpo: sin <head>, sin <style> y sin etiquetas. */
    private function texto(string $html): string
    {
        $html = (string) preg_replace('/<head>.*?<\/head>|<style[^>]*>.*?<\/style>/su', '', $html);

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
    }
}
