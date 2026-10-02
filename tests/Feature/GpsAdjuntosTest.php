<?php

namespace Tests\Feature;

use App\Livewire\Clients\GpsVehiculos;
use App\Models\Client;
use App\Models\ClientAttachment;
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
 * 02/10/2026 (Antony): las fotos del reporte de GPS también se pueden elegir
 * de la pestaña Adjuntos del cliente, antes de guardar (quedan marcadas y se
 * copian al guardar junto con las subidas) o sobre un reporte ya guardado (se
 * copian al instante). Se COPIAN: borrar el adjunto no toca el reporte. En el
 * listado, las fotos van como en ingresos/egresos: cámara + contador que abre
 * la galería.
 */
class GpsAdjuntosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Vehiculo $vehiculo;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'gps-adj-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '1451', 'nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'HUAMAN',
            'tipo_documento' => 'DNI', 'documento' => '41234568', 'sexo' => 'F',
            'direccion' => 'Av. Túpac Amaru 1234', 'distrito' => 'Comas',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        $this->vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ABC123', 'marca' => 'BAJAJ', 'modelo' => 'RE', 'valor' => 9000]);
        Storage::fake('public');
    }

    /** Un adjunto de la pestaña Adjuntos, con archivo y miniatura reales en el disco. */
    private function adjunto(string $nombre, ?int $clientId = null): ClientAttachment
    {
        $clientId ??= $this->client->id;
        $disco = Storage::disk('public');
        $archivo = UploadedFile::fake()->image($nombre, 600, 400);
        $guardado = basename($disco->putFileAs("clients/{$clientId}", $archivo, uniqid().'.jpg'));
        $disco->put("clients/{$clientId}/thumbs/{$guardado}", $disco->get("clients/{$clientId}/{$guardado}"));

        return ClientAttachment::create([
            'client_id' => $clientId, 'filename' => $guardado, 'original_name' => $nombre,
            'path' => "clients/{$clientId}/{$guardado}", 'thumb_path' => "clients/{$clientId}/thumbs/{$guardado}",
            'mime' => 'image/jpeg', 'size' => 1234, 'uploaded_by' => $this->user->id,
        ]);
    }

    private function formularioListo()
    {
        return Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->set('form.puntos.0.direccion', 'Las Lúcumas, Carabayllo');
    }

    public function test_elegidas_antes_de_guardar_se_copian_al_reporte_junto_con_las_subidas(): void
    {
        $this->mundo();
        $a = $this->adjunto('frente.jpg');
        $b = $this->adjunto('costado.jpg');
        $this->adjunto('otra.jpg');

        $comp = $this->formularioListo()
            ->assertSee('Elegir de Adjuntos (3)')
            ->call('abrirAdjuntos')
            ->assertSet('mostrarAdjuntos', true)
            ->set('adjuntosSel', [(string) $a->id, (string) $b->id])
            ->call('confirmarAdjuntos')
            ->assertSet('mostrarAdjuntos', false)
            ->assertSet('form.adjuntos', [$a->id, $b->id])
            ->assertSee('Guardar y generar el mensaje con 2 fotos')
            ->set('files', [UploadedFile::fake()->image('cochera.jpg', 800, 600)])
            ->assertSee('Guardar y generar el mensaje con 3 fotos')
            ->call('guardar')
            ->assertHasNoErrors();

        $reporte = VehiculoGpsReporte::firstOrFail();
        $fotos = $reporte->fotos;
        $this->assertCount(3, $fotos);
        $this->assertSame([$a->id, $b->id], $fotos->whereNotNull('client_attachment_id')->pluck('client_attachment_id')->map(fn ($v) => (int) $v)->values()->all());
        $this->assertSame(1, $fotos->whereNull('client_attachment_id')->count(), 'la subida no lleva origen');

        $disco = Storage::disk('public');
        foreach ($fotos as $f) {
            $this->assertStringStartsWith("gps/reportes/{$reporte->id}/", $f->path, 'la copia vive en la carpeta del reporte');
            $disco->assertExists($f->path);
            $this->assertNotNull($f->thumb_path);
            $disco->assertExists($f->thumb_path);
        }
        $copia = $fotos->firstWhere('client_attachment_id', $a->id);
        $this->assertSame('frente.jpg', $copia->original_name);
        $this->assertNotSame($a->path, $copia->path, 'es copia, no el mismo archivo');

        // El visor rotula de dónde vino cada una.
        $nombres = collect($reporte->galeria())->pluck('name');
        $this->assertSame(2, $nombres->filter(fn ($n) => str_ends_with($n, '(de Adjuntos)'))->count());

        // El formulario quedó limpio.
        $comp->assertSet('mostrarForm', false)->assertSet('form', []);
    }

    public function test_quitar_una_elegida_antes_de_guardar_y_las_que_no_son_del_cliente_se_ignoran(): void
    {
        $this->mundo();
        $a = $this->adjunto('frente.jpg');
        $b = $this->adjunto('costado.jpg');
        $otro = Client::create([
            'expediente' => '9', 'nombre' => 'OTRO', 'apellido_pat' => 'CLIENTE', 'tipo_documento' => 'DNI', 'documento' => '40000009',
            'direccion' => 'x', 'distrito' => 'y', 'headquarter_id' => $this->client->headquarter_id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        $ajeno = $this->adjunto('ajeno.jpg', $otro->id);

        $comp = $this->formularioListo()
            ->call('abrirAdjuntos')
            ->set('adjuntosSel', [$a->id, $b->id, $ajeno->id])
            ->call('confirmarAdjuntos')
            ->assertSet('form.adjuntos', [$a->id, $b->id])
            ->call('quitarAdjuntoForm', $a->id)
            ->assertSet('form.adjuntos', [$b->id])
            ->assertSee('Guardar y generar el mensaje con 1 foto');

        // Reabrir el modal parte de lo ya elegido.
        $comp->call('abrirAdjuntos')->assertSet('adjuntosSel', [$b->id]);

        $comp->call('guardar')->assertHasNoErrors();
        $this->assertSame([$b->id], VehiculoGpsReporteFoto::pluck('client_attachment_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_sobre_un_reporte_guardado_se_anexan_al_instante_y_no_se_repiten(): void
    {
        $this->mundo();
        $a = $this->adjunto('frente.jpg');
        $b = $this->adjunto('costado.jpg');
        $reporte = VehiculoGpsReporte::create([
            'client_id' => $this->client->id, 'vehiculo_id' => $this->vehiculo->id, 'placa' => 'ABC123',
            'fecha' => now()->format('Y-m-d H:i'), 'puntos' => [['direccion' => 'Punto X', 'link' => '']], 'registrado_por' => $this->user->id,
        ]);

        $comp = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('ver', $reporte->id)
            ->call('abrirAdjuntos')
            ->assertSet('adjuntosSel', [])
            ->set('adjuntosSel', [$a->id])
            ->call('confirmarAdjuntos')
            ->assertSet('msg', 'Foto anexada al reporte desde Adjuntos.')
            ->assertSee('Fotos (1)');
        $this->assertSame(1, $reporte->fotos()->count());

        // Ya anexada: el modal la marca y, si se vuelve a mandar, no se duplica.
        $comp->call('abrirAdjuntos')
            ->assertSeeHtml('ya anexada')
            ->set('adjuntosSel', [$a->id, $b->id])
            ->call('confirmarAdjuntos')
            ->assertSet('msg', 'Foto anexada al reporte desde Adjuntos.');
        $this->assertSame(2, $reporte->fotos()->count());

        $comp->call('abrirAdjuntos')->set('adjuntosSel', [$a->id])->call('confirmarAdjuntos')
            ->assertSet('msg', 'Esas fotos ya estaban en el reporte.');
        $this->assertSame(2, $reporte->fotos()->count());
    }

    public function test_borrar_el_adjunto_de_la_ficha_no_toca_la_copia_del_reporte(): void
    {
        $this->mundo();
        $a = $this->adjunto('frente.jpg');

        $this->formularioListo()
            ->call('abrirAdjuntos')->set('adjuntosSel', [$a->id])->call('confirmarAdjuntos')
            ->call('guardar')->assertHasNoErrors();

        $foto = VehiculoGpsReporteFoto::firstOrFail();
        $disco = Storage::disk('public');
        $disco->delete([$a->path, $a->thumb_path]);
        $a->delete();

        $disco->assertExists($foto->path);
        $disco->assertExists($foto->thumb_path);
        $this->assertNotNull($foto->fresh());
        $this->assertNull($foto->fresh()->adjunto);
    }

    public function test_el_listado_muestra_camara_con_contador_que_abre_la_galeria_como_ingresos_y_egresos(): void
    {
        $this->mundo();
        $a = $this->adjunto('frente.jpg');
        $this->formularioListo()
            ->call('abrirAdjuntos')->set('adjuntosSel', [$a->id])->call('confirmarAdjuntos')
            ->set('files', [UploadedFile::fake()->image('cochera.jpg')])
            ->call('guardar')->assertHasNoErrors();
        $sinFotos = VehiculoGpsReporte::create([
            'client_id' => $this->client->id, 'vehiculo_id' => $this->vehiculo->id, 'placa' => 'ABC123',
            'fecha' => now()->format('Y-m-d H:i'), 'puntos' => [['direccion' => 'Punto X', 'link' => '']], 'registrado_por' => $this->user->id,
        ]);

        $html = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->html();

        // Con fotos: cámara azul + badge "2" que abre el lightbox con las dos (una rotulada "de Adjuntos").
        // Js::from pinta la galería como JSON.parse('…') con las comillas en ".
        $this->assertMatchesRegularExpression(
            '/<a href="#" @click\.prevent="openLightbox\(JSON\.parse\(\'[^\']*\(de Adjuntos\)[^\']*\'\)\s*,\s*0\)"[^>]*>\s*<i class="ti ti-camera f-s-16 text-info"><\/i>\s*<span class="badge bg-info"[^>]*>\s*2\s*<\/span>/s',
            $html,
        );
        // Sin fotos: cámara apagada que abre el reporte.
        $this->assertStringContainsString('wire:click.prevent="ver('.$sinFotos->id.')" title="Sin fotos', $html);
        // Ya no se pintan las miniaturas sueltas de 34px en la tabla.
        $this->assertStringNotContainsString('width:34px; height:34px', $html);
    }

    public function test_sin_adjuntos_el_boton_va_apagado_y_el_analista_de_cartera_propia_no_lo_ve(): void
    {
        $this->mundo();

        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->call('nuevo')
            ->assertSeeHtml('Elegir de Adjuntos (0)')
            ->assertSeeHtml('El cliente no tiene fotos en Adjuntos');

        $this->adjunto('frente.jpg');
        $this->user->givePermissionTo('clientes.scope-propio');
        Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])
            ->assertSet('puedeEditar', false)
            ->assertDontSee('Elegir de Adjuntos')
            ->call('abrirAdjuntos')
            ->assertForbidden();
    }
}
