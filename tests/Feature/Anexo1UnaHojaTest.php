<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El Anexo 1 debe caber en UNA hoja (pedido 28/08), con 1 o varios vehículos. */
class Anexo1UnaHojaTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $cuotas, int $vehiculos = 1): array
    {
        $this->actingAs(User::factory()->create(['username' => 'anx-hoja-'.$cuotas.'-'.$vehiculos]));
        $client = Client::create([
            'expediente' => (string) (700 + $cuotas + $vehiculos), 'nombre' => 'Cliente De Prueba Con Nombre Largo',
            'apellido_pat' => 'Apellido', 'apellido_mat' => 'Materno',
            'tipo_documento' => 'DNI', 'documento' => str_pad((string) (10000000 + $cuotas * 10 + $vehiculos), 8, '0', STR_PAD_LEFT), 'sexo' => 'M', 'status' => 'active',
            'direccion' => 'Av. Siempre Viva 742, Urbanización Las Palmeras', 'celular1' => '999888777',
            'email' => 'cliente@correo.com',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 20000, 'cuotas' => $cuotas, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 2000, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        for ($i = 1; $i <= $cuotas; $i++) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => now()->addWeeks($i)->format('Y-m-d'),
                'importe_cuota' => 400, 'importe_interes' => 50, 'pagado' => 0,
            ]);
        }
        $lista = collect();
        for ($v = 1; $v <= $vehiculos; $v++) {
            $lista->push(Vehiculo::create([
                'client_id' => $client->id, 'placa' => "P{$cuotas}{$v}-99",
                'marca' => 'Mercedes Benz', 'modelo' => 'Sprinter 515',
                'nro_serie' => "9BM38406{$v}KB123456", 'valor' => 45000 + $v,
            ]));
        }

        return [$client, $credit, $lista];
    }

    private function paginas(string $pdf): int
    {
        // dompdf escribe /Type /Page por página; /Pages es el nodo raíz.
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_una_hoja_con_cronogramas_de_distinto_largo(): void
    {
        Storage::fake('public');

        foreach ([4, 12, 24, 36, 48, 72, 96] as $cuotas) {
            [$client, $credit, $vehiculos] = $this->mundo($cuotas);
            $doc = app(GeneradorAnexo1::class)->generar($client, $credit, $vehiculos);
            $pdf = Storage::disk('public')->get($doc->pdf_path);

            $this->assertSame(1, $this->paginas($pdf), "con {$cuotas} cuotas el anexo debe caber en 1 hoja");
        }
    }

    public function test_una_hoja_con_tres_vehiculos_y_48_cuotas(): void
    {
        Storage::fake('public');
        [$client, $credit, $vehiculos] = $this->mundo(48, 3);

        $doc = app(GeneradorAnexo1::class)->generar($client, $credit, $vehiculos);
        $pdf = Storage::disk('public')->get($doc->pdf_path);

        $this->assertCount(3, $doc->snapshot['vehiculos']);
        $this->assertSame(1, $this->paginas($pdf), 'con 3 vehículos y 48 cuotas también debe caber en 1 hoja');
    }
}
