<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use App\Services\Documentos\RenderDocumento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Previa del Anexo 1 (17/10/2026, pedido de Antony: "lo mismo que en el
 * contrato"): los DATOS VARIABLES —cliente, vehículo, crédito y cronograma—
 * salen en rojo fuerte y negrita SOLO en el medio 'previa' ($dato() de
 * Resaltado). El PDF y el Word salen sin el span y con el mismo texto; las
 * etiquetas, el banner y el pie siguen en negro.
 */
class Anexo1PreviaDatosVariablesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede Anexo1 previa', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'anexo1-previa', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9907', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781234', 'sexo' => 'F',
            'direccion' => 'MZ. B LOTE 12 AAHH LOS JARDINES', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'celular1' => '987654321', 'email' => 'rosa.quispe@example.com',
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
            'client_id' => $this->client->id, 'placa' => 'ABC123', 'marca' => 'TOYOTA', 'modelo' => 'HILUX',
            'nro_serie' => 'SER-778899', 'valor' => 15000,
        ]);
    }

    public function test_la_previa_pinta_en_rojo_los_datos_variables_y_deja_las_etiquetas_en_negro(): void
    {
        $previa = GeneradorAnexo1::previsualizar($this->client, $this->credit, $this->vehiculo);
        $rojo = fn (string $t) => '<span class="variable">'.$t.'</span>';

        foreach ([
            '46781234', '987654321', 'rosa.quispe@example.com',
            'ABC123', 'TOYOTA', 'HILUX', 'SER-778899', '15.000,00',
            '5.000,00',   // monto del crédito
            '1.300,00',   // cada cuota
            '5.200,00',   // total del cronograma
        ] as $dato) {
            $this->assertStringContainsString($rojo($dato), $previa, "Debió salir en rojo: {$dato}");
        }
        // La fila del cronograma entera: número, fecha y monto.
        $this->assertMatchesRegularExpression(
            '#'.preg_quote($rojo('1'), '#').'</td>\s*<td class="num">'.preg_quote($rojo('01/09/2026'), '#').'#',
            $previa
        );
        $this->assertStringContainsString('.variable { color: #ff0000; font-weight: bold; }', $previa);

        // Etiquetas y cabeceras en negro.
        foreach (['DATOS DEL CLIENTE', 'DATOS DEL VEHÍCULO', 'DATOS DEL CRÉDITO', 'Placa de Rodaje', 'Total'] as $fijo) {
            $this->assertStringContainsString($fijo, $previa);
            $this->assertStringNotContainsString($rojo($fijo), $previa);
        }
    }

    public function test_el_pdf_y_el_word_salen_sin_resaltado_y_con_el_mismo_texto(): void
    {
        $snapshot = GeneradorAnexo1::construirSnapshot($this->client, $this->credit, $this->vehiculo);
        $previa = RenderDocumento::html($snapshot, 'anexo1', 'previa');
        $this->assertStringContainsString('class="variable"', $previa);

        foreach (['pdf', 'word'] as $medio) {
            $html = RenderDocumento::html($snapshot, 'anexo1', $medio);
            $this->assertStringNotContainsString('class="variable"', $html, $medio);
            $this->assertStringNotContainsString('.variable {', $html, $medio);
        }
        $this->assertSame($this->texto($previa), $this->texto(RenderDocumento::html($snapshot, 'anexo1', 'pdf')));
    }

    /** Texto corrido del cuerpo: sin <head>, sin <style> y sin etiquetas. */
    private function texto(string $html): string
    {
        $html = (string) preg_replace('/<head>.*?<\/head>|<style[^>]*>.*?<\/style>/su', '', $html);

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
    }
}
