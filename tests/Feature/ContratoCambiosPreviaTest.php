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
use App\Support\Documentos\DomicilioLegal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Previa del contrato (16/10/2026, pedido de Antony): "colocar en rojo los
 * cambios que se han realizado". GeneradorContrato anota en el snapshot
 * ('cambios') lo que el wizard mandó distinto de los datos del sistema y
 * ResaltarCambios lo envuelve en <span class="cambio"> SOLO en el medio
 * 'previa'. Lo que el wizard devuelve tal cual lo precargó no es un cambio.
 */
class ContratoCambiosPreviaTest extends TestCase
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
            // "casada" en la ficha: el wizard lo normaliza a su opción CASADO.
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'casada',
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

    /** Lo que el wizard precarga de la ficha y del crédito, devuelto sin tocar. */
    private function datosSinCambios(): array
    {
        return [
            'fecha' => now()->format('d/m/Y'),
            'banco' => 'bcp',
            'valor_bien' => 15000.00,
            'cuota' => 1300.00,
            'monto_maximo' => 5200.00,
            'deudores' => [[
                'sexo' => 'F', 'nombre' => mb_strtoupper($this->client->fullName()), 'dni' => '46781900',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
                'domicilio' => DomicilioLegal::deCliente($this->client),
                'correo' => 'ROSA.P@EXAMPLE.COM',
            ]],
        ];
    }

    public function test_sin_cambios_la_previa_no_pinta_nada(): void
    {
        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $this->datosSinCambios()
        );
        $this->assertSame([], $snapshot['cambios']);

        $previa = GeneradorContrato::previsualizar(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $this->datosSinCambios()
        );
        $this->assertStringNotContainsString('class="cambio"', $previa);
    }

    public function test_lo_editado_en_el_wizard_sale_en_rojo_solo_en_la_previa(): void
    {
        $datos = $this->datosSinCambios();
        $datos['deudores'][0]['ocupacion'] = 'ABOGADA';
        $datos['deudores'][0]['domicilio'] = 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA';
        $datos['valor_bien'] = 20000;
        $datos['fecha'] = '28/08/2026';
        $datos['clausulas_adicionales'] = 'Se entregará copia de la tarjeta de propiedad.';

        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $datos
        );
        $this->assertEqualsCanonicalizing(
            ['deudores.0.ocupacion', 'deudores.0.domicilio', 'montos.valor_bien', 'fecha', 'clausulasAdicionales'],
            $snapshot['cambios']
        );

        $previa = RenderDocumento::html($snapshot, 'contrato', 'previa');
        $this->assertStringContainsString('<span class="cambio">ABOGADA</span>', $previa);
        $this->assertStringContainsString(
            '<span class="cambio">CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA</span>',
            $previa
        );
        $this->assertStringContainsString('<span class="cambio">S/ 20,000.00 (VEINTE MIL CON 00/100 SOLES)</span>', $previa);
        $this->assertStringContainsString('<span class="cambio">28 DE AGOSTO DEL 2026</span>', $previa);
        $this->assertStringContainsString('<span class="cambio">SE ENTREGARÁ COPIA DE LA TARJETA DE PROPIEDAD.</span>', $previa);
        $this->assertStringContainsString('.cambio { color: #c00000; }', $previa);
        // Lo que no se tocó sigue en negro.
        $this->assertStringNotContainsString('<span class="cambio">'.mb_strtoupper($this->client->fullName()).'</span>', $previa);
        $this->assertStringNotContainsString('<span class="cambio">46781900</span>', $previa);

        // El PDF (misma ruta que el Word) nunca lleva el resaltado.
        $pdf = RenderDocumento::html($snapshot, 'contrato', 'pdf');
        $this->assertStringNotContainsString('class="cambio"', $pdf);
        $this->assertStringNotContainsString('.cambio {', $pdf);
    }

    public function test_el_monto_igual_al_calculado_y_el_estado_civil_normalizado_no_cuentan_como_cambio(): void
    {
        $datos = $this->datosSinCambios();
        // Mismo valor con otra representación: el wizard manda strings.
        $datos['valor_bien'] = '15000';
        $datos['monto_maximo'] = '5200.001';
        $datos['deudores'][0]['estado_civil'] = 'CASADO'; // la ficha dice "casada"

        $snapshot = GeneradorContrato::construirSnapshot(
            $this->client, $this->credit, [$this->vehiculo->id], 'a2', $datos
        );

        $this->assertSame([], $snapshot['cambios']);
        $this->assertSame('CASADO', $snapshot['deudores'][0]['estadoCivil']);
    }
}
