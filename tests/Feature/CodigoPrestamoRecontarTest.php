<?php

namespace Tests\Feature;

use App\Livewire\Credits\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Credits\EliminadorCredito;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony, pestaña Préstamos): "cuando se elimine un préstamo
 * creado, se vuelva a recontar el código de préstamo eliminado" y "bloquear
 * la opción de editar el código de préstamo, de color plomo".
 */
class CodigoPrestamoRecontarTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private function mundo(): void
    {
        Headquarter::forceCreate(['id' => 1, 'name' => 'Principal', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'codigo-tester', 'headquarter_id' => 1]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['creditos', 'creditos.eliminar']);
        $this->client = Client::create([
            'expediente' => '9030', 'nombre' => 'ANA', 'apellido_pat' => 'CODIGO', 'apellido_mat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '41234568', 'sexo' => 'F', 'status' => 'active',
            'headquarter_id' => 1, 'asesor_id' => $user->id,
        ]);
    }

    private function credito(int $id): Credit
    {
        return Credit::forceCreate([
            'id' => $id, 'client_id' => $this->client->id, 'fecha_prestamo' => now()->toDateString(), 'importe' => 1000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => 1, 'user_id' => auth()->id(),
        ]);
    }

    public function test_al_eliminar_el_ultimo_credito_su_codigo_se_vuelve_a_ofrecer(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $this->credito(500);
        $ultimo = $this->credito(501);
        Livewire::test(Create::class)->assertSet('codpre_', 502);

        EliminadorCredito::eliminar($ultimo);

        $this->assertSame(500, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'), 'el correlativo retrocede al mayor que queda');
        Livewire::test(Create::class)->assertSet('codpre_', 501, 'el siguiente crédito toma el código liberado');
    }

    public function test_al_eliminar_uno_del_medio_el_correlativo_no_cambia(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $medio = $this->credito(500);
        $this->credito(501);

        EliminadorCredito::eliminar($medio);

        $this->assertSame(501, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'), 'el hueco se queda; no se reordena');
        Livewire::test(Create::class)->assertSet('codpre_', 502);
    }

    /**
     * 23/10/2026: en producción hay un crédito migrado fuera de la serie (#87421).
     * Con max(id) el contador no bajaba y el siguiente salía 29495 tras borrar el 29494.
     */
    public function test_un_codigo_migrado_fuera_de_la_serie_no_impide_reusar_el_del_ultimo_eliminado(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $this->credito(499);
        $penultimo = $this->credito(500);
        $ultimo = $this->credito(501);
        $this->credito(87421); // el migrado del legacy, muy por encima del correlativo

        EliminadorCredito::eliminar($ultimo);
        $this->assertSame(500, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'));
        Livewire::test(Create::class)->assertSet('codpre_', 501, 'el siguiente sigue desde el código eliminado');

        EliminadorCredito::eliminar($penultimo);
        $this->assertSame(499, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'), 'borrar el nuevo último también retrocede');
        Livewire::test(Create::class)->assertSet('codpre_', 500);
    }

    public function test_el_recuento_nunca_sube_el_contador_y_un_hueco_del_medio_se_queda_aunque_haya_codigos_fuera_de_serie(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $medio = $this->credito(500);
        $this->credito(501);
        $this->credito(87421);

        EliminadorCredito::eliminar($medio);
        $this->assertSame(501, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'), 'el hueco del medio se queda');

        DB::table('correlativos')->where('tipo', 'Credito')->update(['correl' => 300]);
        $this->assertSame(0, EliminadorCredito::recontarCorrelativo(), 'sin códigos por debajo del contador no hay a qué bajar');
        $this->assertSame(300, (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl'), 'y nunca lo sube');
    }

    public function test_el_codigo_de_prestamo_no_se_edita_y_va_en_plomo(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 700, 'created_at' => now(), 'updated_at' => now()]);

        $html = Livewire::test(Create::class)->assertSet('codpre_', 701)->html();
        $this->assertMatchesRegularExpression('/<input type="number" name="codpre_"[^>]*class="form-control form-control-sm bg-light[^"]*"[^>]*wire:model\.defer="codpre_" readonly tabindex="-1"/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="codpre_"[^>]*background-color:yellow/', $html, 'el código ya no va en amarillo (otros campos sí)');
    }
}
