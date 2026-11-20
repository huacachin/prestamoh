<?php

namespace Tests\Feature;

use App\Livewire\Clients\Prestamos;
use App\Livewire\Credits\Edit;
use App\Livewire\Credits\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Support\EdicionCredito;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 20/11/2026 (Antony, tras hablar con el cliente): "la opción de editar el préstamo solo se pueda hacer
 * hasta que todos los pasos de ese préstamo se hayan completado, luego que se bloquee". Decidido con
 * Antony: se bloquea al EMITIR EL CONTRATO, para todos (también director y contabilidad), y se suma a la
 * regla de siempre (solo el día del registro, salvo quien edita el histórico). Anular el contrato lo
 * vuelve a dejar editable.
 */
class EdicionPrestamoBloqueoTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-20 10:00:00');
        $this->seed(PermissionCatalogSeeder::class);
        $sede = Headquarter::create(['name' => 'Sede Bloqueo', 'status' => 'active']);
        $this->client = Client::create(['nombre' => 'Cliente', 'apellido_pat' => 'Bloqueo', 'documento' => '40998877', 'zona' => 'SIGM.S', 'status' => 'active', 'headquarter_id' => $sede->id]);
    }

    /** @param  list<string>  $permisos */
    private function usuario(string $username, array $permisos): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->givePermissionTo($permisos);
        $this->actingAs($user);

        return $user;
    }

    private function credito(string $fecha): Credit
    {
        return Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => $fecha, 'importe' => 1000, 'cuotas' => 10,
            'tipo_planilla' => 1, 'interes' => 20, 'interes_total' => 200, 'moneda' => 'PEN', 'situacion' => 'Activo', 'estado' => 1,
        ]);
    }

    private function contrato(Credit $credit, string $estado = 'emitido'): int
    {
        return DB::table('documentos_cliente')->insertGetId([
            'client_id' => $credit->client_id, 'credit_id' => $credit->id, 'tipo' => 'contrato', 'estado' => $estado,
            'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_el_prestamo_de_hoy_se_edita_hasta_que_se_emite_su_contrato(): void
    {
        $this->usuario('operador-bloqueo', ['creditos']);
        $credit = $this->credito('2026-11-20');

        Livewire::test(Edit::class, ['id' => $credit->id])->assertOk();

        $this->contrato($credit);

        Livewire::test(Edit::class, ['id' => $credit->id])->assertForbidden();
        $this->assertSame(EdicionCredito::MOTIVO_CONTRATO, EdicionCredito::motivoBloqueo(auth()->user(), $credit));
    }

    public function test_con_el_contrato_emitido_tampoco_lo_edita_quien_edita_el_historico(): void
    {
        $this->usuario('director-bloqueo', ['creditos', 'caja.editar-historico']);
        $antiguo = $this->credito('2026-10-01');

        Livewire::test(Edit::class, ['id' => $antiguo->id])->assertOk();

        $this->contrato($antiguo);

        Livewire::test(Edit::class, ['id' => $antiguo->id])->assertForbidden();
    }

    public function test_anular_el_contrato_vuelve_a_dejar_editable_el_prestamo(): void
    {
        $this->usuario('director-anula', ['creditos', 'caja.editar-historico']);
        $credit = $this->credito('2026-11-20');
        $contrato = $this->contrato($credit);
        Livewire::test(Edit::class, ['id' => $credit->id])->assertForbidden();

        DB::table('documentos_cliente')->where('id', $contrato)->update(['estado' => 'anulado']);

        Livewire::test(Edit::class, ['id' => $credit->id])->assertOk();
    }

    public function test_si_el_contrato_se_emite_con_la_edicion_abierta_no_deja_guardar(): void
    {
        $this->usuario('operador-abierto', ['creditos']);
        $credit = $this->credito('2026-11-20');
        $edicion = Livewire::test(Edit::class, ['id' => $credit->id])->set('importe', 2500);

        $this->contrato($credit);

        $edicion->call('update')->assertForbidden();
        $this->assertEqualsWithDelta(1000, (float) $credit->fresh()->importe, 0.01);
    }

    public function test_la_regla_del_dia_sigue_vigente_un_prestamo_de_ayer_sin_contrato_no_lo_edita_el_operador(): void
    {
        $user = $this->usuario('operador-ayer', ['creditos']);
        $ayer = $this->credito('2026-11-19');

        Livewire::test(Edit::class, ['id' => $ayer->id])->assertForbidden();
        $this->assertSame(EdicionCredito::MOTIVO_DIA, EdicionCredito::motivoBloqueo($user, $ayer));
    }

    public function test_un_prestamo_sin_contrato_se_sigue_guardando_como_siempre(): void
    {
        $this->usuario('operador-guarda', ['creditos']);
        $credit = $this->credito('2026-11-20');

        Livewire::test(Edit::class, ['id' => $credit->id])
            ->set('importe', 2500)
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('credits.show', $credit->id));
        $this->assertEqualsWithDelta(2500, (float) $credit->fresh()->importe, 0.01);
    }

    public function test_editado_desde_el_paso_a_paso_vuelve_a_la_ficha_del_cliente_en_ese_prestamo(): void
    {
        $this->usuario('operador-flujo', ['creditos', 'clientes']);
        $credit = $this->credito('2026-11-20');
        $ficha = route('clients.edit', ['id' => $this->client->id, 'prestamo' => $credit->id, 'tab' => 'prestamos']);

        Livewire::withQueryParams(['desde' => 'expediente'])
            ->test(Edit::class, ['id' => $credit->id])
            ->assertSeeHtml('href="'.e($ficha).'"')
            ->call('update')
            ->assertRedirect($ficha);
    }

    public function test_la_ficha_del_credito_ofrece_editar_solo_mientras_no_hay_contrato(): void
    {
        $this->usuario('operador-ficha', ['creditos', 'clientes']);
        $credit = $this->credito('2026-11-20');

        Livewire::test(Show::class, ['id' => $credit->id])
            ->assertSeeHtml('href="'.route('credits.edit', $credit->id).'"')
            ->assertSee('Pasos del préstamo')
            ->assertDontSee('Contrato emitido: para editar, anúlalo antes.');

        $this->contrato($credit);

        Livewire::test(Show::class, ['id' => $credit->id])
            ->assertDontSeeHtml('href="'.route('credits.edit', $credit->id).'"')
            ->assertSee('Contrato emitido: para editar, anúlalo antes.');
    }

    public function test_la_pestana_prestamos_ofrece_editar_el_de_hoy_y_marca_bloqueado_el_que_tiene_contrato(): void
    {
        $this->usuario('operador-pestana', ['creditos', 'clientes']);
        $editable = $this->credito('2026-11-20');
        $conContrato = $this->credito('2026-11-20');
        $this->contrato($conContrato);
        $deAyer = $this->credito('2026-11-19');

        $html = Livewire::test(Prestamos::class, ['id' => $this->client->id])->html();
        $fila = fn (Credit $c): string => explode('</tr>', explode('wire:key="prestamo-'.$c->id.'"', $html)[1])[0];

        $this->assertStringContainsString(e(route('credits.edit', ['id' => $editable->id, 'desde' => 'expediente'])), $fila($editable));
        $this->assertStringNotContainsString('credits/'.$conContrato->id.'/edit', $fila($conContrato));
        $this->assertStringContainsString('Edición bloqueada: contrato emitido', $fila($conContrato));
        // El de ayer no es editable por la regla del día: ni botón ni candado (no es por el contrato).
        $this->assertStringNotContainsString('credits/'.$deAyer->id.'/edit', $fila($deAyer));
        $this->assertStringNotContainsString('Edición bloqueada', $fila($deAyer));
    }
}
