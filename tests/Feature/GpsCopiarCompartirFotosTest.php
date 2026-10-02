<?php

namespace Tests\Feature;

use App\Livewire\Clients\GpsVehiculos;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Models\VehiculoGpsReporteFoto;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): mandar el reporte por WhatsApp con sus fotos. El
 * portapapeles solo lleva una imagen por copia, así que el detalle ofrece
 * "Copiar" por foto (Ctrl+V en WhatsApp Web), "Descargar fotos (N)" como
 * respaldo y "Compartir" (Web Share) donde el navegador lo soporte. Lo que
 * se prueba aquí es el lado servidor: los nombres de descarga y que el HTML
 * lleve los botones con los datos correctos; el comportamiento del
 * portapapeles es del navegador.
 */
class GpsCopiarCompartirFotosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private function mundo(): VehiculoGpsReporte
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'gps-wa-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '1299', 'nombre' => 'WILDER', 'apellido_pat' => 'ROQUE', 'apellido_mat' => 'JANCA',
            'tipo_documento' => 'DNI', 'documento' => '41234567', 'sexo' => 'M',
            'direccion' => 'Mz. G Lt. 5', 'distrito' => 'Carabayllo',
            'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
        ]);
        $vehiculo = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'ALP-837', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 20000]);

        return VehiculoGpsReporte::create([
            'client_id' => $this->client->id, 'vehiculo_id' => $vehiculo->id, 'placa' => 'ALP-837',
            'fecha' => '2026-10-02 10:30', 'puntos' => [['direccion' => 'Las Lúcumas', 'link' => '']], 'registrado_por' => $this->user->id,
        ]);
    }

    private function foto(VehiculoGpsReporte $r, string $ext, ?string $mime): VehiculoGpsReporteFoto
    {
        return VehiculoGpsReporteFoto::create([
            'reporte_id' => $r->id, 'path' => "gps/reportes/{$r->id}/".uniqid().".{$ext}", 'thumb_path' => null,
            'original_name' => "orig.{$ext}", 'mime' => $mime, 'size' => 10,
        ]);
    }

    public function test_las_descargas_llevan_placa_fecha_y_correlativo_con_su_extension(): void
    {
        $r = $this->mundo();
        $a = $this->foto($r, 'jpg', 'image/jpeg');
        $b = $this->foto($r, 'PNG', null);

        $d = $r->fresh()->descargas();

        $this->assertSame([$a->id, $b->id], array_column($d, 'id'));
        $this->assertSame(['ALP837_20261002_1030_1.jpg', 'ALP837_20261002_1030_2.png'], array_column($d, 'nombre'));
        $this->assertSame(['image/jpeg', 'image/jpeg'], array_column($d, 'mime'), 'sin mime guardado se asume JPEG');
        $this->assertSame($a->url(), $d[0]['url']);
        $this->assertSame([], VehiculoGpsReporte::make(['placa' => 'X'])->descargas(), 'sin fotos, nada que descargar');
    }

    public function test_el_detalle_ofrece_copiar_por_foto_descargar_todas_y_compartir(): void
    {
        $r = $this->mundo();
        $a = $this->foto($r, 'jpg', 'image/jpeg');
        $b = $this->foto($r, 'jpg', 'image/jpeg');

        $html = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->call('ver', $r->id)->html();

        // Un "Copiar" por foto, con su id y su url (Js::from escapa las barras).
        $this->assertStringContainsString('x-on:click="copiarFoto('.$a->id.', '.Js::from($a->url()).')"', $html);
        $this->assertStringContainsString('x-on:click="copiarFoto('.$b->id.', '.Js::from($b->url()).')"', $html);
        // Descargar todas y Compartir (este último solo se muestra donde el navegador sabe compartir archivos).
        $this->assertStringContainsString('Descargar fotos (2)', $html);
        $this->assertStringContainsString('x-on:click="descargarFotos()"', $html);
        $this->assertStringContainsString('x-show="compartible" x-cloak x-on:click="compartir()"', $html);
        // Los datos de las fotos van en el estado de Alpine, con el nombre de descarga.
        $this->assertStringContainsString('ALP837_20261002_1030_1.jpg', $html);
        $this->assertStringContainsString('ALP837_20261002_1030_2.jpg', $html);
        // El bloque se recrea si cambian las fotos (clave con sus ids) y la guía para WhatsApp Web está.
        $this->assertStringContainsString('wire:key="detalle-'.$r->id.'-'.$a->id.'-'.$b->id.'"', $html);
        $this->assertStringContainsString('En WhatsApp Web:', $html);
        // Copiar texto sigue igual.
        $this->assertStringContainsString('Copiar texto', $html);
    }

    public function test_sin_fotos_no_hay_descargar_pero_compartir_sigue_para_mandar_el_texto(): void
    {
        $r = $this->mundo();

        $html = Livewire::test(GpsVehiculos::class, ['id' => $this->client->id])->call('ver', $r->id)->html();

        $this->assertStringNotContainsString('Descargar fotos (', $html); // el botón; el aviso de respaldo sí menciona "Descargar fotos"
        $this->assertStringNotContainsString('x-on:click="copiarFoto(', $html);
        $this->assertStringContainsString('x-on:click="compartir()"', $html);
        $this->assertStringContainsString('fotos: [],', $html); // Js::from([]) sale como [] literal
    }
}
