<?php

namespace Tests\Feature;

use App\Exports\ClientsExport;
use App\Livewire\Clients\Create;
use App\Livewire\Clients\Index;
use App\Livewire\Clients\Vehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Personas RELACIONADAS (28/08): el copropietario se crea desde el alta
 * rápida del buscador — como cliente MARCADO (es_relacionado), sin
 * expediente ni asesor — y no ensucia el listado ni los reportes. El día que
 * pide su propio crédito, el alta normal PROMUEVE esa misma ficha (mismo id,
 * vínculos intactos) en vez de duplicar a la persona.
 */
class PersonasRelacionadasTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Rel', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'rel-tester', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);
        // El alta consulta el correlativo de expediente
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Cliente', 'correl' => 9000]);
    }

    private function titular(): Client
    {
        return Client::create([
            'expediente' => '9001', 'nombre' => 'TITULAR', 'apellido_pat' => 'DE', 'apellido_mat' => 'PRUEBA',
            'tipo_documento' => 'DNI', 'documento' => '47000001', 'sexo' => 'M',
            'direccion' => 'AV. UNO', 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
    }

    private function vehiculo(Client $c): Vehiculo
    {
        return Vehiculo::create([
            'client_id' => $c->id, 'placa' => 'REL111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'valor' => 15000,
        ]);
    }

    private function formularioCopro(): array
    {
        return [
            'tipo_documento' => 'DNI', 'documento' => '47000002', 'nombre' => 'MARIA',
            'apellido_pat' => 'LOPEZ', 'apellido_mat' => 'DIAZ', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'direccion' => 'AV. DOS 123', 'distrito' => 'LINCE', 'provincia' => 'LIMA',
            'departamento' => 'LIMA', 'email' => 'maria.rel@example.com',
        ];
    }

    // ── 1 · Alta rápida ───────────────────────────────────────────────────

    public function test_el_alta_rapida_crea_la_persona_marcada_y_la_vincula(): void
    {
        $titular = $this->titular();
        $v = $this->vehiculo($titular);

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('abrirCopro', $v->id)
            ->call('abrirCrearCopro')
            ->set('nuevoCopro', $this->formularioCopro())
            ->call('crearYVincularCopro')
            ->assertHasNoErrors();

        $copro = Client::where('documento', '47000002')->firstOrFail();

        $this->assertTrue($copro->es_relacionado, 'nace marcada como relacionada');
        $this->assertNull($copro->expediente, 'sin expediente');
        $this->assertNull($copro->asesor_id, 'sin asesor');
        $this->assertTrue($v->fresh()->copropietarios->contains($copro->id), 'queda vinculada al vehículo en el mismo paso');
    }

    public function test_el_alta_rapida_exige_los_datos_del_contrato(): void
    {
        $titular = $this->titular();
        $v = $this->vehiculo($titular);

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('abrirCopro', $v->id)
            ->call('abrirCrearCopro')
            ->set('nuevoCopro', array_merge($this->formularioCopro(), [
                'email' => '', 'direccion' => '', 'distrito' => '',
            ]))
            ->call('crearYVincularCopro')
            ->assertHasErrors(['nuevoCopro.email', 'nuevoCopro.direccion', 'nuevoCopro.distrito']);

        $this->assertNull(Client::where('documento', '47000002')->first());
    }

    public function test_documento_repetido_no_duplica_persona(): void
    {
        $titular = $this->titular();
        $v = $this->vehiculo($titular);
        Client::create([
            'expediente' => '9002', 'nombre' => 'YA', 'apellido_pat' => 'EXISTE',
            'tipo_documento' => 'DNI', 'documento' => '47000002', 'sexo' => 'F',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('abrirCopro', $v->id)
            ->call('abrirCrearCopro')
            ->set('nuevoCopro', $this->formularioCopro())
            ->call('crearYVincularCopro')
            ->assertHasErrors(['nuevoCopro.documento']);

        $this->assertSame(1, Client::where('documento', '47000002')->count());
    }

    public function test_lo_que_llena_la_api_se_pinta_en_rojo(): void
    {
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake(['*/dni/info/*' => Http::response([
            'success' => true,
            'data' => [
                'nombres' => 'MARIA ELENA', 'apellido_paterno' => 'LOPEZ', 'apellido_materno' => 'DIAZ',
                'direccion' => 'AV. DOS 123', 'distrito' => 'LINCE', 'provincia' => 'LIMA',
                'departamento' => 'LIMA', 'sexo' => 'F',
            ],
        ])]);

        $titular = $this->titular();
        $v = $this->vehiculo($titular);

        $comp = Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('abrirCopro', $v->id)
            ->call('abrirCrearCopro')
            ->set('nuevoCopro.documento', '47000009')
            ->call('consultarDocCopro');

        // La convención de la casa: lo traído por la API va EN ROJO.
        $auto = $comp->get('autoCopro');
        foreach (['nombre', 'apellido_pat', 'direccion', 'distrito', 'sexo', 'provincia'] as $campo) {
            $this->assertContains($campo, $auto, "el campo {$campo} vino de la API y debe marcarse");
        }
        $comp->assertSeeHtml('campo-api');
        // Factiliza normaliza los nombres a Título (misma regla que el alta).
        $this->assertSame('Maria Elena', $comp->get('nuevoCopro')['nombre']);
    }

    // ── 2 · No ensucian listado ni export ─────────────────────────────────

    public function test_el_listado_los_excluye_por_defecto_y_los_muestra_al_pedirlos(): void
    {
        $titular = $this->titular();
        $rel = Client::create($this->formularioCopro() + [
            'es_relacionado' => true, 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $porDefecto = Livewire::test(Index::class);
        $porDefecto->assertSee($titular->nombre)->assertDontSee('MARIA');

        $relacionados = Livewire::test(Index::class)->set('verRelacionados', 'si');
        $relacionados->assertSee('MARIA')->assertDontSee('TITULAR');
    }

    public function test_el_export_no_los_incluye(): void
    {
        $this->titular();
        Client::create($this->formularioCopro() + [
            'es_relacionado' => true, 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $filas = (new ClientsExport)->collection();

        $this->assertTrue($filas->contains(fn ($c) => $c->documento === '47000001'));
        $this->assertFalse($filas->contains(fn ($c) => $c->documento === '47000002'));
    }

    // ── 3 · Promoción a cliente ───────────────────────────────────────────

    public function test_el_alta_normal_promueve_al_relacionado_sin_duplicar(): void
    {
        $titular = $this->titular();
        $v = $this->vehiculo($titular);
        $rel = Client::create($this->formularioCopro() + [
            'es_relacionado' => true, 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
        $v->copropietarios()->attach($rel->id, ['rol' => 'copropietario']);

        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('documento', '47000002')
            ->set('nombre', 'MARIA')
            ->set('apellido_pat', 'LOPEZ')
            ->set('apellido_mat', 'DIAZ')
            ->set('sexo', 'F')
            ->set('email', 'maria.rel@example.com')
            ->set('ocupacion', 'independiente')
            ->set('estado_civil', 'casado')
            ->set('nacionalidad', 'PERUANO')
            ->set('expediente', 9500)
            ->set('direccion', 'AV. DOS 123')
            ->set('distrito', 'LINCE')
            ->set('provincia', 'LIMA')
            ->set('departamento', 'LIMA')
            ->call('siguientePaso')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Client::where('documento', '47000002')->count(), 'misma persona, sin duplicar');

        $promovida = Client::where('documento', '47000002')->firstOrFail();
        $this->assertSame($rel->id, $promovida->id, 'conserva el id');
        $this->assertFalse($promovida->es_relacionado, 'deja de ser relacionada');
        $this->assertSame('9500', $promovida->expediente, 'gana expediente');
        $this->assertTrue(
            $v->fresh()->copropietarios->contains($rel->id),
            'el vínculo de copropiedad sobrevive a la promoción'
        );
    }

    public function test_un_titular_de_verdad_sigue_bloqueando_el_documento_duplicado(): void
    {
        $this->titular(); // documento 47000001

        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('documento', '47000001')
            ->set('nombre', 'OTRO')
            ->set('apellido_pat', 'CUALQUIERA')
            ->set('sexo', 'M')
            ->set('email', 'otro@example.com')
            ->set('expediente', 9501)
            ->set('direccion', 'AV. TRES')
            ->set('distrito', 'ATE')
            ->set('provincia', 'LIMA')
            ->set('departamento', 'LIMA')
            ->call('siguientePaso')
            ->assertHasErrors(['documento']);
    }
}
