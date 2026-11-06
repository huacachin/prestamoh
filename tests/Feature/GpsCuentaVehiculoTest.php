<?php

namespace Tests\Feature;

use App\Livewire\Clients\GpsVehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsCuenta;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 06/11/2026 (Trello, "Agregar una opción en la cochera para registrar la cuenta del GPS:
 * Usuario, contraseña, enlace y aplicativo"; Antony: "una para cada placa, un botón que abre
 * un modal al costado de agregar ubicación"). La contraseña va cifrada y enmascarada en la auditoría.
 */
class GpsCuentaVehiculoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede GPS', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'gps-cuenta-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '1480', 'nombre' => 'INGRID', 'apellido_pat' => 'MANDUJANO', 'apellido_mat' => 'RARAZ',
            'tipo_documento' => 'DNI', 'documento' => '41234580', 'sexo' => 'F',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        $this->vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ALP837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);
    }

    private function cuenta(array $extra = []): VehiculoGpsCuenta
    {
        return VehiculoGpsCuenta::create($extra + [
            'vehiculo_id' => $this->vehiculo->id, 'aplicativo' => 'Protrack', 'enlace' => 'https://protrack.example/login',
            'usuario' => 'huacachin01', 'contrasena' => 'Clave-Inicial-1', 'registrado_por' => $this->user->id,
        ]);
    }

    public function test_cada_placa_registra_su_cuenta_gps_en_un_modal_y_la_contrasena_se_guarda_cifrada(): void
    {
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);
        $this->assertSame(1, substr_count($comp->html(), 'wire:click="abrirCuenta('.$this->vehiculo->id.')"'), 'botón Cuenta GPS en la placa');

        $comp->call('abrirCuenta', $this->vehiculo->id)
            ->assertDispatched('cuenta-gps-modal-open')
            ->assertSet('cuentaVehiculoId', $this->vehiculo->id)
            ->set('cuenta.aplicativo', ' Protrack ')
            ->set('cuenta.enlace', 'protrack.example/login')
            ->set('cuenta.usuario', 'huacachin01')
            ->set('cuenta.contrasena', 'S3creta-GPS')
            ->call('guardarCuenta')
            ->assertHasNoErrors()
            ->assertDispatched('cuenta-gps-modal-close')
            ->assertSet('cuentaVehiculoId', null)
            ->assertSet('cuenta', [])
            ->assertSee('Cuenta GPS del vehículo ALP837 registrada.');

        $guardada = VehiculoGpsCuenta::where('vehiculo_id', $this->vehiculo->id)->sole();
        $this->assertSame('Protrack', $guardada->aplicativo);
        $this->assertSame('https://protrack.example/login', $guardada->enlace, 'sin https:// se le agrega');
        $this->assertSame('S3creta-GPS', $guardada->contrasena);
        $this->assertSame($this->user->id, $guardada->registrado_por);
        $this->assertNull($guardada->actualizado_por);

        $enBase = DB::table('vehiculo_gps_cuentas')->where('id', $guardada->id)->value('contrasena');
        $this->assertStringNotContainsString('S3creta-GPS', $enBase, 'en la base va cifrada');

        $auditoria = Activity::where('subject_type', VehiculoGpsCuenta::class)->sole();
        $this->assertSame('••••••', $auditoria->attribute_changes['attributes']['contrasena']);
        $this->assertStringNotContainsString('S3creta-GPS', json_encode($auditoria->attribute_changes));

        // Con cuenta, el botón va en verde.
        $this->assertMatchesRegularExpression('/btn-success"\s+wire:click="abrirCuenta\('.$this->vehiculo->id.'\)"/', Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->html());
    }

    public function test_pide_aplicativo_usuario_y_contrasena_y_rechaza_un_enlace_que_no_es_web(): void
    {
        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('abrirCuenta', $this->vehiculo->id)
            ->call('guardarCuenta')
            ->assertHasErrors(['cuenta.aplicativo' => 'required', 'cuenta.usuario' => 'required', 'cuenta.contrasena' => 'required'])
            ->assertHasNoErrors('cuenta.enlace')
            ->set('cuenta.aplicativo', 'Protrack')->set('cuenta.usuario', 'u')->set('cuenta.contrasena', 'c')
            ->set('cuenta.enlace', 'no es un enlace')
            ->call('guardarCuenta')
            ->assertHasErrors(['cuenta.enlace' => 'url']);

        $this->assertSame(0, VehiculoGpsCuenta::count());
    }

    public function test_al_editar_carga_lo_guardado_y_marca_quien_lo_cambio_solo_si_cambio_algo(): void
    {
        $cuenta = $this->cuenta();
        $otro = User::factory()->create(['username' => 'gps-cuenta-otro', 'headquarter_id' => $this->user->headquarter_id]);
        $otro->givePermissionTo('clientes');
        $this->actingAs($otro);

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('abrirCuenta', $this->vehiculo->id)
            ->assertSet('cuenta.usuario', 'huacachin01')
            ->assertSet('cuenta.contrasena', 'Clave-Inicial-1')
            ->call('guardarCuenta')
            ->assertHasNoErrors();
        $this->assertNull($cuenta->fresh()->actualizado_por, 'sin cambios no queda como actualizada');

        $comp->call('abrirCuenta', $this->vehiculo->id)
            ->set('cuenta.contrasena', 'Clave-Nueva-2')
            ->call('guardarCuenta')
            ->assertSee('Cuenta GPS del vehículo ALP837 actualizada.');

        $this->assertSame(1, VehiculoGpsCuenta::count(), 'una cuenta por placa');
        $this->assertSame('Clave-Nueva-2', $cuenta->fresh()->contrasena);
        $this->assertSame($otro->id, $cuenta->fresh()->actualizado_por);
        $this->assertSame($this->user->id, $cuenta->fresh()->registrado_por);
        $editado = Activity::where('subject_type', VehiculoGpsCuenta::class)->where('event', 'updated')->sole();
        $this->assertSame('••••••', $editado->attribute_changes['old']['contrasena']);
        $this->assertSame('••••••', $editado->attribute_changes['attributes']['contrasena']);
    }

    public function test_no_abre_ni_guarda_la_cuenta_de_un_vehiculo_de_otro_cliente(): void
    {
        $ajeno = Client::create([
            'expediente' => '1481', 'nombre' => 'Otro', 'apellido_pat' => 'Ajeno', 'apellido_mat' => 'X',
            'tipo_documento' => 'DNI', 'documento' => '41234581', 'sexo' => 'M', 'status' => 'active',
        ]);
        $vehiculoAjeno = Vehiculo::create(['client_id' => $ajeno->id, 'placa' => 'ZZZ111', 'marca' => 'KIA', 'modelo' => 'RIO', 'valor' => 9000]);
        VehiculoGpsCuenta::create(['vehiculo_id' => $vehiculoAjeno->id, 'aplicativo' => 'Wialon', 'usuario' => 'ajeno', 'contrasena' => 'Secreto-Ajeno']);

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('abrirCuenta', $vehiculoAjeno->id)
            ->assertDispatched('errorAlert')
            ->assertNotDispatched('cuenta-gps-modal-open')
            ->assertSet('cuenta', [])
            // Aunque se fuerce el id desde el navegador, no se escribe en la cuenta ajena.
            ->set('cuentaVehiculoId', $vehiculoAjeno->id)
            ->set('cuenta', ['aplicativo' => 'X', 'enlace' => '', 'usuario' => 'pirata', 'contrasena' => 'pirata'])
            ->call('guardarCuenta')
            ->assertDispatched('errorAlert');

        $this->assertSame('ajeno', VehiculoGpsCuenta::where('vehiculo_id', $vehiculoAjeno->id)->value('usuario'));
    }

    public function test_el_analista_de_cartera_propia_ve_la_cuenta_registrada_pero_no_la_cambia(): void
    {
        $sinCuenta = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'AWI775', 'marca' => 'HYUNDAI', 'modelo' => 'H1', 'valor' => 25000]);
        $this->cuenta();
        $this->user->givePermissionTo('clientes.scope-propio');

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->assertSet('puedeEditar', false);
        $html = $comp->html();
        $this->assertStringContainsString('wire:click="abrirCuenta('.$this->vehiculo->id.')"', $html);
        $this->assertStringNotContainsString('wire:click="abrirCuenta('.$sinCuenta->id.')"', $html, 'sin cuenta no hay nada que ver');

        $comp->call('abrirCuenta', $this->vehiculo->id)
            ->assertSet('cuenta.contrasena', 'Clave-Inicial-1')
            ->assertDontSeeHtml('wire:click="guardarCuenta"')
            ->set('cuenta.contrasena', 'Cambiada')
            ->call('guardarCuenta')
            ->assertStatus(403);

        $this->assertSame('Clave-Inicial-1', VehiculoGpsCuenta::sole()->contrasena);
    }
}
