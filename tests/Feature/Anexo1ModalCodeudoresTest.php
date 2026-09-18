<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El camino REAL del Anexo 1: el modal de la ficha del cliente. Hasta el
 * 18/09 todo lo del codeudor se probaba llamando al generador a mano, así
 * que nada garantizaba que el modal le pasara los vehículos con sus
 * copropietarios — que es donde Antony vio que "no salen los copropietarios".
 */
class Anexo1ModalCodeudoresTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Client, Client, Credit, Vehiculo} */
    private function mundo(): array
    {
        $this->actingAs(User::factory()->create(['username' => 'anx-modal']));

        $titular = Client::create([
            'expediente' => '8100', 'nombre' => 'Ingrid Madeleine', 'apellido_pat' => 'Mandujano',
            'apellido_mat' => 'Raraz', 'tipo_documento' => 'DNI', 'documento' => '04049993',
            'sexo' => 'F', 'status' => 'active', 'direccion' => 'UCV 74 LOTE 31 ZONA E HUAYCAN',
            'celular1' => '915055012', 'email' => 'titular@gmail.com',
        ]);
        $codeudor = Client::create([
            'expediente' => '8101', 'nombre' => 'Miguel Alcides', 'apellido_pat' => 'Mejia',
            'apellido_mat' => 'Villanueva', 'tipo_documento' => 'DNI', 'documento' => '04049399',
            'sexo' => 'M', 'status' => 'active', 'direccion' => 'UCV 74 LOTE 31 ZONA E HUAYCAN',
            'celular1' => '953243546', 'email' => 'codeudor@gmail.com',
        ]);

        $credit = Credit::create([
            'client_id' => $titular->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 12000, 'cuotas' => 28, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 2000, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        for ($i = 1; $i <= 28; $i++) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => now()->addWeeks($i)->format('Y-m-d'),
                'importe_cuota' => 500, 'importe_interes' => 48, 'pagado' => 0,
            ]);
        }

        $vehiculo = Vehiculo::create([
            'client_id' => $titular->id, 'placa' => 'T1T779', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE COMMUTER', 'nro_serie' => 'JTFSS22P1D0118418', 'valor' => 25000,
        ]);
        $vehiculo->copropietarios()->attach($codeudor->id);

        return [$titular, $codeudor, $credit, $vehiculo];
    }

    /** Abrir el modal y generar: el documento sale con los DOS deudores. */
    public function test_el_modal_emite_el_anexo_con_el_copropietario_como_codeudor(): void
    {
        Storage::fake('public');
        [$titular, $codeudor, $credit, $vehiculo] = $this->mundo();

        Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalAnexo1')
            // El modal premarca la garantía completa...
            ->assertSet('anexoVehiculos', [$vehiculo->id])
            ->assertSet('anexoSinCodeudores', false)
            ->set('creditoId', $credit->id)
            ->call('generar')
            ->assertHasNoErrors();

        $doc = DocumentoCliente::where('tipo', 'anexo1')->latest('id')->first();
        $this->assertNotNull($doc, 'el modal no llegó a emitir el Anexo 1');
        $this->assertCount(2, $doc->snapshot['clientes'], 'el anexo salió sin el copropietario');
        $this->assertSame(
            [mb_strtoupper($titular->fullName()), mb_strtoupper($codeudor->fullName())],
            array_column($doc->snapshot['clientes'], 'nombre')
        );
        // Y con los datos propios de cada uno, no los del titular repetidos.
        $this->assertSame('04049399', $doc->snapshot['clientes'][1]['documento']);
        $this->assertSame('953243546', $doc->snapshot['clientes'][1]['celular']);
        $this->assertSame('codeudor@gmail.com', $doc->snapshot['clientes'][1]['correo']);
    }

    /** Desmarcando el vehículo compartido, el codeudor ya no entra. */
    public function test_sin_el_vehiculo_compartido_no_hay_codeudor(): void
    {
        Storage::fake('public');
        [$titular, , $credit] = $this->mundo();

        Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalAnexo1')
            ->set('creditoId', $credit->id)
            ->set('anexoVehiculos', [])
            ->call('generar')
            ->assertHasNoErrors();

        $this->assertCount(1, DocumentoCliente::where('tipo', 'anexo1')->latest('id')->first()->snapshot['clientes']);
    }

    /** El interruptor del modal manda por encima del vínculo. */
    public function test_el_interruptor_deja_fuera_al_codeudor(): void
    {
        Storage::fake('public');
        [$titular, , $credit] = $this->mundo();

        Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalAnexo1')
            ->set('creditoId', $credit->id)
            ->set('anexoSinCodeudores', true)
            ->call('generar')
            ->assertHasNoErrors();

        $this->assertCount(1, DocumentoCliente::where('tipo', 'anexo1')->latest('id')->first()->snapshot['clientes']);
    }
}
