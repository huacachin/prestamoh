<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Livewire\Clients\GpsVehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Models\VehiculoGpsReporteFoto;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 26/09/2026: reportes de GPS de los vehículos en garantía, en la pestaña GPS
 * del cliente debajo de Casa. Un solo formulario: uno o varios puntos con
 * etiqueta y horario de estadía, más fotos. El texto sale con el formato
 * exacto que manda el área (corto si el punto no lleva etiqueta ni horario).
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
            'expediente' => '1299', 'nombre' => 'WILDER BENIGNO', 'apellido_pat' => 'ROQUE', 'apellido_mat' => 'JANCACHAGUA',
            'tipo_documento' => 'DNI', 'documento' => '41234567', 'sexo' => 'M',
            'direccion' => 'Mz. G Lt. 5 Proviv. El Paraiso', 'distrito' => 'Carabayllo',
            'latitud' => '-11.860000', 'longitud' => '-77.030000',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        $this->vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ALP837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);
        Storage::fake('public');
    }

    private function reporte(array $extra = []): VehiculoGpsReporte
    {
        return VehiculoGpsReporte::create($extra + [
            'client_id' => $this->client->id, 'vehiculo_id' => $this->vehiculo->id, 'placa' => 'ALP837',
            'fecha' => now()->format('Y-m-d H:i'), 'puntos' => [['direccion' => 'Punto X', 'link' => '']], 'registrado_por' => $this->user->id,
        ]);
    }

    public function test_un_solo_punto_sin_etiqueta_sale_en_el_formato_corto_con_lo_prellenado(): void
    {
        $this->mundo();

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->assertSet('form.vehiculo_id', (string) $this->vehiculo->id) // única placa: ya elegida
            ->assertSet('form.puntos.0.etiqueta', '')
            ->assertSet('form.domicilio_direccion', 'Mz. G Lt. 5 Proviv. El Paraiso, Carabayllo')
            ->assertSet('form.domicilio_link', 'https://maps.google.com/?q=-11.8600000,-77.0300000') // la BD guarda 7 decimales
            ->set('form.inicio_desde', '06:30')->set('form.inicio_hasta', '07:40')
            ->set('form.fin_desde', '23:00')->set('form.fin_hasta', '01:30')
            ->set('form.puntos.0.direccion', 'Las Lúcumas, Carabayllo 15319')
            ->set('form.puntos.0.link', 'https://maps.app.goo.gl/cmxi6j2wpeJEvNWe7')
            ->set('form.domicilio_personalizado', true)
            ->set('form.domicilio_direccion', 'Mz. G Lt. 5 Proviv. El Paraiso de Carabayllo')
            ->set('form.domicilio_link', 'https://maps.app.goo.gl/FqEVUPpdh4sr56ub7')
            ->call('guardar')
            ->assertHasNoErrors();

        $r = VehiculoGpsReporte::where('client_id', $this->client->id)->firstOrFail();
        $esperado = implode("\n", [
            '📍 REPORTE DE GPS – VEHÍCULO EN GARANTÍA',
            '👤 Cliente: '.$this->client->fullName(),
            '🚗 Placa: ALP837',
            '📄 Expediente: 1299',
            '',
            '⏰ Inicio de ruta: 6:30 a.m. – 7:40 a.m.',
            '⏰ Fin de ruta: 11:00 p.m. – 1:30 a.m.',
            '',
            '📍 Ubicación de vehículo:',
            'Las Lúcumas, Carabayllo 15319',
            '🔗 Link de ubicación de vehículo en Google Maps:',
            'https://maps.app.goo.gl/cmxi6j2wpeJEvNWe7',
            '',
            '📍 Ubicación de domicilio:',
            'Mz. G Lt. 5 Proviv. El Paraiso de Carabayllo',
            '🔗 Link de ubicación de domicilio en Google Maps:',
            'https://maps.app.goo.gl/FqEVUPpdh4sr56ub7',
        ]);
        $this->assertSame($esperado, $r->texto());
        $comp->assertSee('Copiar texto')->assertSee('ALP837')->assertSee('Las Lúcumas, Carabayllo 15319');
    }

    public function test_varios_puntos_llevan_etiqueta_y_horario_de_estadia_en_el_formato_largo(): void
    {
        $this->mundo();

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->call('agregarPunto') // al agregar, el primero pasa a "Donde se queda"
            ->call('agregarPunto')
            ->assertSet('form.puntos.0.etiqueta', 'Donde se queda')
            ->assertSet('form.puntos.1.etiqueta', 'Intermedio')
            ->assertSet('form.puntos.2.etiqueta', 'Punto de llegada')
            ->set('form.inicio_desde', '02:30')->set('form.inicio_hasta', '15:50')
            ->set('form.puntos.0.estadia_desde', '02:30')->set('form.puntos.0.estadia_hasta', '07:00')
            ->set('form.puntos.0.direccion', 'Carretera Federico Basadre, Huipoca (Aguaytia)')
            ->set('form.puntos.0.link', 'https://www.google.com/maps?q=-9.052531,-75.52533')
            ->set('form.puntos.1.direccion', 'Carretera Federico Basadre, Huipoca (Huipoca)')
            ->set('form.puntos.2.direccion', 'Pe-5Na, Codo Del Pozuzo')
            ->call('guardar')
            ->assertHasNoErrors();

        $r = VehiculoGpsReporte::where('client_id', $this->client->id)->firstOrFail();
        $this->assertCount(3, $r->puntos);
        $texto = $r->texto();
        $this->assertStringContainsString("📍 Ubicación de vehículo (Donde se queda):\n⏱️ Horario aproximado de estadía: 2:30 a.m. – 7:00 a.m.\n→ Carretera Federico Basadre, Huipoca (Aguaytia)\n🔗 Link de ubicación de vehículo en Google Maps:\nhttps://www.google.com/maps?q=-9.052531,-75.52533", $texto);
        $this->assertStringContainsString('📍 Ubicación de vehículo (Punto de llegada):', $texto);
        $this->assertStringContainsString('⏰ Inicio de ruta: 2:30 a.m. – 3:50 p.m.', $texto);
    }

    public function test_la_vista_previa_se_arma_mientras_se_escribe_y_el_domicilio_sale_de_la_ficha(): void
    {
        $this->mundo();

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->set('form.puntos.0.etiqueta', 'Otra')
            ->set('form.puntos.0.etiqueta_otra', 'Taller')
            ->set('form.puntos.0.direccion', 'Av. Los Talleres 100');

        $previa = $comp->instance()->vistaPrevia();
        $this->assertStringContainsString('📍 Ubicación de vehículo (Taller):', $previa);
        $this->assertStringContainsString("📍 Ubicación de domicilio:\nMz. G Lt. 5 Proviv. El Paraiso, Carabayllo", $previa, 'sin personalizar, el domicilio sale de la ficha');
        $this->assertStringContainsString('https://maps.google.com/?q=-11.8600000,-77.0300000', $previa, 'y el enlace, de la ubicación de Casa');
        $comp->assertSee('Así saldrá el mensaje')->assertSee('Enlace de Casa registrado');

        $comp->call('guardar')->assertHasNoErrors();
        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertSame('Taller', $r->puntos[0]['etiqueta']);
        $this->assertSame('Mz. G Lt. 5 Proviv. El Paraiso, Carabayllo', $r->domicilio_direccion);
    }

    public function test_las_fotos_se_suben_al_guardar_y_se_pueden_arrastrar_despues_sobre_el_reporte(): void
    {
        $this->mundo();

        // Al guardar: dos fotos con el reporte.
        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->set('form.puntos.0.direccion', 'Cochera Los Cedros')
            ->set('files', [UploadedFile::fake()->image('cochera.jpg', 800, 600), UploadedFile::fake()->image('placa.png', 400, 300)])
            ->call('guardar')
            ->assertHasNoErrors();

        $r = VehiculoGpsReporte::firstOrFail();
        $this->assertCount(2, $r->fotos);
        foreach ($r->fotos as $f) {
            Storage::disk('public')->assertExists($f->path);
            $this->assertNotNull($f->thumb_path, 'cada foto lleva miniatura');
            Storage::disk('public')->assertExists($f->thumb_path);
        }
        $this->assertStringStartsWith("gps/reportes/{$r->id}/", $r->fotos[0]->path);
        $comp->assertSee('Fotos (2)');

        // Las miniaturas abren la galería tipo lightbox (panel y tabla), no una pestaña nueva.
        $galeria = $r->galeria();
        $this->assertCount(2, $galeria);
        $this->assertSame($r->fotos[0]->url(), $galeria[0]['url']);
        $this->assertStringEndsWith(' · cochera.jpg', $galeria[0]['name']);
        $this->assertStringStartsWith('ALP837 · ', $galeria[0]['name']);
        $comp->assertSeeHtml('openLightbox(')
            ->assertSeeHtml(', 1)"') // la segunda miniatura abre en la foto 1
            ->assertSeeHtml('class="huac-lb"')
            ->assertDontSeeHtml('target="_blank" rel="noopener" title="cochera.jpg"');

        // Después, arrastrada sobre el reporte abierto: se guarda al instante, sin botón.
        $comp->set('fotosExtra', [UploadedFile::fake()->image('extra.jpg')]);
        $this->assertCount(3, $r->fresh()->fotos);
        $comp->assertSet('fotosExtra', [])->assertSee('Fotos (3)');

        // Quitar una foto borra fila y archivos; eliminar el reporte borra todas.
        $foto = $r->fresh()->fotos->first();
        $comp->call('eliminarFoto', $foto->id);
        Storage::disk('public')->assertMissing($foto->path);
        $this->assertCount(2, $r->fresh()->fotos);

        $rutas = $r->fresh()->fotos->pluck('path')->all();
        $comp->call('eliminar', $r->id);
        $this->assertSame(0, VehiculoGpsReporteFoto::count());
        foreach ($rutas as $ruta) {
            Storage::disk('public')->assertMissing($ruta);
        }
    }

    public function test_cada_direccion_tiene_su_boton_a_google_maps_en_pestaña_nueva(): void
    {
        $this->assertSame('https://maps.app.goo.gl/abc', VehiculoGpsReporte::maps('https://maps.app.goo.gl/abc', 'x'));
        $this->assertSame('https://maps.app.goo.gl/abc', VehiculoGpsReporte::maps('maps.app.goo.gl/abc'));
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=Las%20L%C3%BAcumas%2C%20Carabayllo', VehiculoGpsReporte::maps('', 'Las Lúcumas, Carabayllo'));
        $this->assertNull(VehiculoGpsReporte::maps('', ''));

        $this->mundo();
        $r = $this->reporte([
            'domicilio_direccion' => 'Mz. G Lt. 5', 'domicilio_link' => 'https://maps.app.goo.gl/dom',
            'puntos' => [
                ['etiqueta' => 'Donde se queda', 'direccion' => 'Huipoca', 'link' => 'https://maps.app.goo.gl/p1'],
                ['etiqueta' => 'Punto de llegada', 'direccion' => 'Codo del Pozuzo', 'link' => ''],
            ],
        ]);

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);
        // En la fila: cada punto con su Maps y el domicilio, en pestaña nueva.
        $comp->assertSeeHtml('href="https://maps.app.goo.gl/p1" target="_blank"')
            ->assertSeeHtml('https://www.google.com/maps/search/?api=1&amp;query=Codo%20del%20Pozuzo')
            ->assertSeeHtml('href="https://maps.app.goo.gl/dom" target="_blank"');
        // En el panel: un botón por punto y el domicilio.
        $comp->call('ver', $r->id)
            ->assertSee('Donde se queda en Maps')
            ->assertSee('Punto de llegada en Maps')
            ->assertSee('Domicilio en Maps');
        // En el formulario: el botón Maps del punto sigue al enlace escrito.
        $comp->call('nuevo')
            ->set('form.puntos.0.link', 'https://maps.app.goo.gl/nuevo')
            ->assertSeeHtml('href="https://maps.app.goo.gl/nuevo" target="_blank"');
    }

    public function test_la_tabla_va_del_ultimo_registrado_hacia_abajo_y_filtra_por_placa(): void
    {
        $this->mundo();
        $otro = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'AWI775', 'marca' => 'HYUNDAI', 'modelo' => 'H1', 'valor' => 25000]);
        $crear = fn (Vehiculo $v, string $fecha, string $dir) => $this->reporte([
            'vehiculo_id' => $v->id, 'placa' => $v->placa, 'fecha' => $fecha, 'puntos' => [['direccion' => $dir, 'link' => '']],
        ]);
        // Manda el orden de registro, no la fecha del reporte: el último registrado va arriba.
        $crear($this->vehiculo, now()->format('Y-m-d H:i'), 'Primero registrado ALP');
        $crear($otro, now()->subDay()->format('Y-m-d H:i'), 'Segundo registrado AWI');
        $crear($this->vehiculo, now()->subDays(2)->format('Y-m-d H:i'), 'Tercero registrado ALP');

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id]);
        $comp->assertSeeInOrder(['Tercero registrado ALP', 'Segundo registrado AWI', 'Primero registrado ALP'])
            ->assertSee('Todas (2)');

        $comp->set('filtroPlaca', 'AWI775')
            ->assertSee('Segundo registrado AWI')
            ->assertDontSee('Primero registrado ALP')
            ->assertDontSee('Tercero registrado ALP')
            ->set('filtroPlaca', '')
            ->assertSee('Primero registrado ALP');
    }

    public function test_sin_direccion_del_vehiculo_no_guarda(): void
    {
        $this->mundo();

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->call('guardar')
            ->assertHasErrors(['form.puntos.0.direccion']);

        $this->assertSame(0, VehiculoGpsReporte::count());
    }

    public function test_sin_horario_ni_enlace_el_mensaje_no_lleva_rotulos_vacios(): void
    {
        $this->mundo();
        $r = $this->reporte(['domicilio_direccion' => 'Mz. G Lt. 5', 'domicilio_link' => '']);

        $texto = $r->texto();
        $this->assertStringNotContainsString('Inicio de ruta', $texto);
        $this->assertStringNotContainsString('Fin de ruta', $texto);
        $this->assertStringNotContainsString('Link de ubicación', $texto);
        $this->assertStringNotContainsString('Horario aproximado', $texto);
        $this->assertStringEndsWith("📍 Ubicación de domicilio:\nMz. G Lt. 5", $texto);
        $this->assertStringContainsString("📄 Expediente: 1299\n\n📍 Ubicación de vehículo:\nPunto X\n\n📍 Ubicación de domicilio:", $texto);

        // Solo el inicio: sale esa línea y no la del fin.
        $r->update(['inicio_desde' => '06:00', 'inicio_hasta' => '07:00']);
        $this->assertStringContainsString("\n\n⏰ Inicio de ruta: 6:00 a.m. – 7:00 a.m.\n\n📍 Ubicación de vehículo:", $r->fresh()->texto());
        $this->assertStringNotContainsString('Fin de ruta', $r->fresh()->texto());
    }

    public function test_horas_en_formato_del_area(): void
    {
        $this->assertSame('6:30 a.m.', VehiculoGpsReporte::hora12('06:30'));
        $this->assertSame('11:00 p.m.', VehiculoGpsReporte::hora12('23:00'));
        $this->assertSame('12:15 a.m.', VehiculoGpsReporte::hora12('00:15'));
        $this->assertSame('12:05 p.m.', VehiculoGpsReporte::hora12('12:05'));
        $this->assertSame('', VehiculoGpsReporte::hora12(null));
        $this->assertSame('6:30 a.m.', VehiculoGpsReporte::rango('06:30', null));
    }

    public function test_la_pestaña_gps_incluye_la_tabla_y_el_analista_de_cartera_propia_solo_mira(): void
    {
        $this->mundo();
        $this->reporte();

        Livewire::test(Gps::class, ['id' => $this->client->id])->assertSeeLivewire('clients.gps-vehiculos');

        $this->user->givePermissionTo('clientes.scope-propio');
        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->assertSet('puedeEditar', false)
            ->assertSee('Punto X')
            ->assertDontSee('Nuevo reporte');
    }
}
