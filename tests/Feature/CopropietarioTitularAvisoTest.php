<?php

namespace Tests\Feature;

use App\Livewire\Clients\Vehiculos;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 1372 en prod): al intentar poner como copropietario
 * el DNI de la propia titular, el sistema lo rechazaba por las tres vías pero
 * sin decirlo donde se mira: "Sin resultados" + botón de crear persona, la
 * consulta cerraba el formulario con el aviso arriba fuera de pantalla, y el
 * guardado decía "documento ya registrado" sin nombre. Ahora un solo texto con
 * el nombre, pegado a cada paso, más la alerta emergente.
 */
class CopropietarioTitularAvisoTest extends TestCase
{
    use RefreshDatabase;

    private Client $titular;

    private Client $otra;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['username' => 'copro-tester']));
        $this->titular = Client::create([
            'expediente' => '1310', 'nombre' => 'Felicitas', 'apellido_pat' => 'Estrada', 'apellido_mat' => 'Rodriguez',
            'tipo_documento' => 'DNI', 'documento' => '71555128', 'sexo' => 'F', 'status' => 'active',
        ]);
        $this->otra = Client::create([
            'expediente' => '1311', 'nombre' => 'Juan', 'apellido_pat' => 'Perez', 'apellido_mat' => 'Lopez',
            'tipo_documento' => 'DNI', 'documento' => '12345678', 'sexo' => 'M', 'status' => 'active',
        ]);
        $this->vehiculo = Vehiculo::create(['client_id' => $this->titular->id, 'placa' => 'A3U743', 'marca' => 'TOYOTA', 'valor' => 20000]);
        Http::fake();
    }

    private const AVISO = 'Ese documento es de la titular, Estrada Rodriguez Felicitas: el copropietario tiene que ser otra persona.';

    private function panel()
    {
        return Livewire::test(Vehiculos::class, ['id' => $this->titular->id])
            ->call('abrirCopro', $this->vehiculo->id)
            ->call('agregarCopro');
    }

    public function test_el_buscador_avisa_que_es_la_titular_y_no_ofrece_crearla(): void
    {
        $this->assertSame(self::AVISO, Livewire::test(Vehiculos::class, ['id' => $this->titular->id])->instance()->avisoTitular());

        $this->panel()->set('buscarCopro', '71555128')
            ->assertSee(self::AVISO)
            ->assertDontSee('Sin resultados')
            ->assertDontSee('No está registrado: crear persona');

        // También por nombre (y a medio escribir el DNI).
        $this->panel()->set('buscarCopro', 'estrada')->assertSee(self::AVISO);
        $this->panel()->set('buscarCopro', '7155')->assertSee(self::AVISO);

        // Otra persona: la lista de siempre, sin aviso.
        $this->panel()->set('buscarCopro', '1234')
            ->assertSee('Perez Lopez Juan — 12345678')
            ->assertDontSee(self::AVISO)
            ->assertSee('No está registrado: crear persona');

        // Algo que no es nadie: "Sin resultados" y el botón de crear, como antes.
        $this->panel()->set('buscarCopro', 'zzzz')
            ->assertSeeHtml('Sin resultados para "zzzz"')
            ->assertDontSee(self::AVISO)
            ->assertSee('No está registrado: crear persona');
    }

    public function test_consultar_el_dni_de_la_titular_deja_el_aviso_en_el_formulario_y_salta_la_alerta(): void
    {
        $comp = $this->panel()->call('abrirCrearCopro')
            ->set('nuevoCopro.documento', '71555128')
            ->call('consultarDocCopro')
            ->assertSet('coproCreando', true, 'el formulario sigue abierto')
            ->assertSet('coproDocMsgType', 'err')
            ->assertSet('coproDocMsg', self::AVISO)
            ->assertDispatched('errorAlert')
            ->assertSeeHtml('alert-danger');
        $this->assertSame(0, $this->vehiculo->copropietarios()->count());
        Http::assertNothingSent();

        // El DNI de otra persona ya registrada: se vincula directo, como antes.
        $comp->set('nuevoCopro.documento', '12345678')
            ->call('consultarDocCopro')
            ->assertNotDispatched('errorAlert')
            ->assertSet('msgType', 'ok');
        $this->assertSame(1, $this->vehiculo->copropietarios()->whereKey($this->otra->id)->count());
    }

    public function test_crear_persona_con_documento_repetido_dice_de_quien_es(): void
    {
        $this->panel()->call('abrirCrearCopro')
            ->set('nuevoCopro.documento', '71555128')
            ->call('crearYVincularCopro')
            ->assertHasErrors(['nuevoCopro.documento'])
            ->assertSee(self::AVISO);

        $this->panel()->call('abrirCrearCopro')
            ->set('nuevoCopro.documento', '12345678')
            ->call('crearYVincularCopro')
            ->assertHasErrors(['nuevoCopro.documento'])
            ->assertSee('Ese documento ya tiene ficha: Perez Lopez Juan. Búscalo arriba en vez de crearlo.');

        $this->assertSame(2, Client::count(), 'no se creó ninguna ficha repetida');
    }

    public function test_vincular_dos_veces_avisa_que_ya_era_copropietario_y_el_aviso_general_se_trae_a_la_vista(): void
    {
        $comp = $this->panel()->call('vincularCopro', $this->vehiculo->id, $this->otra->id)
            ->assertSet('msgType', 'ok');
        $comp->call('vincularCopro', $this->vehiculo->id, $this->otra->id)
            ->assertSet('msgType', 'warn')
            ->assertSet('msg', 'Perez Lopez Juan ya era copropietario del vehículo A3U743.')
            ->assertSeeHtml('x-init="$el.scrollIntoView({ block: \'nearest\', behavior: \'smooth\' })"');
        $this->assertSame(1, $this->vehiculo->copropietarios()->count());

        // El titular como copropietario de su propio vehículo: mismo aviso + alerta emergente.
        $comp->call('vincularCopro', $this->vehiculo->id, $this->titular->id)
            ->assertSet('msgType', 'err')
            ->assertSet('msg', self::AVISO)
            ->assertDispatched('errorAlert');
    }
}
