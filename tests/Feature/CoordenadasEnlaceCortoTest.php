<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Models\Client;
use App\Models\User;
use App\Support\Coordenadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony, ficha 173): "no me deja poner el link de Maps". El enlace
 * de Compartir del celular es corto (maps.app.goo.gl) y no lleva coordenadas:
 * ahora se sigue la redirección hasta el enlace largo y se leen de ahí, con
 * preferencia por el pin exacto (!3d/!4d) sobre el centro del mapa (@lat,lng).
 */
class CoordenadasEnlaceCortoTest extends TestCase
{
    use RefreshDatabase;

    private const LARGO = 'https://www.google.com/maps/place/Av.+Brasil+1280,+Jes%C3%BAs+Mar%C3%ADa/@-12.0631,-77.0527,17z/data=!3m1!4b1!4m6!3m5!1s0x9105c8c0d3d3d3d3:0x1!8m2!3d-12.0631527!4d-77.0501364!16s%2Fg%2F11c?entry=ttu';

    public function test_el_enlace_corto_se_resuelve_y_se_leen_las_coordenadas_del_pin(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => self::LARGO]),
        ]);

        $this->assertSame([-12.0631527, -77.0501364], Coordenadas::parse('https://maps.app.goo.gl/cmxi6j2wpeJEvNWe7'), 'el pin exacto (!3d/!4d), no el centro del mapa ni el 1280 de la dirección');
        Http::assertSentCount(1);

        // El enlace largo se lee sin red; el "@" manda cuando no hay pin; los números sin decimales se ignoran.
        $this->assertSame([-12.0631527, -77.0501364], Coordenadas::parse(self::LARGO));
        $this->assertSame([-12.0464, -77.0428], Coordenadas::parse('https://www.google.com/maps/@-12.0464,-77.0428,17z'));
        $this->assertSame([-12.0464, -77.0428], Coordenadas::parse('https://maps.google.com/?q=-12.0464,-77.0428'));
        $this->assertSame([-12.014431, -76.824936], Coordenadas::parse('-12.014431, -76.824936'));
        $this->assertNull(Coordenadas::parse('Av. Arequipa 3400'));
        Http::assertSentCount(1, 'solo el enlace corto sale a la red');
    }

    public function test_varias_redirecciones_y_enlace_que_no_se_puede_leer(): void
    {
        // Patrones con esquema: "goo.gl/*" a secas también casaría con maps.app.goo.gl y se enredaría.
        Http::fake([
            'https://goo.gl/*' => Http::response('', 301, ['Location' => 'https://maps.app.goo.gl/abc']),
            'https://maps.app.goo.gl/abc' => Http::response('', 302, ['Location' => '/maps/place/x/@-12.1,-77.1,15z']),
            'https://maps.app.goo.gl/muerto' => Http::response('No encontrado', 404),
        ]);

        $this->assertSame([-12.1, -77.1], Coordenadas::parse('https://goo.gl/maps/xyz'), 'dos saltos y una Location relativa');
        $this->assertNull(Coordenadas::parse('https://maps.app.goo.gl/muerto'));
        $this->assertTrue(Coordenadas::esEnlaceDeMaps('https://maps.app.goo.gl/muerto'));
        $this->assertFalse(Coordenadas::esEnlaceDeMaps('https://example.com/mapa'), 'solo se sale a la red por enlaces de Google Maps');
        $this->assertNull(Coordenadas::parse('https://example.com/mapa'));
    }

    public function test_la_pestana_gps_guarda_el_enlace_corto_y_avisa_bien_cuando_no_se_puede_leer(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'gps-corto']));
        $c = Client::create([
            'expediente' => '970', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Enlace',
            'tipo_documento' => 'DNI', 'documento' => '33445600', 'sexo' => 'M', 'status' => 'active',
        ]);
        Http::fake([
            'maps.app.goo.gl/bueno' => Http::response('', 302, ['Location' => self::LARGO]),
            'maps.app.goo.gl/muerto' => Http::response('', 404),
        ]);

        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.casa', 'https://maps.app.goo.gl/bueno')
            ->call('guardar', 'casa')
            ->assertSet('msgType', 'ok');
        $c->refresh();
        $this->assertEqualsWithDelta(-12.0631527, (float) $c->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-77.0501364, (float) $c->longitud, 0.0000001);

        // Enlace de Maps que no se puede leer: aviso específico, no el "formato inválido" genérico.
        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.negocio', 'https://maps.app.goo.gl/muerto')
            ->call('guardar', 'negocio')
            ->assertSet('msgType', 'err')
            ->assertSet('msg', 'No pude leer las coordenadas de ese enlace de Google Maps. Abre el punto en Maps, mantén pulsado el pin, copia las coordenadas y pégalas aquí.');
        $this->assertNull($c->fresh()->latitud2);

        // Y un texto cualquiera sigue con el aviso de siempre, también en "Agregar dirección".
        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('nuevaNombre', 'Taller')->set('nuevaCoordenadas', 'por el mercado')
            ->call('agregar')
            ->assertHasErrors(['nuevaCoordenadas']);
    }
}
