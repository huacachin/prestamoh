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
 * 10/10/2026 (Antony): en /clients, al ver las personas relacionadas
 * (copropietarios) las columnas Exp., T.Credito, Giro y Asesor salían vacías.
 * Ahora la persona queda referenciada al préstamo en el que es copropietaria:
 * hereda esos datos del titular, se ve "Copropietario de …" con el último
 * crédito con contrato, los filtros la encuentran por los datos del titular y
 * su ficha lleva el bloque "copropietaria en". El enlace es exacto cuando el
 * contrato guardó al codeudor (codeudor_client_id); si no, por el vehículo.
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

    public function test_sin_contratos_hereda_los_datos_del_titular_por_el_vehiculo(): void
    {
        $this->mundo();

        $her = $this->copro->herenciaDeCopropietario();
        $this->assertSame($this->titular->id, $her['titular']->id);
        $this->assertNull($her['credit'], 'el titular aún no tiene contrato emitido');
        $this->assertSame(['B8T492'], $her['placas']);
        $this->assertFalse($her['exacto']);

        // En la misma lista que los clientes: "Copropietario: <persona>" y debajo "Titular: <titular>",
        // con Exp., T.Credito (zona), Giro y Asesor heredados del titular.
        $comp = Livewire::test(Index::class);
        $comp->assertSee('Mejia Villanueva Miguel Alcides')
            ->assertSee('Copropietario:')->assertSee('Titular:')
            ->assertSee('Mandujano Raraz Ingrid Madeleine')
            ->assertSee('SIGM.S')->assertSee('Min.-B8T492')->assertSee('guilmer')
            ->assertDontSee('crédito #')->assertDontSee('Copropietario de');
        $html = $comp->html();
        // El copropietario va justo debajo de su titular (orden por expediente efectivo).
        $this->assertLessThan(strpos($html, 'Copropietario:'), strpos($html, 'Mandujano Raraz Ingrid Madeleine'));
        $this->assertSame(2, substr_count($html, '<td class="text-center">161</td>'), 'el expediente 161 sale en las dos filas');

        // Un titular no hereda nada ni se presenta como copropietario.
        $this->assertNull($this->titular->herenciaDeCopropietario());
        $this->assertSame(1, substr_count($html, 'Copropietario:'));
    }

    public function test_el_ultimo_credito_con_contrato_del_titular_y_el_enlace_exacto_del_codeudor(): void
    {
        $this->mundo();
        $viejo = $this->credito('2026-07-01');
        $this->contrato($viejo);
        $this->contrato($this->credito);           // el más reciente con contrato
        $anulado = $this->credito('2026-09-15');
        $this->contrato($anulado, null, 'anulado'); // anulado: no cuenta

        $c = $this->copro->copropiedades();
        $this->assertCount(1, $c, 'un solo titular');
        $this->assertSame($this->credito->id, $c[0]['credit']->id, 'por el vehículo: el último crédito con contrato NO anulado');
        $this->assertFalse($c[0]['exacto']);

        $comp = Livewire::test(Index::class);
        $comp->assertSee('crédito #'.$this->credito->id)->assertSeeHtml('title="Último crédito con contrato del titular"');

        // Un contrato posterior que guardó al codeudor manda sobre la deducción por vehículo.
        $exacto = $this->credito('2026-10-01');
        $this->contrato($exacto, $this->copro->id);
        $c = $this->copro->fresh()->copropiedades();
        $this->assertSame($exacto->id, $c[0]['credit']->id);
        $this->assertTrue($c[0]['exacto']);
        $this->assertSame(['B8T492'], $c[0]['placas'], 'las placas del vehículo compartido se suman al titular');
        Livewire::test(Index::class)->assertSeeHtml('title="Codeudor en el contrato"');
    }

    public function test_los_filtros_encuentran_al_copropietario_por_los_datos_del_titular(): void
    {
        $this->mundo();
        $lista = fn () => Livewire::test(Index::class);

        // Por expediente / giro / ruta / asesor del titular salen los dos: el titular y su copropietario.
        $lista()->set('nexpediente', '161')->assertSee('Mandujano Raraz')->assertSee('Mejia Villanueva');
        $lista()->set('nexpediente', '999')->assertDontSee('Mejia Villanueva')->assertDontSee('Mandujano Raraz');
        $lista()->set('giro', 'B8T492')->assertSee('Mejia Villanueva');
        $lista()->set('ruta', 'SIGM')->assertSee('Mejia Villanueva');
        $lista()->set('ejecutivo', (string) $this->asesor->id)->assertSee('Mejia Villanueva');
        // Por su propio nombre sigue igual: una sola fila (la del copropietario, que lleva al
        // titular en su línea "Titular:"), sin la fila propia del titular.
        $html = $lista()->set('nombre', 'Mejia')->assertSee('Mejia Villanueva')->html();
        $this->assertSame(1, substr_count($html, 'Mandujano Raraz Ingrid Madeleine'), 'solo en la línea Titular:');
        $this->assertSame(1, substr_count($html, '<td class="text-center">161</td>'));
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

        // En la lista sale como un cliente más (sus datos, sin "Copropietario:"), y sigue siendo copropietario del vehículo.
        $html = Livewire::test(Index::class)->html();
        $this->assertStringNotContainsString('Copropietario:', $html);
        $this->assertStringContainsString('<td class="text-center">1501</td>', $html);
        $this->assertCount(1, $c->copropiedades(), 'conserva el vínculo con el vehículo del titular');
        Livewire::test(Show::class, ['id' => $c->id])->assertSee('Copropietaria en:')->assertDontSee('Persona relacionada');

        // Y Credits\Create lo llama al registrar el crédito.
        $this->assertStringContainsString('Client::find($this->codigod)?->promoverATitular();', file_get_contents(app_path('Livewire/Credits/Create.php')));
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
}
