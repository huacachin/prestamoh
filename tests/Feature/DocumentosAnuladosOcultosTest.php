<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: en la ficha de documentos del cliente los ANULADOS se ocultan
 * por defecto (emitir, anular y volver a emitir es el uso normal y la lista
 * se llenaba de tachados). No se borran: son la constancia de lo entregado.
 */
class DocumentosAnuladosOcultosTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'docs-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '9030', 'nombre' => 'ANA', 'apellido_pat' => 'ROJAS', 'apellido_mat' => 'PAZ',
            'tipo_documento' => 'DNI', 'documento' => '42345678', 'sexo' => 'F', 'direccion' => 'AV. TEST 2',
            'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA', 'celular1' => '988888888',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => now()->toDateString(), 'importe' => 5000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => $sede->id, 'user_id' => $user->id,
        ]);
    }

    private function documento(string $tipo, int $version, string $estado): DocumentoCliente
    {
        return DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $this->credit->id, 'tipo' => $tipo, 'version' => $version,
            'snapshot' => ['prueba' => true], 'pdf_path' => "documentos/cliente-{$this->client->id}/{$tipo}-credito-{$this->credit->id}-v{$version}.pdf",
            'sha256' => hash('sha256', "{$tipo}{$version}"), 'estado' => $estado, 'generado_por' => auth()->id(),
        ]);
    }

    public function test_los_anulados_se_ocultan_por_defecto_y_se_muestran_a_pedido(): void
    {
        $this->mundo();
        $this->documento('anexo1', 1, 'anulado');
        $this->documento('anexo1', 2, 'anulado');
        $this->documento('anexo1', 3, 'emitido');

        $comp = Livewire::test(Documentos::class, ['id' => $this->client->id]);

        // Por defecto: solo el vigente, sin filas tachadas, y el enlace con el conteo.
        $comp->assertSee('v3')
            ->assertDontSeeHtml('line-through')
            ->assertSee('Ver 2 anulados');

        // Al pedirlos: aparecen tachados y el enlace cambia.
        $comp->set('verAnulados', true)
            ->assertSeeHtml('line-through')
            ->assertSee('v1')
            ->assertSee('v2')
            ->assertSee('Ocultar los anulados');
    }

    public function test_si_solo_hay_anulados_lo_dice_en_vez_de_fingir_que_no_hay_documentos(): void
    {
        $this->mundo();
        $this->documento('contrato', 1, 'anulado');

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->assertSee('no tiene documentos vigentes')
            ->assertSee('Ver 1 anulado')
            ->assertDontSee('aún no tiene documentos generados');
    }

    public function test_sin_anulados_no_aparece_el_enlace(): void
    {
        $this->mundo();
        $this->documento('anexo1', 1, 'emitido');

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->assertDontSee('anulado');
    }
}
