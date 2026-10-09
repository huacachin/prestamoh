<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index;
use App\Livewire\Clients\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Audit;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): la persona relacionada (copropietario) queda referenciada
 * al préstamo en el que es copropietaria —su ficha lo dice— y, tras varias
 * vueltas, el listado de clientes NO la lista: el titular que tiene
 * copropietarios lleva un icono de varios usuarios al lado del nombre y, al
 * pasar el mouse, el tooltip dice quiénes son y de qué vehículo. El enlace al
 * crédito es exacto cuando el contrato guardó al codeudor (codeudor_client_id);
 * si no, por el vehículo compartido. Si saca su propio crédito, pasa a titular.
 */
class CopropietarioReferenciadoAlPrestamoTest extends TestCase
{
    use RefreshDatabase;

    private User $asesor;

    private Client $titular;

    private Client $copro;

    private Vehiculo $vehiculo;

    private Credit $credito;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->asesor = User::factory()->create(['username' => 'guilmer', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->asesor);
        $this->seed(PermissionCatalogSeeder::class);
        $this->asesor->givePermissionTo(['clientes', 'creditos']);

        $this->titular = Client::create([
            'expediente' => '161', 'nombre' => 'Ingrid Madeleine', 'apellido_pat' => 'Mandujano', 'apellido_mat' => 'Raraz',
            'tipo_documento' => 'DNI', 'documento' => '41000173', 'sexo' => 'F', 'status' => 'active',
            'zona' => 'SIGM.S', 'giro' => 'Min.-B8T492', 'headquarter_id' => $sede->id, 'asesor_id' => $this->asesor->id,
        ]);
        $this->copro = Client::create([
            'nombre' => 'Miguel Alcides', 'apellido_pat' => 'Mejia', 'apellido_mat' => 'Villanueva',
            'tipo_documento' => 'DNI', 'documento' => '41001458', 'sexo' => 'M', 'status' => 'active',
            'es_relacionado' => true, 'headquarter_id' => $sede->id,
        ]);
        $this->vehiculo = Vehiculo::create(['client_id' => $this->titular->id, 'placa' => 'B8T492', 'marca' => 'TOYOTA', 'valor' => 20000]);
        $this->vehiculo->copropietarios()->attach($this->copro->id, ['rol' => 'copropietario']);

        $this->credito = $this->credito('2026-09-01');
    }

    private function credito(string $fecha): Credit
    {
        return Credit::create([
            'client_id' => $this->titular->id, 'fecha_prestamo' => $fecha, 'importe' => 5000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => $this->titular->headquarter_id, 'user_id' => $this->asesor->id,
        ]);
    }

    private function contrato(Credit $credit, ?int $codeudorId = null, string $estado = 'emitido'): DocumentoCliente
    {
        return DocumentoCliente::create([
            'client_id' => $this->titular->id, 'credit_id' => $credit->id, 'codeudor_client_id' => $codeudorId,
            'tipo' => 'contrato', 'modelo' => 'a3', 'version' => DocumentoCliente::where('credit_id', $credit->id)->max('version') + 1,
            'snapshot' => ['prueba' => true], 'pdf_path' => "x-{$credit->id}.pdf", 'sha256' => str_repeat('a', 64), 'estado' => $estado,
        ]);
    }

    public function test_el_listado_no_lista_al_copropietario_y_marca_al_titular_con_el_icono_y_el_tooltip(): void
    {
        $this->mundo();

        $comp = Livewire::test(Index::class);
        $comp->assertSee('Mandujano Raraz Ingrid Madeleine')
            ->assertDontSeeHtml(route('clients.edit', $this->copro->id)) // el nombre solo aparece en el tooltip del titular
            ->assertDontSee('Copropietario:')->assertDontSee('Titular:');
        $html = $comp->html();
        $this->assertSame(1, substr_count($html, 'class="copro-icono"'), 'un icono por titular (solo se pinta la tabla de escritorio; la tarjeta móvil solo con $movil)');
        $this->assertStringContainsString('data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-custom-class="tip-copro"', $html);
        $this->assertStringContainsString('title="&lt;b&gt;Este cliente tiene un copropietario:&lt;/b&gt;&lt;br&gt;Mejia Villanueva Miguel Alcides &lt;small&gt;(B8T492)&lt;/small&gt;"', $html);
        $this->assertStringContainsString('<i class="ti ti-users ms-1"></i>', $html, 'el icono dentro del mismo contenedor que el nombre, con su color');

        // Un segundo copropietario en otro vehículo: el tooltip los pinta a los dos con sus placas.
        $otro = Client::create([
            'nombre' => 'Juan', 'apellido_pat' => 'Perez', 'apellido_mat' => 'Lopez', 'tipo_documento' => 'DNI', 'documento' => '41001459',
            'sexo' => 'M', 'status' => 'active', 'es_relacionado' => true, 'headquarter_id' => $this->titular->headquarter_id,
        ]);
        $v2 = Vehiculo::create(['client_id' => $this->titular->id, 'placa' => 'T1T779', 'marca' => 'TOYOTA', 'valor' => 10000]);
        $v2->copropietarios()->attach($this->copro->id, ['rol' => 'copropietario']);
        $v2->copropietarios()->attach($otro->id, ['rol' => 'copropietario']);
        $html = Livewire::test(Index::class)->html();
        $this->assertStringContainsString('title="&lt;b&gt;Este cliente tiene 2 copropietarios:&lt;/b&gt;&lt;br&gt;Mejia Villanueva Miguel Alcides &lt;small&gt;(B8T492, T1T779)&lt;/small&gt;&lt;br&gt;Perez Lopez Juan &lt;small&gt;(T1T779)&lt;/small&gt;"', $html);

        // Sin copropietarios no hay icono.
        $solo = Client::create([
            'expediente' => '162', 'nombre' => 'Sola', 'apellido_pat' => 'Sin', 'apellido_mat' => 'Nadie', 'tipo_documento' => 'DNI', 'documento' => '41001460',
            'sexo' => 'F', 'status' => 'active', 'headquarter_id' => $this->titular->headquarter_id,
        ]);
        $this->assertSame(1, substr_count(Livewire::test(Index::class)->html(), 'class="copro-icono"'), 'solo el titular con copropietarios');
        $this->assertStringContainsString('class="copro-icono"', Livewire::test(Index::class)->set('movil', true)->html(), 'también en la tarjeta móvil');
    }

    public function test_los_filtros_no_traen_al_copropietario(): void
    {
        $this->mundo();
        Livewire::test(Index::class)->set('nexpediente', '161')->assertSee('Mandujano Raraz')->assertDontSeeHtml(route('clients.edit', $this->copro->id))->assertSeeHtml('copro-icono');
        Livewire::test(Index::class)->set('nombre', 'Mejia')->assertDontSee('Mandujano Raraz')->assertDontSeeHtml(route('clients.edit', $this->copro->id));
        Livewire::test(Index::class)->set('giro', 'B8T492')->assertSee('Mandujano Raraz')->assertDontSeeHtml(route('clients.edit', $this->copro->id));
    }

    public function test_la_referencia_al_prestamo_usa_el_ultimo_credito_con_contrato_y_el_enlace_exacto_del_codeudor(): void
    {
        $this->mundo();

        // Sin contratos: por el vehículo, al titular, sin crédito.
        $c = $this->copro->copropiedades();
        $this->assertCount(1, $c);
        $this->assertSame($this->titular->id, $c[0]['titular']->id);
        $this->assertNull($c[0]['credit'], 'el titular aún no tiene contrato emitido');
        $this->assertSame(['B8T492'], $c[0]['placas']);
        $this->assertFalse($c[0]['exacto']);
        $this->assertEmpty($this->titular->copropiedades(), 'un titular no es copropietario de nadie');

        $viejo = $this->credito('2026-07-01');
        $this->contrato($viejo);
        $this->contrato($this->credito);           // el más reciente con contrato
        $anulado = $this->credito('2026-09-15');
        $this->contrato($anulado, null, 'anulado'); // anulado: no cuenta

        $c = $this->copro->copropiedades();
        $this->assertSame($this->credito->id, $c[0]['credit']->id, 'por el vehículo: el último crédito con contrato NO anulado');
        $this->assertFalse($c[0]['exacto']);

        // Un contrato posterior que guardó al codeudor manda sobre la deducción por vehículo.
        $exacto = $this->credito('2026-10-01');
        $this->contrato($exacto, $this->copro->id);
        $c = $this->copro->fresh()->copropiedades();
        $this->assertSame($exacto->id, $c[0]['credit']->id);
        $this->assertTrue($c[0]['exacto']);
        $this->assertSame(['B8T492'], $c[0]['placas'], 'las placas del vehículo compartido se suman al titular');
    }

    public function test_la_ficha_del_relacionado_dice_en_que_prestamo_es_copropietario(): void
    {
        $this->mundo();
        $this->contrato($this->credito);

        Livewire::test(Show::class, ['id' => $this->copro->id])
            ->assertSee('Persona relacionada')
            ->assertSee('vehículo B8T492 de')
            ->assertSee('Mandujano Raraz Ingrid Madeleine')
            ->assertSee('(exp. 161)')
            ->assertSee('crédito #'.$this->credito->id)
            ->assertSee('contrato v1 del')
            ->assertSee('(último crédito con contrato del titular)');

        // La ficha del titular no lleva el bloque.
        Livewire::test(Show::class, ['id' => $this->titular->id])->assertDontSee('Persona relacionada');
    }

    /** "Hay que pensar en una lógica si el cliente copropietario llegara a tener un préstamo por sí solo." */
    public function test_al_sacar_su_propio_credito_la_persona_relacionada_pasa_a_titular_con_expediente(): void
    {
        $this->mundo();
        DB::table('correlativos')->updateOrInsert(['tipo' => 'Cliente'], ['correl' => 1500, 'updated_at' => now(), 'created_at' => now()]);

        $this->assertNull($this->titular->promoverATitular(), 'un titular no se promueve');

        $exp = $this->copro->promoverATitular();
        $this->assertSame('1501', $exp, 'siguiente expediente del correlativo de clientes');
        $c = $this->copro->fresh();
        $this->assertFalse((bool) $c->es_relacionado);
        $this->assertSame('1501', $c->expediente);
        $this->assertSame(now()->toDateString(), $c->fecha_registro?->toDateString());
        $this->assertSame('guilmer', $c->usuario);
        $this->assertSame($this->asesor->id, $c->asesor_id);
        $this->assertSame(1501, (int) DB::table('correlativos')->where('tipo', 'Cliente')->value('correl'), 'el correlativo avanza');
        $this->assertNull($c->promoverATitular(), 'ya es titular: no vuelve a promover');
        $this->assertSame(1, Activity::where('log_name', Audit::LOG)->where('subject_id', $c->id)->where('description', 'like', 'Pasó a titular con el expediente 1501%')->count());

        // Ahora sí sale en el listado, como un cliente más, y sigue siendo copropietario del vehículo del otro.
        $html = Livewire::test(Index::class)->html();
        $this->assertStringContainsString('Mejia Villanueva Miguel Alcides', $html);
        $this->assertStringContainsString('<td class="text-center">1501</td>', $html);
        $this->assertCount(1, $c->copropiedades(), 'conserva el vínculo con el vehículo del titular');
        Livewire::test(Show::class, ['id' => $c->id])->assertSee('Copropietaria en:')->assertDontSee('Persona relacionada');

        // Y Credits\Create lo llama al registrar el crédito.
        $this->assertStringContainsString('Client::find($this->codigod)?->promoverATitular();', file_get_contents(app_path('Livewire/Credits/Create.php')));
    }
}
