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
 * 16/10/2026 (Antony, pestaña Documentos): el orden de trabajo es Contrato →
 * Anexo 1 → Anexo 2, tanto en los botones de generar como en el listado de
 * lo ya generado (dentro de cada crédito).
 */
class DocumentosOrdenTest extends TestCase
{
    use RefreshDatabase;

    public function test_botones_y_listado_van_contrato_anexo_1_anexo_2(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'orden-docs', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['clientes']);
        $client = Client::create([
            'expediente' => '9040', 'nombre' => 'LUIS', 'apellido_pat' => 'ORDEN', 'apellido_mat' => 'DOCS',
            'tipo_documento' => 'DNI', 'documento' => '41234569', 'sexo' => 'M', 'status' => 'active',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id,
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->toDateString(), 'importe' => 5000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id, 'user_id' => $user->id,
        ]);
        // Generados en el orden "equivocado": anexo 2, anexo 1 y al final el contrato.
        foreach (['anexo2', 'anexo1', 'contrato'] as $tipo) {
            DocumentoCliente::create([
                'client_id' => $client->id, 'credit_id' => $credit->id, 'tipo' => $tipo, 'modelo' => $tipo === 'contrato' ? 'a1' : null,
                'version' => 1, 'snapshot' => ['prueba' => true], 'pdf_path' => "x-{$tipo}.pdf", 'sha256' => hash('sha256', $tipo), 'estado' => 'emitido',
            ]);
        }
        // Una segunda versión del anexo 1 va después de la primera.
        DocumentoCliente::create([
            'client_id' => $client->id, 'credit_id' => $credit->id, 'tipo' => 'anexo1', 'version' => 2,
            'snapshot' => ['prueba' => true], 'pdf_path' => 'x-anexo1-v2.pdf', 'sha256' => hash('sha256', 'a1v2'), 'estado' => 'emitido',
        ]);

        $html = Livewire::test(Documentos::class, ['id' => $client->id])->html();

        $botones = [strpos($html, 'Generar Contrato'), strpos($html, 'Generar Anexo 1'), strpos($html, 'Generar Anexo 2')];
        $this->assertNotContains(false, $botones);
        $this->assertTrue($botones[0] < $botones[1] && $botones[1] < $botones[2], 'botones: Contrato → Anexo 1 → Anexo 2');

        // El botón Anular de cada fila lleva "tipo vN": sirve para leer el orden del listado.
        preg_match_all('/¿Anular (.+?) v(\d) del crédito/u', $html, $m, PREG_SET_ORDER);
        $orden = array_map(fn ($f) => $f[1].' v'.$f[2], $m);
        $t = DocumentoCliente::TIPOS;
        $this->assertSame([$t['contrato'].' v1', $t['anexo1'].' v1', $t['anexo1'].' v2', $t['anexo2'].' v1'], $orden, 'listado: Contrato, Anexo 1 (v1, v2), Anexo 2');
    }
}
