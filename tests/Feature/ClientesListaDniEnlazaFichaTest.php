<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /clients el DNI también lleva a la ficha del cliente
 * (antes abría /credits/create). Cae en la pestaña Préstamos, donde está el
 * botón "Nuevo préstamo", así que lo de antes sigue a un clic.
 */
class ClientesListaDniEnlazaFichaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_dni_del_listado_enlaza_a_la_ficha_en_la_pestana_prestamos(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'dni-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['clientes']);
        $client = Client::create([
            'expediente' => '27', 'nombre' => 'Fernando', 'apellido_pat' => 'Obregon', 'apellido_mat' => 'Lopez',
            'tipo_documento' => 'DNI', 'documento' => '41849995', 'sexo' => 'M', 'status' => 'active',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id,
        ]);
        $enlace = route('clients.edit', ['id' => $client->id, 'tab' => 'prestamos']);

        // Tabla de escritorio.
        $html = Livewire::test(Index::class)->html();
        $this->assertMatchesRegularExpression('~<a href="'.preg_quote($enlace, '~').'" style="color: inherit; text-decoration: none;">\s*41849995\s*</a>~', $html);
        $this->assertStringNotContainsString(route('credits.create', $client->id), $html, 'el DNI ya no abre el alta de crédito');
        $this->assertStringContainsString('<a href="'.route('clients.edit', $client->id).'" style="color: inherit; text-decoration: none;">', $html, 'el nombre sigue yendo a Datos');

        // Tarjetas en móvil.
        $movil = Livewire::test(Index::class)->set('movil', true)->html();
        $this->assertStringContainsString('<b>DNI:</b>', $movil);
        $this->assertStringContainsString('<a href="'.$enlace.'" style="color: inherit;">41849995</a>', $movil);
        $this->assertStringNotContainsString(route('credits.create', $client->id), $movil);
    }
}
