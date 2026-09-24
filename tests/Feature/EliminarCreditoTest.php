<?php

namespace Tests\Feature;

use App\Livewire\Credits\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Credits\EliminadorCredito;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * "Eliminar" del listado de créditos (24/09/2026): borrado REAL con todo lo
 * suyo, documentos emitidos incluidos, en una transacción y con traza en la
 * auditoría. Antes fallaba por la clave foránea de documentos_cliente y
 * dejaba el crédito vivo sin cronograma (crédito 29429 en producción).
 */
class EliminarCreditoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'admin-elimina', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['creditos', 'creditos.eliminar']);

        $this->client = Client::create([
            'expediente' => '9020', 'nombre' => 'JOSE LUIS', 'apellido_pat' => 'MENESES', 'apellido_mat' => 'SOTO',
            'tipo_documento' => 'DNI', 'documento' => '41234567', 'sexo' => 'M', 'direccion' => 'AV. TEST 1',
            'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA', 'celular1' => '999999999',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);

        // Del día y registrado por el mismo usuario: es lo que el listado deja borrar sin permiso de histórico.
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => now()->toDateString(), 'importe' => 15000,
            'cuotas' => 24, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => $sede->id, 'user_id' => $this->user->id,
        ]);
        foreach (range(1, 24) as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => now()->addWeeks($n)->toDateString(),
                'importe_cuota' => 625, 'importe_interes' => 62.5, 'importe_excedente' => 0, 'importe_aplicado' => 0,
                'interes_aplicado' => 0, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
    }

    /** Anexo 1 (con correlativo), contrato y anexo 2 (con foto) emitidos, con sus PDF y una copia de impresión. */
    private function documentos(): array
    {
        $carpeta = "documentos/cliente-{$this->client->id}";
        $foto = "{$carpeta}/voucher-test.png";
        Storage::disk('public')->put($foto, 'PNG');
        $docs = [];
        foreach (['anexo1' => ['correlativo' => 'A1-0007'], 'contrato' => ['estado' => 'anulado'], 'anexo2' => ['snapshot' => ['imagen_path' => $foto]]] as $tipo => $extra) {
            $pdf = "{$carpeta}/{$tipo}-credito-{$this->credit->id}-v1.pdf";
            Storage::disk('public')->put($pdf, "%PDF-1.7 {$tipo} %%EOF");
            $docs[$tipo] = DocumentoCliente::create($extra + [
                'client_id' => $this->client->id, 'credit_id' => $this->credit->id, 'tipo' => $tipo,
                'modelo' => $tipo === 'contrato' ? 'a3' : null, 'version' => 1, 'snapshot' => ['prueba' => true],
                'pdf_path' => $pdf, 'sha256' => hash('sha256', $tipo), 'estado' => 'emitido', 'generado_por' => $this->user->id,
            ]);
        }
        // Copia de impresión ya generada para el contrato (sufijo -impresion-g300).
        Storage::disk('public')->put("{$carpeta}/contrato-credito-{$this->credit->id}-v1-impresion-g300.pdf", '%PDF-1.7 copia %%EOF');

        return $docs;
    }

    public function test_el_listado_borra_el_credito_con_documentos_cuotas_y_archivos_y_deja_traza(): void
    {
        $this->mundo();
        Storage::fake('public');
        $docs = $this->documentos();
        $id = $this->credit->id;
        $carpeta = "documentos/cliente-{$this->client->id}";

        Livewire::test(Index::class)
            ->call('delete', $id)
            ->assertDispatched('successAlert');

        $this->assertDatabaseMissing('credits', ['id' => $id]);
        $this->assertSame(0, CreditInstallment::where('credit_id', $id)->count());
        $this->assertSame(0, DocumentoCliente::where('credit_id', $id)->count());

        // Archivos: los tres PDF, la copia de impresión y la foto del voucher.
        Storage::disk('public')->assertMissing("{$carpeta}/anexo1-credito-{$id}-v1.pdf");
        Storage::disk('public')->assertMissing("{$carpeta}/contrato-credito-{$id}-v1.pdf");
        Storage::disk('public')->assertMissing("{$carpeta}/contrato-credito-{$id}-v1-impresion-g300.pdf");
        Storage::disk('public')->assertMissing("{$carpeta}/anexo2-credito-{$id}-v1.pdf");
        Storage::disk('public')->assertMissing("{$carpeta}/voucher-test.png");

        // Traza en la auditoría: qué documentos se fueron, con versión, hash y correlativo.
        $traza = DB::table('activity_log')->where('log_name', 'auditoria')->where('description', 'like', "Eliminó el crédito #{$id}%")->first();
        $this->assertNotNull($traza);
        $this->assertSame("Eliminó el crédito #{$id} con 3 documento(s) emitido(s)", $traza->description);
        $this->assertSame($this->user->id, (int) $traza->causer_id);
        $props = json_decode($traza->properties, true);
        $this->assertSame(24, $props['cuotas_borradas']);
        $this->assertSame(15000.0, (float) $props['importe']);
        $this->assertCount(3, $props['documentos']);
        $porTipo = array_column($props['documentos'], null, 'tipo');
        $this->assertSame('A1-0007', $porTipo['anexo1']['correlativo']);
        $this->assertSame('anulado', $porTipo['contrato']['estado']);
        $this->assertSame(hash('sha256', 'contrato'), $porTipo['contrato']['sha256']);
    }

    public function test_la_foto_del_voucher_se_conserva_si_otro_documento_la_usa(): void
    {
        $this->mundo();
        Storage::fake('public');
        $this->documentos();
        $foto = "documentos/cliente-{$this->client->id}/voucher-test.png";

        // Otro crédito del mismo cliente con un anexo 2 que apunta a la misma foto.
        $otro = Credit::create($this->credit->only(['client_id', 'fecha_prestamo', 'importe', 'cuotas', 'tipo_planilla', 'interes', 'situacion', 'estado', 'headquarter_id', 'user_id']));
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $otro->id, 'tipo' => 'anexo2', 'version' => 1,
            'snapshot' => ['imagen_path' => $foto], 'pdf_path' => null, 'sha256' => 'x', 'estado' => 'emitido', 'generado_por' => $this->user->id,
        ]);

        EliminadorCredito::eliminar($this->credit);

        Storage::disk('public')->assertExists($foto);
        $this->assertDatabaseHas('credits', ['id' => $otro->id]);
    }

    public function test_con_garantia_del_area_legal_no_borra_nada_y_lo_dice(): void
    {
        $this->mundo();
        Storage::fake('public');
        $this->documentos();
        Garantia::create([
            'credit_id' => $this->credit->id, 'client_id' => $this->client->id, 'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural', 'gps' => true, 'custodia' => false, 'monto_gravamen' => 15000, 'registrado_por' => $this->user->id,
        ]);

        Livewire::test(Index::class)
            ->call('delete', $this->credit->id)
            ->assertDispatched('errorAlert', fn ($name, $params) => str_contains($params['message'] ?? ($params[0]['message'] ?? ''), 'área legal'));

        $this->assertDatabaseHas('credits', ['id' => $this->credit->id]);
        $this->assertSame(24, CreditInstallment::where('credit_id', $this->credit->id)->count());
        $this->assertSame(3, DocumentoCliente::where('credit_id', $this->credit->id)->count());
        Storage::disk('public')->assertExists("documentos/cliente-{$this->client->id}/contrato-credito-{$this->credit->id}-v1.pdf");
    }

    public function test_si_algo_falla_a_mitad_no_queda_un_credito_sin_cronograma(): void
    {
        $this->mundo();
        Storage::fake('public');
        $this->documentos();
        $id = $this->credit->id;

        // Falla al borrar el segundo documento: la transacción debe deshacer TODO.
        $vistos = 0;
        DocumentoCliente::deleting(function () use (&$vistos) {
            if (++$vistos === 2) {
                throw new RuntimeException('falló a mitad');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            EliminadorCredito::eliminar($this->credit);
        } finally {
            DocumentoCliente::flushEventListeners();
            // Nada cambió: ni crédito, ni cuotas, ni documentos, ni archivos, ni auditoría.
            $this->assertDatabaseHas('credits', ['id' => $id]);
            $this->assertSame(24, CreditInstallment::where('credit_id', $id)->count());
            $this->assertSame(3, DocumentoCliente::where('credit_id', $id)->count());
            Storage::disk('public')->assertExists("documentos/cliente-{$this->client->id}/anexo1-credito-{$id}-v1.pdf");
            $this->assertSame(0, DB::table('activity_log')->where('description', 'like', "Eliminó el crédito #{$id}%")->count());
        }
    }
}
