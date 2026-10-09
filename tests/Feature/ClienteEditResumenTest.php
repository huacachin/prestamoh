<?php

namespace Tests\Feature;

use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /clients/{id}/edit "debe haber un resumen indicando
 * si tiene vehículos, contratos, y abajo en lista los copropietarios y a qué
 * vehículos pertenecen". Se ve en cualquier pestaña.
 */
class ClienteEditResumenTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private function cliente(string $exp, string $doc): Client
    {
        return Client::create([
            'expediente' => $exp, 'nombre' => 'Carla', 'apellido_pat' => 'Ore', 'apellido_mat' => 'Barrientos',
            'tipo_documento' => 'DNI', 'documento' => $doc, 'sexo' => 'F', 'status' => 'active', 'headquarter_id' => $this->sede->id,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'resumen-tester', 'headquarter_id' => $this->sede->id]));
    }

    public function test_sin_nada_el_resumen_dice_ninguno(): void
    {
        $c = $this->cliente('1399', '41001463');
        $html = Livewire::test(Edit::class, ['id' => $c->id])->html();

        $this->assertStringContainsString('resumen-cliente', $html);
        $this->assertSame(3, substr_count($html, '<span class="text-muted">ninguno</span>'), 'vehículos, contratos (franja) y copropietarios (sección)');
        $this->assertStringNotContainsString('Es copropietario en:', $html);
        // La sección de copropietarios va debajo de Dirección Principal y antes de los botones.
        $this->assertLessThan(strpos($html, 'seccion-copropietarios'), strpos($html, '>Dirección Principal<'));
        $this->assertLessThan(strpos($html, 'Regresar'), strpos($html, 'seccion-copropietarios'));
        $this->assertStringContainsString('agregar desde Vehículos', $html);
    }

    public function test_con_vehiculos_contratos_y_copropietarios_lo_resume_todo(): void
    {
        $c = $this->cliente('1399', '41001463');
        $v1 = Vehiculo::create(['client_id' => $c->id, 'placa' => 'BVJ155', 'marca' => 'TOYOTA', 'valor' => 20000]);
        $v2 = Vehiculo::create(['client_id' => $c->id, 'placa' => 'AWI132', 'marca' => 'NISSAN', 'valor' => 15000]);
        $copro = Client::create([
            'nombre' => 'Joselyn', 'apellido_pat' => 'Escobar', 'apellido_mat' => 'Suma', 'tipo_documento' => 'DNI', 'documento' => '41001467',
            'sexo' => 'F', 'status' => 'active', 'es_relacionado' => true, 'headquarter_id' => $this->sede->id,
            'direccion' => 'Jr. Los Pinos 123', 'distrito' => 'Ate', 'celular1' => '999111222',
        ]);
        $otro = Client::create([
            'nombre' => 'Pedro', 'apellido_pat' => 'Solo', 'apellido_mat' => 'Uno', 'tipo_documento' => 'DNI', 'documento' => '41001468',
            'sexo' => 'M', 'status' => 'active', 'es_relacionado' => true, 'headquarter_id' => $this->sede->id,
        ]);
        $v1->copropietarios()->attach($copro->id, ['rol' => 'copropietario']);
        $v2->copropietarios()->attach($copro->id, ['rol' => 'copropietario']);
        $v2->copropietarios()->attach($otro->id, ['rol' => 'copropietario']);

        $credit = Credit::create([
            'client_id' => $c->id, 'fecha_prestamo' => '2026-09-01', 'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1,
            'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([[1, 'emitido'], [2, 'anulado'], [3, 'emitido']] as [$v, $estado]) {
            DocumentoCliente::create([
                'client_id' => $c->id, 'credit_id' => $credit->id, 'tipo' => 'contrato', 'modelo' => 'a2', 'version' => $v,
                'snapshot' => ['prueba' => true], 'pdf_path' => "c-{$v}.pdf", 'sha256' => str_repeat('a', 64), 'estado' => $estado,
            ]);
        }
        // Un anexo no cuenta como contrato.
        DocumentoCliente::create([
            'client_id' => $c->id, 'credit_id' => $credit->id, 'tipo' => 'anexo1', 'version' => 1,
            'snapshot' => ['prueba' => true], 'pdf_path' => 'a.pdf', 'sha256' => str_repeat('b', 64), 'estado' => 'emitido',
        ]);

        $comp = Livewire::test(Edit::class, ['id' => $c->id]);
        // Livewire 4 mete marcas <!--[if BLOCK]><![endif]--> entre los @if: se quitan para leer el texto seguido.
        $html = preg_replace('/<!--.*?-->/s', '', $comp->html());

        $this->assertStringContainsString('2 ·', $html);
        $this->assertStringContainsString('AWI132 (NISSAN), BVJ155 (TOYOTA)', $html, 'placas ordenadas con su marca');
        $this->assertStringContainsString('2 emitidos (1 anulado)', $html);
        $this->assertStringContainsString('último: v3 del '.now()->format('d/m/Y'), $html);
        $this->assertStringContainsString("(crédito #{$credit->id})", $html);
        // Copropietarios en su sección (tabla ancha): nombre, DNI, celular, vehículos y ficha.
        $this->assertStringContainsString('tabla-copropietarios', $html);
        $this->assertStringContainsString('<td class="fw-semibold">Escobar Suma Joselyn</td>', $html);
        $this->assertStringContainsString('<td class="text-center">41001467</td>', $html);
        $this->assertStringContainsString('<td class="text-center">999111222</td>', $html);
        $this->assertStringContainsString('<td>Jr. Los Pinos 123, Ate</td>', $html, 'la dirección del copropietario');
        $this->assertStringContainsString('<th>Dirección</th>', $html);
        $this->assertStringContainsString('<td>AWI132, BVJ155</td>', $html, 'en el orden de las placas');
        $this->assertStringContainsString('<td class="fw-semibold">Solo Uno Pedro</td>', $html);
        $this->assertStringContainsString('<td>AWI132</td>', $html);
        $this->assertStringContainsString(route('clients.edit', $copro->id), $html);
        $this->assertStringNotContainsString('<b>Copropietarios:</b>', $html, 'ya no va en la franja de arriba');

        // La franja de arriba (vehículos y contratos) se ve en las otras pestañas.
        $comp->set('tab', 'gps')->assertSee('2 emitidos')->assertSee('AWI132 (NISSAN)');

        // La ficha de la copropietaria indica de quién es copropietaria.
        Livewire::test(Edit::class, ['id' => $copro->id])
            ->assertSee('Es copropietario en:')
            ->assertSee('BVJ155, AWI132 de Ore Barrientos Carla');
    }
}
