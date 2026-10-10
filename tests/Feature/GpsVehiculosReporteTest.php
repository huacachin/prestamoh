<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Livewire\Clients\GpsVehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 1469): en la pestaña GPS se listan TODAS las
 * placas del cliente; cada una con su botón "Agregar ubicación" (coordenadas
 * y descripción; la fecha de registro se pone sola) y, debajo, sus
 * ubicaciones. Sin vehículos, se avisa. Fuera (09/10) los puntos del
 * recorrido, horarios, domicilio, fotos y el texto para WhatsApp.
 */
class GpsVehiculosReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Vehiculo $vehiculo;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mundo(bool $conVehiculo = true): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'gps-veh-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '1469', 'nombre' => 'WILDER BENIGNO', 'apellido_pat' => 'ROQUE', 'apellido_mat' => 'JANCACHAGUA',
            'tipo_documento' => 'DNI', 'documento' => '41234567', 'sexo' => 'M', 'direccion' => 'Mz. G Lt. 5', 'distrito' => 'Carabayllo',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        if ($conVehiculo) {
            $this->vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ALP837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);
        }
    }

    private function ubicacion(array $extra = []): VehiculoGpsReporte
    {
        return VehiculoGpsReporte::create($extra + [
            'client_id' => $this->client->id, 'vehiculo_id' => $this->vehiculo->id, 'placa' => 'ALP837',
            'fecha' => now()->format('Y-m-d H:i'), 'latitud' => -12.014431, 'longitud' => -76.824936,
            'descripcion' => 'Frente al mercado', 'registrado_por' => $this->user->id,
        ]);
    }

    public function test_cada_placa_tiene_su_boton_de_agregar_ubicacion_que_pide_coordenadas_y_descripcion_y_la_fecha_se_pone_sola(): void
    {
        $this->mundo();
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);
        $html = $comp->html();
        $this->assertStringContainsString('Ubicaciones GPS de los vehículos', $html);
        $this->assertStringContainsString('ALP837', $html);
        $this->assertStringContainsString('TOYOTA HIACE', $html);
        $this->assertStringContainsString('· 0 ubicaciones', $html);
        $this->assertStringContainsString('Sin ubicaciones todavía.', $html);
        $this->assertStringContainsString("wire:click=\"nuevo({$this->vehiculo->id})\"", $html, 'el botón va en la placa');
        $this->assertStringNotContainsString('wire:model="form.coordenadas"', $html, 'sin formulario hasta pulsar Agregar ubicación');

        $comp->call('nuevo', $this->vehiculo->id)->assertSet('formVehiculoId', $this->vehiculo->id);
        $this->assertSame(['coordenadas', 'descripcion'], array_keys($comp->get('form')), 'solo coordenadas y descripción; la placa ya está elegida y la fecha no se digita');
        $html = $comp->html();
        $this->assertStringContainsString('wire:model="form.coordenadas"', $html);
        $this->assertStringContainsString('wire:model="form.descripcion"', $html);
        $this->assertStringNotContainsString("wire:click=\"nuevo({$this->vehiculo->id})\"", $html, 'el botón se esconde mientras el formulario está abierto');
        foreach (['datetime-local', 'form.vehiculo_id', 'Horario', 'Punto 1', 'Domicilio', 'Fotos', 'Adjuntos', 'zona-imagenes', 'WhatsApp', 'Así saldrá el mensaje', 'form.puntos', 'filtroPlaca', '_lightbox'] as $viejo) {
            $this->assertStringNotContainsString($viejo, $html, "ya no va: {$viejo}");
        }

        Carbon::setTestNow('2026-10-10 08:30:00'); // la fecha de registro es el momento de guardar
        $comp->set('form.coordenadas', '-12.014431, -76.824936')
            ->set('form.descripcion', '  Parado frente al mercado de Huaycán  ')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVehiculoId', null)
            ->assertSet('msgType', 'ok')
            ->assertSet('msg', 'Ubicación del vehículo ALP837 guardada.');

        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertSame([$this->client->id, $this->vehiculo->id, 'ALP837', $this->user->id], [$r->client_id, $r->vehiculo_id, $r->placa, $r->registrado_por]);
        $this->assertSame('2026-10-10 08:30', $r->fecha->format('Y-m-d H:i'));
        $this->assertEqualsWithDelta(-12.014431, (float) $r->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-76.824936, (float) $r->longitud, 0.0000001);
        $this->assertSame('Parado frente al mercado de Huaycán', $r->descripcion);
        $this->assertSame('-12.014431, -76.824936', $r->coordenadas());
        $this->assertSame('https://maps.google.com/?q=-12.014431,-76.824936', $r->enlaceMaps());

        // Debajo de la placa: fecha, coordenadas con su Maps, descripción y quién.
        $html = $comp->html();
        $this->assertStringContainsString('· 1 ubicación', $html);
        $this->assertStringContainsString('10/10/2026 <span class="text-muted">08:30</span>', $html);
        $this->assertStringContainsString('<span class="font-monospace">-12.014431, -76.824936</span>', $html);
        $this->assertStringContainsString('href="https://maps.google.com/?q=-12.014431,-76.824936" target="_blank"', $html);
        $this->assertStringContainsString('Parado frente al mercado de Huaycán', $html);
        $this->assertStringContainsString('gps-veh-tester', $html);
        // 10/10 (Antony): "el registro va después de coordenadas": Fecha · Coordenadas · Registró · Descripción.
        $this->assertMatchesRegularExpression('/<th[^>]*>Fecha de registro<\/th>\s*<th[^>]*>Coordenadas<\/th>\s*<th[^>]*>Registró<\/th>\s*<th>Descripción<\/th>/', $html);
        $this->assertLessThan(strpos($html, 'Parado frente al mercado de Huaycán'), strpos($html, 'data-label="Registró">gps-veh-tester'), 'quién registró va antes de la descripción también en el celular');
        $this->assertStringContainsString("wire:click=\"nuevo({$this->vehiculo->id})\"", $html, 'vuelve el botón');

        // Lo demás se fue de verdad: ni fotos, ni puntos, ni texto para WhatsApp.
        $this->assertFalse(Schema::hasTable('vehiculo_gps_reporte_fotos'));
        foreach (['puntos', 'inicio_desde', 'fin_hasta', 'domicilio_direccion', 'domicilio_link'] as $columna) {
            $this->assertFalse(Schema::hasColumn('vehiculo_gps_reportes', $columna), "columna {$columna}");
        }
        foreach (['texto', 'fotos', 'galeria', 'descargas', 'maps', 'hora12'] as $metodo) {
            $this->assertFalse(method_exists(VehiculoGpsReporte::class, $metodo), "método {$metodo}");
        }
        foreach (['ver', 'agregarPunto', 'abrirAdjuntos', 'eliminarFoto', 'vistaPrevia', 'updatedFotosExtra'] as $metodo) {
            $this->assertFalse(method_exists(GpsVehiculos::class, $metodo), "método {$metodo}");
        }
    }

    public function test_acepta_el_enlace_de_google_maps_y_rechaza_lo_que_no_son_coordenadas(): void
    {
        $this->mundo();
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/x/@-12.0631,-77.0527,17z/data=!3m1!4b1!4m6!3m5!8m2!3d-12.0631527!4d-77.0501364']),
        ]);
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);

        // Sin pulsar "Agregar ubicación" no hay placa elegida: no se guarda nada.
        $comp->set('form.coordenadas', '-12.1, -77.1')->call('guardar')->assertDispatched('errorAlert');
        // Un vehículo ajeno tampoco abre el formulario.
        $comp->call('nuevo', 999)->assertSet('formVehiculoId', null)->assertDispatched('errorAlert');

        $comp->call('nuevo', $this->vehiculo->id);
        // Texto cualquiera: aviso de formato, como en Casa/Negocio.
        $comp->set('form.coordenadas', 'por el mercado')->call('guardar')->assertHasErrors(['form.coordenadas']);
        $this->assertStringContainsString('Formato inválido. Pega las coordenadas como: -12.014431, -76.824936', $comp->html());
        $comp->set('form.coordenadas', '')->call('guardar')->assertHasErrors(['form.coordenadas' => 'required']);
        $this->assertSame(0, VehiculoGpsReporte::count());

        // Enlace corto de Maps: se resuelve una sola vez y se lee el pin.
        $comp->set('form.coordenadas', 'https://maps.app.goo.gl/abc')->call('guardar')->assertHasNoErrors();
        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertEqualsWithDelta(-12.0631527, (float) $r->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-77.0501364, (float) $r->longitud, 0.0000001);
        $this->assertNull($r->descripcion, 'la descripción es opcional');
        Http::assertSentCount(1);
    }

    public function test_todas_las_placas_con_sus_ubicaciones_del_ultimo_registro_hacia_abajo_y_el_analista_solo_mira(): void
    {
        $this->mundo();
        $otro = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'AWI775', 'marca' => 'HYUNDAI', 'modelo' => 'H1', 'valor' => 25000]);
        // Copropietario de un vehículo ajeno: también se lista.
        $dueno = Client::create(['expediente' => '1470', 'nombre' => 'Otro', 'apellido_pat' => 'Dueno', 'apellido_mat' => 'X', 'tipo_documento' => 'DNI', 'documento' => '41234568', 'sexo' => 'M', 'status' => 'active']);
        $compartido = Vehiculo::create(['client_id' => $dueno->id, 'placa' => 'CDD033', 'marca' => 'KIA', 'modelo' => 'RIO', 'valor' => 15000]);
        $compartido->copropietarios()->attach($this->client->id, ['rol' => 'copropietario']);
        // Placa de un vehículo que ya no está en la ficha: sus ubicaciones siguen a la vista.
        $this->ubicacion(['vehiculo_id' => null, 'placa' => 'ZZZ999', 'descripcion' => 'Ubicación huérfana']);

        $this->ubicacion(['descripcion' => 'Primera de ALP']);
        $this->ubicacion(['vehiculo_id' => $otro->id, 'placa' => 'AWI775', 'fecha' => now()->subDay()->format('Y-m-d H:i'), 'descripcion' => 'Única de AWI']);
        $this->ubicacion(['fecha' => now()->subDays(2)->format('Y-m-d H:i'), 'descripcion' => 'Segunda de ALP']);

        // Placas por orden alfabético; dentro de cada una manda el orden de registro (la última arriba).
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);
        $comp->assertSeeInOrder(['ALP837', 'Segunda de ALP', 'Primera de ALP', 'AWI775', 'Única de AWI', 'CDD033', 'Sin ubicaciones todavía.', 'ZZZ999', 'vehículo ya no registrado en la ficha', 'Ubicación huérfana']);
        $html = $comp->html();
        $this->assertSame(3, substr_count($html, 'wire:click="nuevo('), 'un botón por cada vehículo de la ficha; la placa huérfana no tiene');
        $this->assertStringContainsString('· 2 ubicaciones', $html);
        $this->assertSame(4, substr_count($html, 'wire:click="eliminar('));

        Livewire::test(Gps::class, ['id' => $this->client->id])->assertSeeLivewire('clients.gps-vehiculos');

        $this->user->givePermissionTo('clientes.scope-propio');
        $html = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->assertSet('puedeEditar', false)->html();
        $this->assertStringContainsString('Primera de ALP', $html);
        $this->assertStringNotContainsString('Agregar ubicación', $html);
        $this->assertStringNotContainsString('wire:click="eliminar(', $html);
    }

    public function test_sin_vehiculos_avisa_que_aun_no_se_agregaron(): void
    {
        $this->mundo(conVehiculo: false);
        $html = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->html();
        $this->assertStringContainsString('Aún no se han agregado vehículos a este cliente.', $html);
        $this->assertStringContainsString(route('clients.edit', ['id' => $this->client->id, 'tab' => 'vehiculos']), $html);
        $this->assertStringNotContainsString('Agregar ubicación', $html);
    }

    public function test_eliminar_quita_la_ubicacion_dentro_de_las_reglas_de_eliminacion(): void
    {
        $this->mundo();
        // Dentro de la ventana (6–11) y creada hoy: quien no es director también puede.
        Carbon::setTestNow(now()->setTime(9, 0));
        $r = $this->ubicacion();

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('eliminar', $r->id)
            ->assertSet('msg', 'Ubicación del vehículo ALP837 eliminada.')
            ->assertSee('Sin ubicaciones todavía.');
        $this->assertNull($r->fresh());
    }
}
