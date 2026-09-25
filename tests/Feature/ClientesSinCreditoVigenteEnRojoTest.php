<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: en el listado de clientes, la fila va COMPLETA en rojo cuando el
 * cliente no tiene ningún crédito vigente, como el legacy (cliente.php): el
 * cliente nuevo que todavía no tiene crédito y el que ya canceló todos. Antes
 * el nuevo no se marcaba. Aplica a la tabla, a las tarjetas móviles y al Excel.
 */
class ClientesSinCreditoVigenteEnRojoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    private function cliente(string $nombre): Client
    {
        static $n = 0;
        $n++;

        return Client::create([
            'expediente' => (string) (2000 + $n), 'nombre' => $nombre, 'apellido_pat' => 'PRUEBA', 'apellido_mat' => 'ROJO',
            'tipo_documento' => 'DNI', 'documento' => (string) (41000000 + $n), 'sexo' => 'M',
            'headquarter_id' => $this->sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
    }

    private function credito(Client $c, string $situacion): Credit
    {
        return Credit::create([
            'client_id' => $c->id, 'fecha_prestamo' => '2026-07-01', 'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3,
            'interes' => 10, 'situacion' => $situacion, 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
    }

    private function mundo(): array
    {
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'rojo-tester', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $nuevo = $this->cliente('NUEVO');                       // sin ningún crédito → rojo
        $activo = $this->cliente('ACTIVO');                     // con crédito vigente → normal
        $this->credito($activo, 'Activo');
        $cancelado = $this->cliente('CANCELADO');               // tuvo y canceló todos → rojo
        $this->credito($cancelado, 'Cancelado');

        return [$nuevo, $activo, $cancelado];
    }

    /** El <tr> (o la tarjeta) que contiene el nombre. */
    private function bloqueDe(string $html, string $nombre, string $inicio = '<tr'): string
    {
        $pos = strpos($html, $nombre);
        $this->assertNotFalse($pos, "no se encontró {$nombre}");

        return substr($html, strrpos(substr($html, 0, $pos), $inicio), 160);
    }

    public function test_tabla_el_nuevo_y_el_que_cancelo_todo_van_en_rojo_y_el_activo_no(): void
    {
        [$nuevo, $activo, $cancelado] = $this->mundo();

        $html = Livewire::test(Index::class)->html();
        $this->assertStringContainsString('class="sin-vigente"', $this->bloqueDe($html, 'PRUEBA ROJO NUEVO'));
        $this->assertStringContainsString('class="sin-vigente"', $this->bloqueDe($html, 'PRUEBA ROJO CANCELADO'));
        $this->assertStringNotContainsString('sin-vigente', $this->bloqueDe($html, 'PRUEBA ROJO ACTIVO'));
        $this->assertStringContainsString('tr.sin-vigente > td', $html, 'la regla CSS pinta las celdas');
        $this->assertStringContainsString('color: #FF0000', $html, 'rojo del legacy');
    }

    public function test_tarjetas_moviles_mismo_criterio(): void
    {
        $this->mundo();

        $html = Livewire::test(Index::class)->set('movil', true)->html();
        $this->assertStringContainsString('sin-vigente', $this->bloqueDe($html, 'PRUEBA ROJO NUEVO', '<div class="card mb-2'));
        $this->assertStringContainsString('sin-vigente', $this->bloqueDe($html, 'PRUEBA ROJO CANCELADO', '<div class="card mb-2'));
        $this->assertStringNotContainsString('sin-vigente', $this->bloqueDe($html, 'PRUEBA ROJO ACTIVO', '<div class="card mb-2'));
    }

    public function test_excel_pinta_las_mismas_filas_en_rojo(): void
    {
        $this->mundo();

        $xls = $this->get(route('exports.clients'))->assertOk()->getContent();
        $this->assertStringContainsString('<font color="#FF0000">PRUEBA ROJO NUEVO</font>', $xls);
        $this->assertStringContainsString('<font color="#FF0000">PRUEBA ROJO CANCELADO</font>', $xls);
        $this->assertStringNotContainsString('<font color="#FF0000">PRUEBA ROJO ACTIVO</font>', $xls);
        $this->assertStringNotContainsString('#dc3545', $xls);
    }
}
