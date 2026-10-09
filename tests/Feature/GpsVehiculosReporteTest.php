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
 * 09/10/2026 (Antony, ficha 1469): el reporte de GPS de vehículos, en la
 * pestaña GPS del cliente, se simplifica a placa, coordenadas, fecha de
 * registro y una descripción. Fuera los puntos del recorrido, horarios,
 * domicilio, fotos y el texto para WhatsApp.
 */
class GpsVehiculosReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Vehiculo $vehiculo;

    private function mundo(): void
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
        $this->vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ALP837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);
    }

    private function reporte(array $extra = []): VehiculoGpsReporte
    {
        return VehiculoGpsReporte::create($extra + [
            'client_id' => $this->client->id, 'vehiculo_id' => $this->vehiculo->id, 'placa' => 'ALP837',
            'fecha' => now()->format('Y-m-d H:i'), 'latitud' => -12.014431, 'longitud' => -76.824936,
            'descripcion' => 'Frente al mercado', 'registrado_por' => $this->user->id,
        ]);
    }

    public function test_el_formulario_solo_pide_placa_fecha_coordenadas_y_descripcion_y_guarda(): void
    {
        $this->mundo();
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->assertSet('mostrarForm', true)
            ->assertSet('form.vehiculo_id', (string) $this->vehiculo->id); // único vehículo: elegido solo
        $this->assertSame(['vehiculo_id', 'fecha', 'coordenadas', 'descripcion'], array_keys($comp->get('form')));

        $html = $comp->html();
        $this->assertStringContainsString('Fecha de registro *', $html);
        $this->assertStringContainsString('wire:model="form.coordenadas"', $html);
        $this->assertStringContainsString('wire:model="form.descripcion"', $html);
        foreach (['Horario', 'Punto 1', 'Domicilio', 'Fotos', 'Adjuntos', 'zona-imagenes', 'WhatsApp', 'Así saldrá el mensaje', 'form.puntos', 'filtroPlaca', '_lightbox'] as $viejo) {
            $this->assertStringNotContainsString($viejo, $html, "ya no va: {$viejo}");
        }

        $comp->set('form.fecha', '2026-10-09T08:30')
            ->set('form.coordenadas', '-12.014431, -76.824936')
            ->set('form.descripcion', '  Parado frente al mercado de Huaycán  ')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarForm', false)
            ->assertSet('msgType', 'ok')
            ->assertSet('msg', 'Reporte de GPS del vehículo ALP837 guardado.');

        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertSame([$this->client->id, $this->vehiculo->id, 'ALP837', $this->user->id], [$r->client_id, $r->vehiculo_id, $r->placa, $r->registrado_por]);
        $this->assertSame('2026-10-09 08:30', $r->fecha->format('Y-m-d H:i'));
        $this->assertEqualsWithDelta(-12.014431, (float) $r->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-76.824936, (float) $r->longitud, 0.0000001);
        $this->assertSame('Parado frente al mercado de Huaycán', $r->descripcion);
        $this->assertSame('-12.014431, -76.824936', $r->coordenadas());
        $this->assertSame('https://maps.google.com/?q=-12.014431,-76.824936', $r->enlaceMaps());

        // La tabla: fecha, placa, coordenadas con su Maps, descripción y quién.
        $html = $comp->html();
        $this->assertStringContainsString('09/10/2026 <span class="text-muted">08:30</span>', $html);
        $this->assertStringContainsString('ALP837', $html);
        $this->assertStringContainsString('<span class="font-monospace">-12.014431, -76.824936</span>', $html);
        $this->assertStringContainsString('href="https://maps.google.com/?q=-12.014431,-76.824936" target="_blank"', $html);
        $this->assertStringContainsString('Parado frente al mercado de Huaycán', $html);
        $this->assertStringContainsString('gps-veh-tester', $html);

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
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->call('nuevo');

        // Texto cualquiera: aviso de formato, como en Casa/Negocio.
        $comp->set('form.coordenadas', 'por el mercado')->call('guardar')->assertHasErrors(['form.coordenadas']);
        $this->assertStringContainsString('Formato inválido. Pega las coordenadas como: -12.014431, -76.824936', $comp->html());
        $comp->set('form.coordenadas', '')->call('guardar')->assertHasErrors(['form.coordenadas' => 'required']);
        $comp->set('form.coordenadas', '-12.1, -77.1')->set('form.fecha', '')->call('guardar')->assertHasErrors(['form.fecha' => 'required']);
        $this->assertSame(0, VehiculoGpsReporte::count());

        // Enlace corto de Maps: se resuelve una sola vez y se lee el pin.
        $comp->set('form.fecha', '2026-10-09T09:00')->set('form.coordenadas', 'https://maps.app.goo.gl/abc')->call('guardar')->assertHasNoErrors();
        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertEqualsWithDelta(-12.0631527, (float) $r->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-77.0501364, (float) $r->longitud, 0.0000001);
        $this->assertNull($r->descripcion, 'la descripción es opcional');
        Http::assertSentCount(1);
    }

    public function test_la_tabla_va_del_ultimo_registrado_hacia_abajo_y_el_analista_de_cartera_propia_solo_mira(): void
    {
        $this->mundo();
        $otro = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'AWI775', 'marca' => 'HYUNDAI', 'modelo' => 'H1', 'valor' => 25000]);
        $this->reporte(['fecha' => now()->format('Y-m-d H:i'), 'descripcion' => 'Primero registrado ALP']);
        $this->reporte(['vehiculo_id' => $otro->id, 'placa' => 'AWI775', 'fecha' => now()->subDay()->format('Y-m-d H:i'), 'descripcion' => 'Segundo registrado AWI']);
        $this->reporte(['fecha' => now()->subDays(2)->format('Y-m-d H:i'), 'descripcion' => 'Tercero registrado ALP']);

        // Manda el orden de registro, no la fecha del reporte: el último registrado va arriba.
        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->assertSeeInOrder(['Tercero registrado ALP', 'Segundo registrado AWI', 'Primero registrado ALP'])
            ->assertSee('Nuevo reporte')
            ->assertSeeHtml('wire:click="eliminar(');

        Livewire::test(Gps::class, ['id' => $this->client->id])->assertSeeLivewire('clients.gps-vehiculos');

        $this->user->givePermissionTo('clientes.scope-propio');
        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->assertSet('puedeEditar', false)
            ->assertSee('Primero registrado ALP')
            ->assertDontSee('Nuevo reporte')
            ->assertDontSeeHtml('wire:click="eliminar(');
    }

    public function test_eliminar_quita_el_reporte_dentro_de_las_reglas_de_eliminacion(): void
    {
        $this->mundo();
        // Dentro de la ventana (6–11) y creado hoy: quien no es director también puede.
        Carbon::setTestNow(now()->setTime(9, 0));
        $r = $this->reporte();

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('eliminar', $r->id)
            ->assertSet('msg', 'Reporte eliminado.')
            ->assertSee('Aún no hay reportes de GPS');
        $this->assertNull($r->fresh());
        Carbon::setTestNow();
    }
}
