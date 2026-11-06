<?php

namespace Tests\Feature;

use App\Livewire\Credits\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Credits\EliminadorCredito;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony, pestaña Préstamos): "cuando se elimine un préstamo
 * creado, se vuelva a recontar el código de préstamo eliminado" y "bloquear
 * la opción de editar el código de préstamo, de color plomo".
 *
 * 06/11/2026 (Antony): si hoy se eliminó un crédito del medio, al crear otro "pregunte si
 * quiere seguir con el siguiente correlativo o usar el hueco reciente; OJO solo del mismo día".
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
        // 06/11: eliminado HOY, el siguiente préstamo toma su código (abajo, los tests del hueco del día).
        Livewire::test(Create::class)->assertSet('codpre_', 500);
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

    /** Créditos 500 y 501 con el 500 (el del medio) eliminado hoy. */
    private function conElDelMedioEliminadoHoy(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $medio = $this->credito(500);
        $this->credito(501);
        EliminadorCredito::eliminar($medio);
    }

    /** El formulario de nuevo préstamo listo para guardar (solo falta elegir el código si se pregunta). */
    private function formulario(): Testable
    {
        return Livewire::test(Create::class, ['clientId' => $this->client->id])
            ->set('seletipl', '1')->set('impopres', 1000)->set('cuot', 4)->set('inte', 10)->set('nomasesores', 'codigo-tester');
    }

    private function correlativo(): int
    {
        return (int) DB::table('correlativos')->where('tipo', 'Credito')->value('correl');
    }

    public function test_si_hoy_se_elimino_uno_del_medio_el_nuevo_toma_su_codigo_y_el_contador_no_baja(): void
    {
        $this->conElDelMedioEliminadoHoy();

        $this->formulario()
            ->assertSet('codpre_', 500)
            ->assertSee('Este préstamo toma el código')
            ->assertSee('CODIGO TEST ANA, S/ 1,000.00')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('credits.show', 500));

        $this->assertSame($this->client->id, (int) Credit::findOrFail(500)->client_id);
        $this->assertFalse(Credit::whereKey(502)->exists(), 'no se pasa al siguiente');
        $this->assertSame(501, $this->correlativo(), 'el contador no baja');
        $this->assertTrue(Activity::where('description', 'Creó el crédito #500 con el código del crédito eliminado hoy')->exists());

        // Usado el código, sigue la serie.
        $this->formulario()->assertSet('codigosLiberados', [])->assertSet('codpre_', 502)->assertDontSee('Este préstamo toma el código');
    }

    public function test_con_varios_codigos_libres_del_dia_los_usa_de_menor_a_mayor_antes_de_seguir_la_serie(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 502, 'created_at' => now(), 'updated_at' => now()]);
        $a = $this->credito(500);
        $b = $this->credito(501);
        $this->credito(502);
        EliminadorCredito::eliminar($b);
        EliminadorCredito::eliminar($a);

        $this->formulario()->assertSet('codpre_', 500)->assertSee('También quedan libres de hoy: #501.')
            ->call('save')->assertHasNoErrors();
        $this->formulario()->assertSet('codpre_', 501)->call('save')->assertHasNoErrors();
        $this->formulario()->assertSet('codpre_', 503);
        $this->assertSame(502, $this->correlativo());
    }

    public function test_un_codigo_eliminado_ayer_ya_no_se_ofrece(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $medio = $this->credito(500);
        $this->credito(501);
        Carbon::setTestNow(now()->subDay()->setTime(17, 0));
        EliminadorCredito::eliminar($medio);
        Carbon::setTestNow();

        $this->formulario()->assertSet('codigosLiberados', [])->assertDontSee('Este préstamo toma el código')
            ->call('save')->assertHasNoErrors();
        $this->assertTrue(Credit::whereKey(502)->exists(), 'el de ayer no cuenta: sigue la serie');
        $this->assertFalse(Credit::whereKey(500)->exists());
    }

    public function test_al_eliminar_el_ultimo_no_hay_hueco_porque_su_codigo_ya_es_el_siguiente(): void
    {
        $this->mundo();
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Credito', 'correl' => 501, 'created_at' => now(), 'updated_at' => now()]);
        $this->credito(500);
        EliminadorCredito::eliminar($this->credito(501));

        $this->formulario()->assertSet('codigosLiberados', [])->assertSet('codpre_', 501);
    }

    public function test_si_el_codigo_cambia_mientras_se_llena_el_formulario_avisa_el_nuevo_y_no_guarda_hasta_volver_a_guardar(): void
    {
        $this->conElDelMedioEliminadoHoy();

        // Otro usuario tomó el 500 mientras tanto: el formulario pasa al 502 y avisa.
        $comp = $this->formulario()->assertSet('codpre_', 500);
        $this->credito(500);
        $comp->call('save')->assertHasErrors('codpre_')->assertSee('El código del préstamo cambió a #502')->assertSet('codpre_', 502);
        $this->assertFalse(Credit::whereKey(502)->exists(), 'no se guarda con un número que no se vio');
        $comp->call('save')->assertHasNoErrors();
        $this->assertTrue(Credit::whereKey(502)->exists());

        // Al revés: se abrió con el siguiente (503) y mientras tanto se eliminó hoy uno del medio.
        $comp = $this->formulario()->assertSet('codpre_', 503);
        EliminadorCredito::eliminar(Credit::findOrFail(501));
        $comp->call('save')->assertHasErrors('codpre_')->assertSee('El código del préstamo cambió a #501')->assertSet('codpre_', 501);
        $comp->call('save')->assertHasNoErrors();
        $this->assertTrue(Credit::whereKey(501)->exists());
        $this->assertFalse(Credit::whereKey(503)->exists());
    }
}
