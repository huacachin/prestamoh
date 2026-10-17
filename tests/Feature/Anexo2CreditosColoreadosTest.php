<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ventana "Generar Anexo 2" (17/10/2026, pedido de Antony):
 *  - "Crédito desembolsado" es la misma lista con color del Anexo 1: amarillo
 *    el crédito vigente del día (el más reciente sin contrato), negrita los
 *    que ya tienen contrato y rojo los créditos anteriores sin contrato.
 *  - El monto del voucher queda BLOQUEADO: lo rellena la lectura del voucher
 *    y solo sirve para cuadrar contra el desembolso.
 */
class Anexo2CreditosColoreadosTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Anexo2', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'anexo2-color', 'headquarter_id' => $this->sede->id]));

        $this->client = Client::create([
            'expediente' => '9812', 'nombre' => 'ANA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'ROJAS',
            'tipo_documento' => 'DNI', 'documento' => '47875565', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'comerciante', 'estado_civil' => 'soltero',
            'email' => 'ana.quispe@example.com',
            'direccion' => 'AV. UNO 100', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
    }

    private function credito(string $fecha): Credit
    {
        $credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => $fecha,
            'importe' => 5000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse($fecha)->addWeeks($i),
                'importe_cuota' => 2500, 'importe_interes' => 250, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        return $credit;
    }

    private function contratoEmitido(Credit $credit): void
    {
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $credit->id, 'tipo' => 'contrato', 'version' => 1,
            'snapshot' => ['prueba' => true], 'pdf_path' => "documentos/cliente-{$this->client->id}/contrato-{$credit->id}.pdf",
            'sha256' => hash('sha256', "c{$credit->id}"), 'estado' => 'emitido', 'generado_por' => auth()->id(),
        ]);
    }

    public function test_el_anexo_2_lista_los_creditos_con_el_mismo_color_que_el_anexo_1(): void
    {
        $viejo = $this->credito('2026-06-01');       // anterior sin contrato → rojo
        $contratado = $this->credito('2026-08-01');  // con contrato → negrita
        $nuevo = $this->credito('2026-10-01');       // el más reciente sin contrato → amarillo
        $this->contratoEmitido($contratado);

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo2');
        $html = $c->html();

        $this->assertMatchesRegularExpression('/credito-vigente"[^>]*wire:key="anx2-cred-'.$nuevo->id.'"/', $html);
        $this->assertMatchesRegularExpression('/credito-contratado"[^>]*wire:key="anx2-cred-'.$contratado->id.'"/', $html);
        $this->assertMatchesRegularExpression('/credito-anterior"[^>]*wire:key="anx2-cred-'.$viejo->id.'"/', $html);
        $this->assertMatchesRegularExpression('/name="creditoAnexo2"[^>]*value="'.$nuevo->id.'"[^>]*wire:model\.live="anexo2CreditoId"/', $html);
        $c->assertSee('Vigente del día')->assertSee('Con contrato')->assertSee('Anterior')
            ->assertDontSeeHtml('wire:model.live="anexo2CreditoId"><option');
    }

    public function test_el_monto_del_voucher_no_se_edita(): void
    {
        $this->credito('2026-10-01');

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalAnexo2');
        $this->assertMatchesRegularExpression('/<input[^>]*readonly[^>]*wire:model="anexo2Monto"/s', $c->html());
        $this->assertDoesNotMatchRegularExpression('/wire:model\.blur="anexo2Monto"/', $c->html());

        // El segundo voucher, igual.
        $c->set('anexo2DosVouchers', true);
        $this->assertMatchesRegularExpression('/<input[^>]*readonly[^>]*wire:model="anexo2Monto2"/s', $c->html());
    }
}
